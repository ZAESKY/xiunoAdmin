<?php

namespace app\api\controller;

use app\api\lib\Oauth;
use app\common\controller\ApiBackend;
use app\common\service\ApplicationInstallerService;
use app\common\service\RateLimitService;
use think\facade\Db;
use think\facade\Session;

class Social extends ApiBackend
{
    private const OAUTH_EXPIRE_SECONDS = 600;
    private const PENDING_EXPIRE_SECONDS = 600;
    private const PROOF_EXPIRE_SECONDS = 300;
    private const FLOW_RESULT_EXPIRE_SECONDS = 600;
    private const UNIFIED_CALLBACK = 'api.php/Social/callback';

    /**
     * 发起 QQ 登录，或处理 QQ 回调页面转发过来的 code/state。
     */
    public function login()
    {
        if (!$this->isTrustedPost()) {
            return message(t('common.illegal_request'), false);
        }

        $state = trim((string)input('post.state/s', ''));
        $oauthError = trim((string)input('post.oauth_error/s', ''));
        if ($oauthError !== '' && $state !== '') {
            $context = $this->getOauthContext($state);
            $flowId = is_array($context) ? (string)($context['flow_id'] ?? '') : '';
            $this->deleteOauthContext($state);
            $result = message(t('qq.oauth_cancelled'), false);
            if ($flowId !== '') {
                $this->storeOauthResult($flowId, $result);
            }
            return $result;
        }

        $code = trim((string)input('post.code/s', ''));
        if ($code !== '') {
            $context = $this->getOauthContext($state);
            $flowId = is_array($context) ? (string)($context['flow_id'] ?? '') : '';
            $result = $this->finishCallback($code, $state, trim((string)input('post.userType/s', '')));
            if ($flowId !== '') {
                $this->storeOauthResult($flowId, $result);
            }
            return $result;
        }

        $userType = trim((string)input('post.userType/s', ''));
        if (!in_array($userType, ['user', 'admin'], true)) {
            return message(t('validation.missing_user_type'), false);
        }
        if (!$this->qqLoginEnabled()) {
            return message(t('qq.login_disabled'), false);
        }

        return $this->beginOauth(
            $userType,
            'login',
            [],
            $userType === 'admin' ? '/admin.php/login/index.html' : '/user.php/login/index.html'
        );
    }

    /**
     * QQ-only registration entry. Existing QQ identities log in directly;
     * new identities are created atomically after the OAuth callback.
     */
    public function register()
    {
        if (!$this->isTrustedPost()) {
            return message(t('common.illegal_request'), false);
        }
        if (!$this->qqLoginEnabled()) {
            return message(t('qq.registration_disabled'), false);
        }
        return $this->beginOauth('user', 'register', [], '/user.php/login/reg.html');
    }

    /**
     * 已登录用户/管理员发起正式 QQ OAuth 绑定。
     * 回调仍由登录页接收，具体动作记录在服务端 Session 中。
     */
    public function binding()
    {
        if (!$this->isTrustedPost()) {
            return message(t('common.illegal_request'), false);
        }

        $userType = trim((string)input('post.userType/s', ''));
        if (!in_array($userType, ['user', 'admin'], true)) {
            return message(t('validation.missing_user_type'), false);
        }
        if (!$this->qqLoginEnabled()) {
            return message(t('qq.login_disabled'), false);
        }

        if ($userType === 'user') {
            try {
                parent::userLogin();
            } catch (\Throwable $e) {
                return message(sf_public_exception_message($e, t('qq.login_check_failed')), false);
            }
        } elseif (!$this->validateAdminSession()) {
            return message(t('login.not_logged_in'), false);
        }

        return $this->beginOauth(
            $userType,
            'bind',
            [],
            $this->safeReturnTo(trim((string)input('post.return_to/s', '')), $userType === 'admin'
                ? '/admin.php/Index/index.html'
                : '/user.php/MyInfo/index.html')
        );
    }

    /**
     * 发起需要 QQ 再验证的敏感操作。数字 QQ 不再由浏览器提交，
     * 而是从一次性迁移后保存的可信关系中读取。
     */
    public function verify()
    {
        if (!$this->isTrustedPost()) {
            return message(t('common.illegal_request'), false);
        }
        if (!$this->qqLoginEnabled()) {
            return message(t('qq.login_disabled'), false);
        }

        $scene = trim((string)input('post.scene/s', ''));
        $returnTo = $this->safeReturnTo(trim((string)input('post.return_to/s', '')), '/user.php/Index/index.html');
        if ($scene === 'auth_binding') {
            try {
                parent::userLogin();
            } catch (\Throwable $e) {
                return message(sf_public_exception_message($e, t('qq.login_check_failed')), false);
            }
            return $this->beginOauth('user', 'auth_binding', [], $returnTo);
        }

        if ($scene === 'download') {
            if (sf_download_mode() !== 'qrcode') {
                return message(t('download.qq_disabled'), false);
            }
            $appId = intval(input('post.appid/d', 0));
            $authInfo = trim((string)input('post.auth_info/s', ''));
            if ($appId <= 0 || $authInfo === '' || mb_strlen($authInfo) > 255) {
                return message(t('download.app_or_auth_invalid'), false);
            }
            return $this->beginOauth('user', 'download', [
                'appid' => $appId,
                'auth_info' => $authInfo,
            ], $returnTo);
        }

        return message(t('qq.unsupported_verification_scene'), false);
    }

