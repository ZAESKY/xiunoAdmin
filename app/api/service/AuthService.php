<?php

namespace app\api\service;

use app\common\service\BaseService;
use app\common\service\ApiErrorService;
use app\common\service\ReleasePackageService;
use app\common\service\SecureTicketService;
use app\api\validate\Auth;
use app\api\model\AuthModel;
use app\api\model\PirateModel;
use app\api\model\VersionModel;
use app\common\extend\Rsa;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Queue;

class AuthService extends BaseService
{
    /** 授权判定：强制拦截 */
    private const ENFORCE_BLOCK = 1;
    /** 授权判定：监控模式（照常判定并记录，但仍放行）——用于上线前评估影响面 */
    private const ENFORCE_MONITOR = 2;

    public function __construct()
    {
        $this->model = new AuthModel();
        $this->pirateModel = new PirateModel();
        $this->versionModel = new VersionModel();
    }

    private static function joinQueue($type, $param, $post, $get){
        $taskType = $type;
        $jobHandlerClassName = '';
        $jobDataArr = [];
        $jobQueueName = '';
        switch ($taskType) {
            case 'checkAuth':
                $jobHandlerClassName  = 'app\common\job\MultiTask@checkAuth';
                $jobDataArr = ['param' => $param, 'post' => $post, 'get' => $get];
                $jobQueueName = 'multiTaskJobQueue';
                break;
            case 'checkUpdate':
                $jobHandlerClassName  = 'app\common\job\MultiTask@checkUpdate';
                $jobDataArr = ['param' => $param, 'post' => $post, 'get' => $get];
                $jobQueueName = 'multiTaskJobQueue';
                break;
            default:
                return json(message(t('common.server_error'), false));
        }
        $authInfo = isset($param['auth_info']) ? $param['auth_info'] : '';
        $queueKey = self::queueCacheKey($authInfo);
        if(Cache::has($queueKey)){
            return json(message(t('login.in_queue'),true, ['queue' => 1]));
        }else{
            $isPushed = Queue::push($jobHandlerClassName, $jobDataArr, $jobQueueName);
            if ($isPushed !== false) {
                Cache::tag('QH_CheckAuth')->set($queueKey, json_encode(message(t('login.waiting'),true, ['queue' => 1])));
                return json(message(t('login.waiting_result'),true, ['queue' => 1]));
            }else{
                // A-17：不再把内部任务类名回显给调用方
                return json(ApiErrorService::reject('common.server_error', 'queue push failed: ' . $taskType));
            }
        }
    }

    public function queueCheck(){
        $param = request()->param();
        $authInfo = isset($param['auth_info']) ? $param['auth_info'] : '';
        $queueKey = self::queueCacheKey($authInfo);
        if(Cache::has($queueKey)){
            $result = json_decode((string)Cache::get($queueKey), true);
            if(!is_array($result) || !isset($result['data'])){
                Cache::delete($queueKey);
                return json(message('null',true, ['queue' => 1]));
            }
            if(!is_array($result['data']) || !array_key_exists('queue', $result['data'])){
                Cache::delete($queueKey);
            }
            return json($result);
        }else{
            return json(message('null',true, ['queue' => 1]));
        }
    }

    public function checkAuth(){
        $param = request()->param();
        $appid = !empty($param['appid'])?intval($param['appid']):null;
        $api_key = !empty($param['api_key'])?$param['api_key']:null;
        $auth_info = !empty($param['auth_info'])?$param['auth_info']:null;
        if(empty($appid)){
            return json(message(t('auth.enter_qq_code'),false));
        }
        if(empty($api_key)){
            return json(message(t('app.api_key_error'),false));
        }
        // 队列
        if(conf('queue_query_switch') == 1){
            return self::joinQueue('checkAuth', $param, request()->post(), request()->get());
        }

        // A-14：命中缓存时必须返回结构化响应，不能返回裸字符串
        //（原实现返回字符串，导致 checkUpdate 的类型探测失效、鉴权门被跳过）
        $cacheKey = self::authCacheKey($appid, $auth_info);
        if(Cache::has($cacheKey)){
            $cached = json_decode((string)Cache::get($cacheKey), true);
            if(is_array($cached) && array_key_exists('code', $cached)){
                return json($cached);
            }
            Cache::delete($cacheKey);
        }
        return $this->checkAuthInfo($appid, $api_key);
    }

    private function checkAuthInfo($appid, $api_key, $param = null){
        try{
            $appInfo = parent::getAppInfo($appid);
            if($appInfo == false){
                return json(message(t('app.not_exist'),false));
            }
            // A-17：api_key 比对改为常量时间，且失败不回显任何内部信息
            if(!hash_equals((string)$appInfo['api_key'], (string)$api_key)){
                return json(message(t('app.api_key_error'),false));
            }
        }catch (\Exception $e){
            return json(ApiErrorService::fail($e, 'common.server_error', ['appid' => $appid]));
        }

        if(empty($param)){
            $allInfo = $appInfo['check_auth_method'] == 0?request()->get():request()->post();
        }else{
            $allInfo = $param;
        }

        $allInfo['appid'] = $appid;
        $allInfo['api_key'] = $api_key;
        $auth_info = !empty($allInfo['auth_info'])?$allInfo['auth_info']:null;
        $rawParam = !empty($allInfo['param'])?$allInfo['param']:null;
        $decoded = json_decode((string)base64_decode((string)$rawParam), true);
        $decoded = is_array($decoded) ? $decoded : [];
        $decoded['appid'] = $appid;
        $decoded['auth_info'] = $auth_info;
        $authcode = !empty($decoded['authcode'])?$decoded['authcode']:null;

        try {
            validate(Auth::class)->check($allInfo);
        } catch (ValidateException $e) {
            // 校验失败属于业务提示，可原样返回（不含内部细节）
            return json(message($e->getError() ,false));
        }

        $authInfo = $this->model->getInfo([['auth_info', '=', $auth_info],['appid', '=', $appid]]);

        /*
         * 授权判定（A-06 核心修复）
         *
         * 原实现把全部判定包在 if(pirate_msg_switch == 1) 之内，
         * 该开关为其它值时直接返回签名过的「正版」且完全不查授权表 —— 默认失败开放。
         * 现在判定无条件执行；pirate_msg_switch 回归其本职：只决定返回哪段文案。
         */
        $verdict = $this->evaluateAuth($appInfo, $authInfo, $authcode);

        /*
         * 盗版入库
         * 复用统一判定结果，同时修正原实现中 IP 与到期判断的括号分组错误。
         */
        if($appInfo['pirate_switch'] != 0){
            if($appInfo['pirate_switch'] == 2 || !$verdict['ok']){
                try{
                    $this->pirateModel->edit($decoded);
                }catch (\Throwable $e){
                    // 盗版记录失败不得影响授权判定本身
                    Log::warning('[QH-API] pirate record failed: ' . $e->getMessage());
                }
            }
        }

        $enforce = $this->enforceMode($appInfo);

        if(!$verdict['ok']){
            if($enforce === self::ENFORCE_MONITOR){
                // 监控模式：记录本应拦截的请求，但仍按放行返回，便于上线前评估影响面
                Log::warning(sprintf(
                    '[QH-API][auth-monitor] appid=%s reason=%s auth_info=%s',
                    $appid,
                    $verdict['reason'],
                    self::maskHost($auth_info)
                ));
            } else {
                return json(message($verdict['msg'], false, $this->signPayload($appInfo, [
                    'code' => -1,
                    'msg'  => $verdict['msg'],
                    'time' => time(),
                ])));
            }
        }

        /*
         * 判定通过。
         * 返回码沿用原语义，保证既有客户端零改动：
         *   pirate_msg_switch == 1 -> code 0（客户端会写入 session 缓存）
         *   其它                   -> code 1（客户端放行但不缓存）
         */
        if($appInfo['pirate_msg_switch'] == 1){
            $data = $this->signPayload($appInfo, ['code' => 0, 'time' => time()]);
            try{
                if(!empty($authInfo['id'])){
                    AuthModel::where('id', $authInfo['id'])
                        ->data(['checktime' => datetime()])
                        ->update();
                }
            } catch (\Exception $e) {
                return json(ApiErrorService::fail($e, 'auth.update_check_time_failed', ['appid' => $appid]));
            }
            return json(message(t('auth.genuine') ,true, $data));
        }

        $data = $this->signPayload($appInfo, ['code' => 1, 'time' => time()]);
        $response = message(t('auth.genuine') ,true, $data);
        // A-25：缓存键改为哈希，不再以明文域名作为键
        Cache::tag('QH_CheckAuth')->set(self::authCacheKey($appid, $auth_info), json_encode($response), 3600);
        return json($response);
    }