    /** QQ 互联唯一回调地址：GET /api.php/Social/callback */
    public function callback()
    {
        if (strtoupper((string)$this->request->method()) !== 'GET') {
            return response('Method Not Allowed', 405);
        }

        $state = trim((string)input('get.state/s', ''));
        $context = $this->getOauthContext($state);
        $flowId = is_array($context) ? (string)($context['flow_id'] ?? '') : '';
        $returnTo = is_array($context)
            ? $this->safeReturnTo((string)($context['return_to'] ?? ''), '/user.php/login/index.html')
            : '/user.php/login/index.html';

        $rate = RateLimitService::hit('qq_oauth_callback', (string)get_client_ip(), 60, 3600);
        if (!$rate['ok']) {
            $result = message(t('qq.callback_rate_limited'), false);
        } elseif (trim((string)input('get.error/s', '')) !== '') {
            $this->deleteOauthContext($state);
            $result = message(t('qq.oauth_cancelled'), false);
        } else {
            $result = $this->finishCallback(
                trim((string)input('get.code/s', '')),
                $state,
                ''
            );
        }

        if ($flowId !== '') {
            $this->storeOauthResult($flowId, $result);
        }
        $resultData = is_array($result['data'] ?? null) ? $result['data'] : [];
        $resultUrl = (string)($resultData['url'] ?? '');
        $fallback = $this->safeReturnTo($resultUrl, $returnTo);
        $needsFlow = intval($result['code'] ?? -1) !== 0
            || in_array((string)($resultData['status'] ?? ''), ['unbound', 'verified'], true);
        return $this->oauthCallbackPage($flowId, $fallback, $needsFlow);
    }

    /** 弹窗父页面轮询 OAuth 结果；flow_id 同时受当前 Session 约束。 */
    public function status()
    {
        if (strtoupper((string)$this->request->method()) !== 'GET') {
            return message(t('common.illegal_request'), false);
        }
        $flowId = trim((string)input('get.flow_id/s', ''));
        if (!preg_match('/^[a-f0-9]{64}$/D', $flowId)) {
            return message(t('qq.flow_invalid'), false);
        }
        $rate = RateLimitService::hit('qq_oauth_status', (string)get_client_ip(), 600, 600);
        if (!$rate['ok']) {
            return message(t('qq.status_rate_limited'), false);
        }
        $results = session('qq_oauth_results');
        $entry = is_array($results) ? ($results[$flowId] ?? null) : null;
        if (!is_array($entry) || time() - intval($entry['created_at'] ?? 0) > self::FLOW_RESULT_EXPIRE_SECONDS) {
            return message('pending', true, ['completed' => false]);
        }
        return message('completed', true, [
            'completed' => true,
            'result' => $entry['result'],
        ]);
    }

    /** 使用最近一次正规 QQ 复验凭证列出或绑定历史授权。 */
    public function legacyAuth()
    {
        if (!$this->isTrustedPost()) {
            return message(t('common.illegal_request'), false);
        }
        try {
            parent::userLogin();
        } catch (\Throwable $e) {
            return message(sf_public_exception_message($e, t('qq.login_check_failed')), false);
        }

        $proof = $this->getOauthProof(trim((string)input('post.proof_token/s', '')));
        if (!$proof || intval($proof['user_id'] ?? 0) !== intval($this->userId)) {
            return message(t('qq.reverification_expired'), false);
        }
        $legacyQq = (string)$proof['legacy_qq'];
        $action = trim((string)input('post.action/s', 'list'));
        if ($action === 'list') {
            $rows = Db::name('auth')
                ->where(['qq' => $legacyQq, 'bindingid' => 0, 'appid' => intval($this->userInfo['appid'])])
                ->field('id,auth_info')
                ->order('id', 'asc')
                ->select()
                ->toArray();
            if (empty($rows)) {
                return message(t('auth.no_auth_to_bind'), false);
            }
            return message(t('auth.select_bind_auth'), true, ['list' => $rows]);
        }
        if ($action !== 'bind') {
            return message(t('auth.unsupported_bind_action'), false);
        }

        $list = input('post.list/a', []);
        $ids = array_values(array_unique(array_filter(array_map('intval', is_array($list) ? $list : []), static function ($id) {
            return $id > 0;
        })));
        if (empty($ids) || count($ids) > 100) {
            return message(t('auth.bind_selection_range', ['min' => 1, 'max' => 100]), false);
        }
        try {
            Db::transaction(function () use ($ids, $legacyQq) {
                foreach ($ids as $id) {
                    $row = Db::name('auth')
                        ->where(['id' => $id, 'appid' => intval($this->userInfo['appid'])])
                        ->field('id,qq,bindingid')
                        ->lock(true)
                        ->find();
                    if (!$row || intval($row['bindingid']) !== 0 || !hash_equals((string)$row['qq'], $legacyQq)) {
                        throw new \RuntimeException(t('auth.owner_or_bind_state_changed'));
                    }
                    $updated = Db::name('auth')
                        ->where(['id' => $id, 'appid' => intval($this->userInfo['appid']), 'bindingid' => 0])
                        ->update(['bindingid' => intval($this->userId)]);
                    if ($updated !== 1) {
                        throw new \RuntimeException(t('auth.bind_state_changed'));
                    }
                }
            });
            $this->deleteOauthProof((string)$proof['token']);
            return message(t('auth.bind_success'), true);
        } catch (\Throwable $e) {
            return message(sf_public_exception_message($e, t('auth.bind_retry')), false);
        }
    }

    /** 已登录用户通过旧扫码做最后一次数字 QQ 证明。 */
    public function claimLegacy()
    {
        if (!$this->isTrustedPost()) {
            return message(t('common.illegal_request'), false);
        }
        try {
            parent::userLogin();
        } catch (\Throwable $e) {
            return message(sf_public_exception_message($e, t('qq.login_check_failed')), false);
        }
        $legacyQq = trim((string)session('get_qq'));
        $scanToken = trim((string)session('get_token'));
        if ($scanToken === '' || !preg_match('/^[1-9][0-9]{4,11}$/D', $legacyQq)) {
            return message(t('qq.legacy_scan_expired'), false);
        }
        $identity = $this->getUserQqIdentity(intval($this->userId));
        if (!$identity) {
            return message(t('qq.bind_official_first'), false);
        }
        try {
            Db::transaction(function () use ($identity, $legacyQq) {
                $this->saveLegacyClaim(intval($identity['id']), intval($this->userId), $legacyQq);
            });
            Session::delete('get_token');
            Session::delete('get_qq');
            Session::save();
            return message(t('qq.legacy_migration_success'), true);
        } catch (\Throwable $e) {
            return message(sf_public_exception_message($e, t('qq.legacy_migration_failed')), false);
        }
    }