    /**
     * 统一授权判定。返回 ['ok'=>bool, 'reason'=>string, 'msg'=>string]
     *
     * 判定顺序与原实现一致：记录存在 -> 状态 -> 授权码 -> IP -> 期限
     */
    private function evaluateAuth($appInfo, $authInfo, $authcode): array
    {
        if($authInfo == false){
            return [
                'ok' => false,
                'reason' => 'not_found',
                'msg' => $this->msgOr($appInfo, 'pirate_msg', 'auth.not_authorized'),
            ];
        }

        if($authInfo['status'] != 1){
            return [
                'ok' => false,
                'reason' => 'status',
                'msg' => $this->msgOr($appInfo, 'status_msg', 'auth.status_disabled'),
            ];
        }

        if($appInfo['authcode_switch'] == 1){
            if(empty($authcode) || !hash_equals((string)$authInfo['authcode'], (string)$authcode)){
                return [
                    'ok' => false,
                    'reason' => empty($authcode) ? 'authcode_missing' : 'authcode_mismatch',
                    'msg' => $this->msgOr($appInfo, 'authcode_msg', 'auth.authcode_invalid'),
                ];
            }
        }

        if($appInfo['ip_switch'] == 1 && !empty($authInfo['ip'])){
            // 注：gethostbyname 属于既有实现（A-19），P0 不改动其语义，
            // 仅补上解析失败时的兜底，避免解析失败被误判为通过。
            $resolved = @gethostbyname((string)$authInfo['auth_info']);
            $allowList = array_filter(explode('|', (string)$authInfo['ip']), 'strlen');
            if($resolved === '' || $resolved === $authInfo['auth_info'] || !in_array($resolved, $allowList, true)){
                return [
                    'ok' => false,
                    'reason' => 'ip',
                    'msg' => $this->msgOr($appInfo, 'ip_msg', 'auth.ip_not_allowed'),
                ];
            }
        }

        if($authInfo['permanent_switch'] == 0){
            if(empty($authInfo['endtime']) || strtotime((string)$authInfo['endtime']) <= time()){
                return [
                    'ok' => false,
                    'reason' => 'expired',
                    'msg' => $this->msgOr($appInfo, 'endtime_msg', 'auth.expired'),
                ];
            }
        }

        return ['ok' => true, 'reason' => 'ok', 'msg' => ''];
    }

    /**
     * 应用未配置对应提示文案时（pirate_msg_switch 关闭的应用通常为空），
     * 回退到通用 i18n 文案，避免对外返回空字符串。
     */
    private function msgOr($appInfo, string $field, string $fallbackKey): string
    {
        $value = isset($appInfo[$field]) ? trim((string)$appInfo[$field]) : '';
        return $value !== '' ? $value : t($fallbackKey);
    }

    /**
     * 判定模式：1 强制拦截（默认），2 监控放行。
     * 兼容尚未执行迁移的库——字段不存在时按强制处理。
     */
    private function enforceMode($appInfo): int
    {
        if(!isset($appInfo['auth_enforce'])){
            return self::ENFORCE_BLOCK;
        }
        return ((int)$appInfo['auth_enforce'] === self::ENFORCE_MONITOR)
            ? self::ENFORCE_MONITOR
            : self::ENFORCE_BLOCK;
    }

    /**
     * 统一签名封装，避免各处重复拼装导致签名字段不一致。
     */
    private function signPayload($appInfo, array $payload): array
    {
        $data = ['data' => $payload];
        $data['sign'] = Rsa::createSign(json_encode($data['data']), $appInfo['private_key']);
        return $data;
    }