    public function adminUnbind()
    {
        if (!$this->isTrustedPost()) {
            return message(t('common.illegal_request'), false);
        }
        if (!$this->validateAdminSession()) {
            return message(t('login.not_logged_in'), false);
        }
        Db::name('admin')->where('id', intval(session('adminId')))->update(['access_token' => null]);
        return message(t('qq.admin_unbind_success'), true);
    }

    /**
     * 用户确认没有旧账号后，为待处理的 QQ 身份自动创建账号。
     */
    public function autoRegister()
    {
        if (!$this->isTrustedPost()) {
            return message(t('common.illegal_request'), false);
        }

        $pending = $this->getPendingIdentity(trim((string)input('post.pending_token/s', '')));
        if (!$pending) {
            return message(t('qq.credential_expired'), false);
        }
        $rate = RateLimitService::hit('qq_register_ip_day', (string)get_client_ip(), 20, 86400);
        if (!$rate['ok']) {
            return message(t('qq.daily_registration_limited'), false);
        }

        try {
            $result = Db::transaction(function () use ($pending) {
                $identity = Db::name('social_identity')->where('id', intval($pending['identity_id']))->lock(true)->find();
                if (!$identity || $identity['provider'] !== 'qq') {
                    throw new \RuntimeException(t('qq.identity_not_found'));
                }

                $users = $this->getBoundUsers(intval($identity['id']), true);
                if (empty($users)) {
                    $userId = $this->createOauthUser($identity);
                    $this->insertUserBinding(intval($identity['id']), $userId);
                    $users = $this->getBoundUsers(intval($identity['id']), true);
                }
                return ['identity_id' => intval($identity['id']), 'users' => $users];
            });

            Session::delete('qq_oauth_pending');
            Session::save();
            return $this->completeUserLogin($result['users'], $result['identity_id'], true);
        } catch (\Throwable $e) {
            return message(sf_public_exception_message($e, t('qq.auto_registration_failed')), false);
        }
    }

    /**
     * QQ OAuth 已完成后，再用旧扫码证明数字 QQ，并迁移该 QQ 的全部本地账号。
     */
    public function migrateLegacy()
    {
        if (!$this->isTrustedPost()) {
            return message(t('common.illegal_request'), false);
        }

        $pending = $this->getPendingIdentity(trim((string)input('post.pending_token/s', '')));
        $legacyQq = trim((string)session('get_qq'));
        if (!$pending || $legacyQq === '' || !preg_match('/^[1-9][0-9]{4,11}$/D', $legacyQq)) {
            return message(t('qq.migration_credential_expired'), false);
        }

        try {
            $result = Db::transaction(function () use ($pending, $legacyQq) {
                $identity = Db::name('social_identity')->where('id', intval($pending['identity_id']))->lock(true)->find();
                if (!$identity || $identity['provider'] !== 'qq') {
                    throw new \RuntimeException(t('qq.identity_not_found'));
                }

                $userIds = Db::name('user')->where('qq', $legacyQq)->order('id', 'asc')->column('id');
                if (empty($userIds)) {
                    throw new \RuntimeException(t('qq.legacy_account_not_found'));
                }
                if (count($userIds) !== 1) {
                    throw new \RuntimeException(t('qq.multiple_legacy_accounts'));
                }

                $boundCount = $this->bindUsersToIdentity(intval($identity['id']), array_map('intval', $userIds));
                if ($boundCount <= 0) {
                    throw new \RuntimeException(t('qq.identity_binding_conflict'));
                }
                $this->saveLegacyClaim(intval($identity['id']), intval($userIds[0]), $legacyQq);

                return [
                    'identity_id' => intval($identity['id']),
                    'users' => $this->getBoundUsers(intval($identity['id']), true),
                ];
            });

            Session::delete('get_token');
            Session::delete('get_qq');
            Session::delete('qq_oauth_pending');
            Session::save();

            if (empty($result['users'])) {
                return message(t('qq.migrated_account_disabled'), false);
            }
            return $this->completeUserLogin($result['users'], $result['identity_id'], false);
        } catch (\Throwable $e) {
            Session::delete('get_token');
            Session::delete('get_qq');
            Session::save();
            return message(sf_public_exception_message($e, t('qq.legacy_account_migration_failed')), false);
        }
    }

    /**
     * 兼容旧版前端入口。QQ 身份现已强制与本地账号一对一，不再允许选择多个账号。
     */
    public function chooseUser()
    {
        if (!$this->isTrustedPost()) {
            return message(t('common.illegal_request'), false);
        }
        Session::delete('qq_oauth_selection');
        Session::save();
        return message(t('qq.single_account_only'), false);
    }

    public function unbind()
    {
        if (!$this->isTrustedPost()) {
            return message(t('common.illegal_request'), false);
        }

        try {
            parent::userLogin();
        } catch (\Throwable $e) {
            return message(sf_public_exception_message($e, t('qq.login_check_failed')), false);
        }

        $userId = intval(cookie('userId'));
        Db::transaction(function () use ($userId) {
            $identityIds = Db::name('user_social_identity')->alias('usi')
                ->join('social_identity si', 'si.id = usi.identity_id')
                ->where('usi.user_id', $userId)
                ->where('si.provider', 'qq')
                ->column('si.id');
            if (empty($identityIds)) {
                return;
            }

            Db::name('qq_identity_claim')->where('user_id', $userId)->whereIn('identity_id', $identityIds)->delete();
            Db::name('user_social_identity')->where('user_id', $userId)->whereIn('identity_id', $identityIds)->delete();
            foreach ($identityIds as $identityId) {
                if (Db::name('user_social_identity')->where('identity_id', intval($identityId))->count() === 0) {
                    Db::name('social_identity')->where('id', intval($identityId))->delete();
                }
            }
        });

        return message(t('qq.quick_login_unbound'), true);
    }

    private function beginOauth(string $userType, string $action, array $payload = [], string $returnTo = ''): array
    {
        try {
            $rate = RateLimitService::hit('qq_oauth_start', (string)get_client_ip(), 30, 600);
            if (!$rate['ok']) {
                return message(t('qq.request_rate_limited'), false);
            }
            $configuredCallback = trim((string)env('qq_oauth_callback_path', ''));
            // 未切换 QQ 互联后台前，统一复用站点当前已登记的用户登录回调。
            // 该页面只负责把 code/state 交回服务端，业务场景仍由服务端上下文决定。
            $callback = $configuredCallback !== '' ? $configuredCallback : 'user.php/login/index.html';
            if (!in_array($callback, [self::UNIFIED_CALLBACK, 'user.php/login/index.html', 'admin.php/login/index.html'], true)) {
                throw new \RuntimeException(t('qq.callback_path_invalid'));
            }
            $oauth = new Oauth($callback);
            $state = bin2hex(random_bytes(32));
            $flowId = bin2hex(random_bytes(32));
            $context = [
                'state' => $state,
                'flow_id' => $flowId,
                'user_type' => $userType,
                'action' => $action,
                'user_id' => $userType === 'user' ? intval(cookie('userId')) : 0,
                'admin_id' => $userType === 'admin' ? intval(session('adminId')) : 0,
                'callback' => $callback,
                'return_to' => $this->safeReturnTo($returnTo, $userType === 'admin'
                    ? '/admin.php/login/index.html'
                    : '/user.php/login/index.html'),
                'payload' => $payload,
                'created_at' => time(),
            ];
            $contexts = session('qq_oauth_contexts');
            $contexts = is_array($contexts) ? $contexts : [];
            foreach ($contexts as $key => $item) {
                if (!is_array($item) || time() - intval($item['created_at'] ?? 0) > self::OAUTH_EXPIRE_SECONDS) {
                    unset($contexts[$key]);
                }
            }
            $contexts[$state] = $context;
            session('qq_oauth_contexts', $contexts);
            // 兼容已经由旧登录页发起、尚未完成的单流程实现。
            session('qq_oauth_context', $context);
            Session::save();
            return message('success', true, [
                'url' => $oauth->login('qq', $state)['url'],
                'flow_id' => $flowId,
                'popup_enabled' => in_array($callback, [self::UNIFIED_CALLBACK, 'user.php/login/index.html'], true),
            ]);
        } catch (\Throwable $e) {
            return message(sf_public_exception_message($e, t('qq.initialization_failed')), false);
        }
    }

    private function finishCallback(string $code, string $state, string $userType): array
    {
        $context = $this->getOauthContext($state);
        if (!is_array($context)
            || empty($context['state'])
            || $state === ''
            || !hash_equals((string)$context['state'], $state)
            || ($userType !== '' && ($context['user_type'] ?? '') !== $userType)
            || time() - intval($context['created_at'] ?? 0) > self::OAUTH_EXPIRE_SECONDS
        ) {
            return message(t('qq.state_expired'), false);
        }

        $this->deleteOauthContext($state);

        try {
            $userType = (string)$context['user_type'];
            $callback = (string)($context['callback'] ?? ($userType === 'admin'
                ? 'admin.php/login/index.html'
                : 'user.php/login/index.html'));
            $oauthClient = new Oauth($callback);
            $oauth = $oauthClient->callback($code);
            if (($oauth['code'] ?? -1) !== 0) {
                return message(t('qq.login_failed'), false);
            }

            $action = (string)($context['action'] ?? 'login');
            if ($action === 'register') {
                if ($userType !== 'user') {
                    return message(t('qq.registration_scene_invalid'), false);
                }
                return $this->finishUserOauthRegistration($oauth, $oauthClient->getAppId());
            }
            if ($action === 'bind') {
                return $userType === 'admin'
                    ? $this->finishAdminBinding($context, $oauth)
                    : $this->finishUserBinding($context, $oauth, $oauthClient->getAppId());
            }
            if ($action === 'auth_binding') {
                return $this->finishAuthBindingProof($context, $oauth, $oauthClient->getAppId());
            }
            if ($action === 'download') {
                return $this->finishDownloadProof($context, $oauth, $oauthClient->getAppId());
            }

            return $userType === 'admin'
                ? $this->finishAdminLogin($oauth)
                : $this->finishUserOauthLogin($oauth, $oauthClient->getAppId());
        } catch (\Throwable $e) {
            return message(sf_public_exception_message($e, t('qq.login_retry')), false);
        }
    }

    private function finishUserOauthLogin(array $oauth, string $providerAppId): array
    {
        $identity = $this->ensureIdentity($oauth, $providerAppId);
        $users = $this->getBoundUsers(intval($identity['id']), true);
        if (empty($users)) {
            $token = bin2hex(random_bytes(32));
            session('qq_oauth_pending', [
                'token' => $token,
                'identity_id' => intval($identity['id']),
                'created_at' => time(),
            ]);
            Session::save();
            return message(t('qq.first_login_action_required'), true, [
                'status' => 'unbound',
                'pending_token' => $token,
            ]);
        }

        return $this->completeUserLogin($users, intval($identity['id']), false);
    }