    public function checkUpdate(){
        // A-14：鉴权门统一改为「失败关闭」——无法确认成功即拒绝
        if(conf('queue_query_switch') == 1){
            $r = $this->queueCheck();
            $result = self::toArray($r);
            if(!$this->passed($result)){
                return json($result ?: message(t('common.server_error'), false));
            }
        }else{
            $r = $this->checkAuth();
            $result = self::toArray($r);
            if(!$this->passed($result)){
                return json($result ?: message(t('common.server_error'), false));
            }
        }

        $param = request()->param();
        $appid = !empty($param['appid'])?intval($param['appid']):null;
        if(!$appid){
            return json(message(t('auth.enter_qq_code'),false));
        }
        try{
            $appInfo = parent::getAppInfo($appid);
            if($appInfo == false){
                return json(message(t('app.not_exist'),false));
            }
        }catch (\Exception $e){
            return json(ApiErrorService::fail($e, 'common.server_error', ['appid' => $appid]));
        }

        $allInfo = $appInfo['check_auth_method'] == 0?request()->get():request()->post();
        $auth_info = !empty($allInfo['auth_info'])?$allInfo['auth_info']:null;
        $appid = !empty($allInfo['appid'])?intval($allInfo['appid']):$appid;
        $decoded = json_decode((string)base64_decode((string)($allInfo['param'] ?? '')), true);
        $decoded = is_array($decoded) ? $decoded : [];
        $version = !empty($decoded['version'])?$decoded['version']:null;
        $beta = !empty($decoded['beta'])?intval($decoded['beta']):0;

        $authInfo = $this->model->getInfo([['auth_info', '=', $auth_info],['appid', '=', $appid]]);
        if($authInfo == false){
            // 走到这里说明鉴权门已放行（例如监控模式），但没有授权记录则不得下发更新
            return json(message(t('auth.not_authorized'), false, $this->signPayload($appInfo, [
                'code' => -1,
                'msg'  => t('auth.not_authorized'),
                'time' => time(),
            ])));
        }

        if(empty($version)){
            return json(message(t('version.version_empty') ,false, $this->signPayload($appInfo, [
                'code' => -1,
                'time' => time(),
            ])));
        }

        $betaFilter = ((int)$authInfo['beta'] === 1) ? [0, 1] : [$beta];
        $versionData = $this->versionModel->getAppUpdateVersionList($appid,$version,$betaFilter);
        if($versionData['count'] == 0){
            return json(message(t('version.is_latest') ,true, $this->signPayload($appInfo, [
                'code' => 0,
                'time' => time(),
            ])));
        }
        if($beta == 1 && (int)$authInfo['beta'] !== 1){
            return json(message(t('version.no_beta_access') ,false, $this->signPayload($appInfo, [
                'code' => -1,
                'msg'  => t('version.no_beta_access'),
                'time' => time(),
            ])));
        }

        try{
            /*
             * A-07：更新元信息可以缓存，但下载票据必须每次重新签发。
             * 原实现把 md5(uniqid()) 票据一并写进 600 秒响应缓存，
             * 导致同一票据被反复下发；改为「元信息缓存 + 票据实时签发」。
             */
            $metaKey = self::updateMetaCacheKey($appid, $auth_info, $version, $beta);
            $meta = Cache::get($metaKey);
            if(empty($meta) || !is_array($meta)){
                $meta = $this->buildUpdateMeta($versionData, $version);
                Cache::tag('QH_Version')->set($metaKey, $meta, 600);
            }

            $allVersion = $meta;
            $allVersion['url'] = $this->downloadBaseUrl();
            $allVersion['download'] = [];
            foreach($meta['version_ids'] as $versionId){
                // 票据只携带 ID 引用；版本行的存在性与包完整性由下载端再次校验
                $allVersion['download'][] = SecureTicketService::issue(
                    [
                        'version_id' => (int)$versionId,
                        'auth_id'    => (int)$authInfo['id'],
                        'appid'      => (int)$appid,
                    ],
                    [
                        'appid'   => (int)$appid,
                        'auth_id' => (int)$authInfo['id'],
                        'ip'      => request()->ip(),
                    ]
                );
            }
            unset($allVersion['version_ids']);

            if(empty($allVersion['download'])){
                return json(message(t('version.file_not_exist'), false, $this->signPayload($appInfo, [
                    'code' => -1,
                    'msg'  => t('version.file_not_exist'),
                    'time' => time(),
                ])));
            }

            $data = $this->signPayload($appInfo, [
                'code' => 1,
                'data' => $allVersion,
                'time' => time(),
            ]);
            return json(message(t('version.latest_version').$allVersion['edition'] ,true , $data));
        }catch (\Exception $e){
            return json(ApiErrorService::fail($e, 'common.server_error', ['appid' => $appid]));
        }
    }