    private function finishUserOauthRegistration(array $oauth, string $providerAppId): array
    {
        $identity = $this->ensureIdentity($oauth, $providerAppId);
        $users = $this->getBoundUsers(intval($identity['id']), true);
        if (!empty($users)) {
            return $this->completeUserLogin($users, intval($identity['id']), false);
        }
        $rate = RateLimitService::hit('qq_register_ip_day', (string)get_client_ip(), 20, 86400);
        if (!$rate['ok']) {
            return message(t('qq.daily_registration_limited'), false);
        }

        try {
            $result = Db::transaction(function () use ($identity) {
                $lockedIdentity = Db::name('social_identity')
                    ->where('id', intval($identity['id']))
                    ->lock(true)
                    ->find();
                if (!$lockedIdentity) {
                    throw new \RuntimeException(t('qq.identity_not_found'));
                }
                $boundUsers = $this->getBoundUsers(intval($identity['id']), true);
                if (empty($boundUsers)) {
                    $userId = $this->createOauthUser($lockedIdentity);
                    $this->insertUserBinding(intval($identity['id']), $userId);
                    $boundUsers = $this->getBoundUsers(intval($identity['id']), true);
                }
                return $boundUsers;
            });
            return $this->completeUserLogin($result, intval($identity['id']), true);
        } catch (\Throwable $e) {
            return message(sf_public_exception_message($e, t('qq.registration_failed')), false);
        }
    }

    private function finishUserBinding(array $context, array $oauth, string $providerAppId): array
    {
        try {
            parent::userLogin();
        } catch (\Throwable $e) {
            return message(sf_public_exception_message($e, t('qq.login_check_failed')), false);
        }
        $currentUserId = intval(cookie('userId'));
        if ($currentUserId <= 0 || $currentUserId !== intval($context['user_id'] ?? 0)) {
            return message(t('qq.binding_login_changed'), false);
        }

        $identity = $this->ensureIdentity($oauth, $providerAppId);
        // 普通账号绑定只关联当前账号；批量迁移必须再通过旧 QQ 扫码证明数字 QQ。
        $boundCount = Db::transaction(function () use ($identity, $currentUserId) {
            return $this->bindUsersToIdentity(intval($identity['id']), [$currentUserId]);
        });
        if ($boundCount <= 0) {
            return message(t('qq.current_binding_conflict'), false);
        }

        return message(t('qq.quick_login_bound'), true, [
            'status' => 'bound',
            'url' => $this->safeReturnTo((string)($context['return_to'] ?? ''), '/user.php/MyInfo/index.html'),
        ]);
    }

    private function finishAuthBindingProof(array $context, array $oauth, string $providerAppId): array
    {
        try {
            parent::userLogin();
        } catch (\Throwable $e) {
            return message(sf_public_exception_message($e, t('qq.login_check_failed')), false);
        }
        $currentUserId = intval(cookie('userId'));
        if ($currentUserId <= 0 || $currentUserId !== intval($context['user_id'] ?? 0)) {
            return message(t('qq.verification_login_changed'), false);
        }
        $identity = $this->ensureIdentity($oauth, $providerAppId);
        if ($this->getIdentityUserId(intval($identity['id'])) !== $currentUserId) {
            return message(t('qq.official_identity_mismatch'), false);
        }
        $claim = $this->getLegacyClaim(intval($identity['id']), $currentUserId);
        if (!$claim) {
            return message(t('qq.complete_legacy_migration_first'), false);
        }
        $token = $this->storeOauthProof($currentUserId, intval($identity['id']), (string)$claim['legacy_qq']);
        return message(t('qq.reverification_success'), true, [
            'status' => 'verified',
            'proof_token' => $token,
            'url' => $this->safeReturnTo((string)($context['return_to'] ?? ''), '/user.php/Auth/list.html'),
        ]);
    }

    private function finishDownloadProof(array $context, array $oauth, string $providerAppId): array
    {
        $identity = $this->ensureIdentity($oauth, $providerAppId);
        $userId = $this->getIdentityUserId(intval($identity['id']));
        if ($userId <= 0) {
            return message(t('qq.platform_account_not_bound'), false);
        }
        $payload = is_array($context['payload'] ?? null) ? $context['payload'] : [];
        $appId = intval($payload['appid'] ?? 0);
        $authInfo = trim((string)($payload['auth_info'] ?? ''));
        $auth = Db::name('auth')
            ->where(['appid' => $appId, 'auth_info' => $authInfo])
            ->field('id,appid,qq,bindingid')
            ->find();
        if (!$auth) {
            return message(t('auth.not_exist'), false);
        }

        $owned = intval($auth['bindingid'] ?? 0) === $userId;
        if (!$owned) {
            $claim = $this->getLegacyClaim(intval($identity['id']), $userId);
            $owned = $claim
                && (string)$auth['qq'] !== ''
                && hash_equals((string)$auth['qq'], (string)$claim['legacy_qq']);
        }
        if (!$owned) {
            return message(t('qq.download_forbidden'), false);
        }

        $ticket = ApplicationInstallerService::issueDownloadTicket($appId, intval($auth['id']), (string)get_client_ip());
        if (empty($ticket['ok'])) {
            return message((string)($ticket['msg'] ?? t('download.ticket_issue_failed')), false);
        }
        return message(t('qq.download_verified'), true, [
            'status' => 'download',
            'url' => SITE_URL . '/api.php/Download/download/?sign=' . rawurlencode((string)$ticket['ticket']),
        ]);
    }

    private function finishAdminLogin(array $oauth): array
    {
        $openid = (string)$oauth['social_uid'];
        $admin = Db::name('admin')->where(['access_token' => $openid, 'status' => 1])->find();
        if (!$admin) {
            return message(t('user.qq_not_bound'), false);
        }

        Session::regenerate(true);
        session('adminId', $admin['id'], 86400);
        session('adminSign', data_auth_sign($admin['username'] . $admin['password'] . sf_password_hash()), 86400);
        Session::save();
        return message('success', true, [
            'status' => 'success',
            'url' => '/admin.php/Index/index.html',
        ]);
    }

    private function finishAdminBinding(array $context, array $oauth): array
    {
        if (!$this->validateAdminSession() || intval(session('adminId')) !== intval($context['admin_id'] ?? 0)) {
            return message(t('qq.admin_binding_login_changed'), false);
        }

        $adminId = intval(session('adminId'));
        $openid = (string)$oauth['social_uid'];
        $exists = Db::name('admin')->where('access_token', $openid)->where('id', '<>', $adminId)->find();
        if ($exists) {
            return message(t('qq.admin_already_bound'), false);
        }
        Db::name('admin')->where('id', $adminId)->update(['access_token' => $openid]);

        return message(t('qq.quick_login_bound'), true, [
            'status' => 'bound',
            'url' => $this->safeReturnTo((string)($context['return_to'] ?? ''), '/admin.php/Index/index.html'),
        ]);
    }

    private function ensureIdentity(array $oauth, string $providerAppId): array
    {
        $providerUid = trim((string)($oauth['social_uid'] ?? ''));
        if ($providerUid === '' || strlen($providerUid) > 128) {
            throw new \RuntimeException(t('qq.user_identifier_invalid'));
        }

        $prefix = (string)config('database.connections.mysql.prefix');
        $table = '`' . str_replace('`', '``', $prefix . 'social_identity') . '`';
        Db::execute(
            "INSERT INTO {$table} (`provider`,`provider_appid`,`provider_uid`,`unionid`,`nickname`,`avatar`,`created_at`,`updated_at`) VALUES (?,?,?,?,?,?,?,?) "
            . "ON DUPLICATE KEY UPDATE `unionid`=VALUES(`unionid`),`nickname`=VALUES(`nickname`),`avatar`=VALUES(`avatar`),`updated_at`=VALUES(`updated_at`)",
            [
                'qq',
                $providerAppId,
                $providerUid,
                trim((string)($oauth['unionid'] ?? '')),
                mb_substr(trim((string)($oauth['nickname'] ?? '')), 0, 255),
                mb_substr(trim((string)($oauth['avatar'] ?? '')), 0, 500),
                datetime(),
                datetime(),
            ]
        );

        $identity = Db::name('social_identity')->where([
            'provider' => 'qq',
            'provider_appid' => $providerAppId,
            'provider_uid' => $providerUid,
        ])->find();
        if (!$identity) {
            throw new \RuntimeException(t('qq.identity_save_failed'));
        }
        return $identity;
    }

    private function getBoundUsers(int $identityId, bool $activeOnly): array
    {
        $query = Db::name('user_social_identity')->alias('usi')
            ->join('user u', 'u.id = usi.user_id')
            ->leftJoin('app a', 'a.id = u.appid')
            ->where('usi.identity_id', $identityId)
            ->field('u.*, a.name AS appname')
            ->order('u.id', 'asc');
        if ($activeOnly) {
            $query->where('u.status', 1);
        }
        return $query->select()->toArray();
    }

    private function completeUserLogin(array $users, int $identityId, bool $registered): array
    {
        if (count($users) !== 1) {
            return message(t('qq.binding_data_abnormal'), false);
        }

        $this->setUserLogin($users[0]);
        $redirect = sf_plugin_detail_redirect((string)Session::get('user_login_redirect', ''));
        Session::delete('user_login_redirect');
        return message($registered ? t('qq.registration_login_success') : t('login.success'), true, [
            'status' => 'success',
            'registered' => $registered,
            'url' => $redirect !== '' ? '/user.php' . $redirect : '/user.php/Index/index.html',
        ]);
    }

    private function setUserLogin(array $user): void
    {
        Session::regenerate(true);
        cookie('userId', $user['id']);
        cookie('userSign', data_auth_sign($user['appid'] . $user['username'] . $user['password'] . sf_password_hash()));
        event('UserLogin', [
            'Title' => '登录后台',
            '结果' => '登录成功[QQ快捷登录]',
            'Result' => 'success',
        ]);
    }

    private function createOauthUser(array $identity): int
    {
        $appId = max(1, intval(env('qq_oauth_default_appid', 1)));
        $app = Db::name('app')->where(['id' => $appId, 'status' => 2, 'register_switch' => 1])->find();
        if (!$app) {
            throw new \RuntimeException(t('qq.auto_registration_disabled'));
        }

        $power = Db::name('power_price')->where([
            'tid' => intval($app['power_template']),
            'status' => 1,
            'default_power' => 1,
        ])->value('id');
        if (empty($power)) {
            throw new \RuntimeException(t('qq.default_permission_missing'));
        }

        $nickname = sf_plain_text($identity['nickname'] ?? '', 40);
        $base = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', $nickname);
        $base = trim((string)$base, '_-');
        if ($base === '' || in_array(mb_strtolower($base, 'UTF-8'), ['admin', 'administrator', 'root', 'system', '官方', '管理员'], true)) {
            $base = 'QQUser';
        }
        $base = mb_substr($base, 0, 28, 'UTF-8');
        $suffix = substr(hash('sha256', $identity['provider_appid'] . '|' . $identity['provider_uid']), 0, 8);
        $username = $base . '_' . $suffix;
        for ($attempt = 0; Db::name('user')->where('username', $username)->find(); $attempt++) {
            if ($attempt >= 10) {
                throw new \RuntimeException(t('qq.unique_username_failed'));
            }
            $username = $base . '_' . $suffix . '_' . bin2hex(random_bytes(2));
        }

        return intval(Db::name('user')->insertGetId([
            'username' => $username,
            'password' => sf_password_make(bin2hex(random_bytes(32))),
            'phone' => '',
            'qq' => null,
            'wechat_openid' => '',
            'email' => '',
            'appid' => $appId,
            'status' => 1,
            'balance' => 0,
            'integral' => 0,
            'power' => intval($power),
            'addtime' => datetime(),
            'userid' => 0,
        ]));
    }