    /**
     * 组装可缓存的更新元信息（不含任何票据）
     */
    private function buildUpdateMeta($versionData, $currentVersion): array
    {
        $meta = [
            'count'       => $versionData['count'],
            'edition'     => '1.0',
            'version'     => $currentVersion,
            'update_log'  => [],
            'download'    => [],
            'filesize'    => 0,
            'introduce'   => '',
            'url'         => '',
            'version_ids' => [],
        ];

        foreach($versionData['list'] as $res){
            // A-24：版本号按数值比较，避免字符串比较造成的顺序错误
            if((int)$meta['version'] < (int)$res['version']){
                $meta['version'] = $res['version'];
                $meta['edition'] = $res['edition'];
            }
            if (($res['storage_driver'] ?? 'local') === 'oss') {
                $meta['filesize'] += round(((int)($res['package_size'] ?? 0)) / 1048576 * 100) / 100;
            } else {
                // A-23：本地包统一走 ReleasePackageService，含目录名清洗与 type 判别
                $meta['filesize'] += ReleasePackageService::sizeMb($res['type'] ?? 1, $res['download_catalogue'] ?? '');
            }
            $meta['version_ids'][] = (int)$res['id'];
            $meta['introduce'] .= $res['update_log'].',';
            $update_log = [$res['addtime'] => explode("\n", (string)$res['update_log'])];
            $meta['update_log'] = array_merge($update_log ?? [], $meta['update_log']);
        }
        $meta['introduce'] = rtrim($meta['introduce'], ',');
        $meta['filesize'] = round($meta['filesize'] * 100) / 100;

        return $meta;
    }

    /**
     * 下载基址。
     * A-02 部分缓解：不再硬编码 http://，优先使用后台配置的完整地址，
     * 未配置时按当前请求协议推导（客户端仍沿用 ?sign= 参数名，保证兼容）。
     */
    private function downloadBaseUrl(): string
    {
        $base = trim((string)conf('download_base_url'));
        if($base === ''){
            $scheme = 'http';
            try{
                if(request()->isSsl() || (int)conf('force_https_download') === 1){
                    $scheme = 'https';
                }
            }catch (\Throwable $e){
                // 无请求上下文（队列任务）时退回配置或 http
                if((int)conf('force_https_download') === 1){
                    $scheme = 'https';
                }
            }
            $base = defined('SITE_URL') ? SITE_URL : ($scheme . '://' . DOMAIN);
        }
        return rtrim($base, '/') . '/api.php/Download/download/?sign=';
    }

    /**
     * 判断一个内部调用结果是否代表「鉴权通过」。
     * 必须显式确认 code === 0，缺字段/类型异常一律视为失败（fail-closed）。
     */
    private function passed($result): bool
    {
        return is_array($result)
            && array_key_exists('code', $result)
            && (int)$result['code'] === 0;
    }

    /**
     * 把控制器风格的返回值统一成数组
     */
    private static function toArray($r)
    {
        if(is_array($r)){
            return $r;
        }
        if(is_object($r) && method_exists($r, 'getData')){
            $data = $r->getData();
            return is_array($data) ? $data : [];
        }
        if(is_object($r) && method_exists($r, 'toArray')){
            $data = $r->toArray();
            return is_array($data) ? $data : [];
        }
        if(is_string($r)){
            $decoded = json_decode($r, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /* ---------------- 缓存键（A-25：不再以明文域名/授权信息作为键） ---------------- */

    private static function authCacheKey($appid, $auth_info): string
    {
        return 'check_auth_' . hash('sha256', $appid . '|' . (string)$auth_info);
    }

    private static function queueCacheKey($auth_info): string
    {
        return 'queue_check_' . hash('sha256', (string)$auth_info);
    }

    private static function updateMetaCacheKey($appid, $auth_info, $version, $beta): string
    {
        return 'update_meta_' . hash('sha256', implode('|', [$appid, (string)$auth_info, (string)$version, (string)$beta]));
    }

    /**
     * 日志中的域名脱敏：保留可读性但不落完整主机名
     */
    private static function maskHost($host): string
    {
        $host = (string)$host;
        if($host === ''){
            return '';
        }
        return substr(hash('sha256', $host), 0, 12);
    }
}