    private function bindUsersToIdentity(int $identityId, array $userIds): int
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (count($userIds) !== 1) {
            return 0;
        }
        $userId = $userIds[0];

        $identityOwner = Db::name('user_social_identity')
            ->where('identity_id', $identityId)
            ->where('user_id', '<>', $userId)
            ->lock(true)
            ->value('user_id');
        if (!empty($identityOwner)) {
            return 0;
        }

        $conflictingIdentity = Db::name('user_social_identity')->alias('usi')
            ->join('social_identity si', 'si.id = usi.identity_id')
            ->where('usi.user_id', $userId)
            ->where('si.provider', 'qq')
            ->where('si.id', '<>', $identityId)
            ->lock(true)
            ->value('si.id');
        if (!empty($conflictingIdentity)) {
            return 0;
        }

        $prefix = (string)config('database.connections.mysql.prefix');
        $table = '`' . str_replace('`', '``', $prefix . 'user_social_identity') . '`';
        Db::execute(
            "INSERT IGNORE INTO {$table} (`identity_id`,`user_id`,`created_at`) VALUES (?,?,?)",
            [$identityId, $userId, datetime()]
        );

        return Db::name('user_social_identity')->where([
            'identity_id' => $identityId,
            'user_id' => $userId,
        ])->find() ? 1 : 0;
    }

    private function insertUserBinding(int $identityId, int $userId): void
    {
        if ($this->bindUsersToIdentity($identityId, [$userId]) !== 1) {
            throw new \RuntimeException(t('qq.current_binding_conflict'));
        }
    }

    private function getPendingIdentity(string $token): ?array
    {
        $pending = session('qq_oauth_pending');
        if (!is_array($pending)
            || empty($pending['token'])
            || $token === ''
            || !hash_equals((string)$pending['token'], $token)
            || time() - intval($pending['created_at'] ?? 0) > self::PENDING_EXPIRE_SECONDS
        ) {
            return null;
        }
        return $pending;
    }

    private function getOauthContext(string $state): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $state)) {
            return null;
        }
        $contexts = session('qq_oauth_contexts');
        if (is_array($contexts) && is_array($contexts[$state] ?? null)) {
            return $contexts[$state];
        }
        $legacy = session('qq_oauth_context');
        return is_array($legacy) && hash_equals((string)($legacy['state'] ?? ''), $state) ? $legacy : null;
    }

    private function deleteOauthContext(string $state): void
    {
        $contexts = session('qq_oauth_contexts');
        if (is_array($contexts)) {
            unset($contexts[$state]);
            session('qq_oauth_contexts', $contexts);
        }
        $legacy = session('qq_oauth_context');
        if (is_array($legacy) && hash_equals((string)($legacy['state'] ?? ''), $state)) {
            Session::delete('qq_oauth_context');
        }
        Session::save();
    }

    private function storeOauthResult(string $flowId, array $result): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $flowId)) {
            return;
        }
        $results = session('qq_oauth_results');
        $results = is_array($results) ? $results : [];
        foreach ($results as $key => $entry) {
            if (!is_array($entry) || time() - intval($entry['created_at'] ?? 0) > self::FLOW_RESULT_EXPIRE_SECONDS) {
                unset($results[$key]);
            }
        }
        $results[$flowId] = ['result' => $result, 'created_at' => time()];
        session('qq_oauth_results', $results);
        Session::save();
    }

    private function storeOauthProof(int $userId, int $identityId, string $legacyQq): string
    {
        $token = bin2hex(random_bytes(32));
        $proofs = session('qq_oauth_proofs');
        $proofs = is_array($proofs) ? $proofs : [];
        foreach ($proofs as $key => $proof) {
            if (!is_array($proof) || time() - intval($proof['created_at'] ?? 0) > self::PROOF_EXPIRE_SECONDS) {
                unset($proofs[$key]);
            }
        }
        $proofs[$token] = [
            'token' => $token,
            'user_id' => $userId,
            'identity_id' => $identityId,
            'legacy_qq' => $legacyQq,
            'created_at' => time(),
        ];
        session('qq_oauth_proofs', $proofs);
        Session::save();
        return $token;
    }

    private function getOauthProof(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return null;
        }
        $proofs = session('qq_oauth_proofs');
        $proof = is_array($proofs) ? ($proofs[$token] ?? null) : null;
        if (!is_array($proof) || time() - intval($proof['created_at'] ?? 0) > self::PROOF_EXPIRE_SECONDS) {
            return null;
        }
        return $proof;
    }

    private function deleteOauthProof(string $token): void
    {
        $proofs = session('qq_oauth_proofs');
        if (is_array($proofs)) {
            unset($proofs[$token]);
            session('qq_oauth_proofs', $proofs);
            Session::save();
        }
    }

    private function getUserQqIdentity(int $userId): ?array
    {
        $row = Db::name('user_social_identity')->alias('usi')
            ->join('social_identity si', 'si.id = usi.identity_id')
            ->where('usi.user_id', $userId)
            ->where('si.provider', 'qq')
            ->field('si.*')
            ->find();
        return $row ?: null;
    }

    private function getIdentityUserId(int $identityId): int
    {
        return intval(Db::name('user_social_identity')->where('identity_id', $identityId)->value('user_id'));
    }

    private function getLegacyClaim(int $identityId, int $userId): ?array
    {
        $claim = Db::name('qq_identity_claim')->where([
            'identity_id' => $identityId,
            'user_id' => $userId,
            'status' => 1,
        ])->find();
        return $claim ?: null;
    }

    private function saveLegacyClaim(int $identityId, int $userId, string $legacyQq): void
    {
        if (!preg_match('/^[1-9][0-9]{4,11}$/D', $legacyQq)) {
            throw new \RuntimeException(t('qq.legacy_format_invalid'));
        }
        if ($this->getIdentityUserId($identityId) !== $userId) {
            throw new \RuntimeException(t('qq.official_identity_mismatch'));
        }
        $user = Db::name('user')->where('id', $userId)->lock(true)->field('id,qq')->find();
        if (!$user) {
            throw new \RuntimeException(t('user.not_exist'));
        }
        if ((string)($user['qq'] ?? '') !== '' && !hash_equals((string)$user['qq'], $legacyQq)) {
            throw new \RuntimeException(t('qq.scanned_legacy_mismatch'));
        }
        $otherUser = Db::name('user')->where('qq', $legacyQq)->where('id', '<>', $userId)->lock(true)->value('id');
        if (!empty($otherUser)) {
            throw new \RuntimeException(t('qq.legacy_owned_by_other'));
        }
        $conflict = Db::name('qq_identity_claim')
            ->where(function ($query) use ($identityId, $userId, $legacyQq) {
                $query->whereOr('identity_id', $identityId)
                    ->whereOr('user_id', $userId)
                    ->whereOr('legacy_qq', $legacyQq);
            })
            ->lock(true)
            ->select()
            ->toArray();
        foreach ($conflict as $row) {
            if (intval($row['identity_id']) !== $identityId
                || intval($row['user_id']) !== $userId
                || !hash_equals((string)$row['legacy_qq'], $legacyQq)
            ) {
                throw new \RuntimeException(t('qq.legacy_identity_conflict'));
            }
        }
        $now = datetime();
        $existing = Db::name('qq_identity_claim')->where('identity_id', $identityId)->find();
        if ($existing) {
            Db::name('qq_identity_claim')->where('id', intval($existing['id']))->update([
                'status' => 1,
                'verified_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            Db::name('qq_identity_claim')->insert([
                'identity_id' => $identityId,
                'user_id' => $userId,
                'legacy_qq' => $legacyQq,
                'proof_method' => 'legacy_qr',
                'verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
                'status' => 1,
            ]);
        }
        if ((string)($user['qq'] ?? '') === '') {
            Db::name('user')->where('id', $userId)->update(['qq' => $legacyQq]);
        }
    }

    private function safeReturnTo(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }
        $parts = parse_url($value);
        if (!is_array($parts)) {
            return $fallback;
        }
        if (isset($parts['scheme']) || isset($parts['host'])) {
            $base = parse_url(rtrim(trim((string)env('qq_oauth_callback_base', '')), '/'));
            if (!is_array($base)
                || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
                || !hash_equals(strtolower((string)($base['host'] ?? '')), strtolower((string)($parts['host'] ?? '')))
            ) {
                return $fallback;
            }
        }
        $path = (string)($parts['path'] ?? '');
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return $fallback;
        }
        $result = $path;
        if (isset($parts['query']) && $parts['query'] !== '') {
            $result .= '?' . $parts['query'];
        }
        return $result;
    }

    private function oauthCallbackPage(string $flowId, string $target, bool $needsFlow)
    {
        $flowJson = json_encode($flowId, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        $targetJson = json_encode($target, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        $needsFlowJson = $needsFlow ? 'true' : 'false';
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lang = $escape(t('common.lang_code'));
        $title = $escape(t('qq.callback_page_title'));
        $message = $escape(t('qq.callback_processing'));
        $html = '<!doctype html><html lang="' . $lang . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $title . '</title>'
            . '<link rel="stylesheet" href="/Assets/css/system-message.css"></head>'
            . '<body class="oauth-callback"><div class="system-message info"><h1 aria-hidden="true">●</h1><p>' . $message . '</p></div><script>(function(){'
            . 'var flow=' . $flowJson . ',target=' . $targetJson . ',needsFlow=' . $needsFlowJson . ';'
            . 'var delivered=false;if(flow&&window.opener&&!window.opener.closed){try{window.opener.postMessage({type:"sf.qq.oauth.complete",flow_id:flow},window.location.origin);delivered=true;}catch(e){}}'
            . 'if(delivered){setTimeout(function(){window.close();},250);return;}'
            . 'if(needsFlow&&flow){target+=(target.indexOf("?")>=0?"&":"?")+"qq_oauth_flow="+encodeURIComponent(flow);}'
            . 'window.location.replace(target||"/");})();</script></body></html>';
        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    private function validateAdminSession(): bool
    {
        $adminId = intval(session('adminId'));
        $sign = (string)session('adminSign');
        if ($adminId <= 0 || $sign === '') {
            return false;
        }
        $admin = Db::name('admin')->where(['id' => $adminId, 'status' => 1])->find();
        return $admin && hash_equals(
            data_auth_sign($admin['username'] . $admin['password'] . sf_password_hash()),
            $sign
        );
    }

    /**
     * Cookie 登录态的写操作只接受同源 XHR。自定义请求头会让跨域浏览器
     * 请求触发预检，再由 API 中间件核对 Origin；普通跨站表单无法伪造该头。
     */
    private function isTrustedPost(): bool
    {
        return IS_POST
            && strtolower(trim((string)$this->request->header('x-requested-with'))) === 'xmlhttprequest';
    }

    private function qqLoginEnabled(): bool
    {
        $switch = conf('login_switch');
        if (!is_array($switch)) {
            $switch = array_filter(explode(',', (string)$switch));
        }
        return in_array('qq', $switch, true);
    }
}
