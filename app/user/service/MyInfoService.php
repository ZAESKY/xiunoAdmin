<?php

namespace app\user\service;

use app\common\service\UserBaseService;
use app\user\model\User;
use think\facade\Db;
use think\facade\Log;

class MyInfoService extends UserBaseService
{
    public function __construct(){
        $this->model = new User();
    }

    public function updatePower(){
        return $this->model->updatePower();
    }

    public function editUserInfo(){
        return $this->model->editUserInfo();
    }

    public function changeUsername(int $userId): array
    {
        $username = trim((string)request()->post('username', ''));
        if (!preg_match('/^[\p{L}\p{N}_-]{2,30}$/u', $username)) {
            return message(t('profile_username.invalid'), false);
        }
        if (in_array(mb_strtolower($username, 'UTF-8'), [
            'admin', 'administrator', 'root', 'system', 'official', '官方', '管理员', '系统',
        ], true)) {
            return message(t('profile_username.reserved'), false);
        }

        try {
            $result = Db::transaction(function () use ($userId, $username) {
                $user = Db::name('user')->where('id', $userId)
                    ->field('id,appid,username,password')->lock(true)->find();
                if (!$user) {
                    throw new \RuntimeException(t('user.not_exist'));
                }
                $qqIdentity = Db::name('user_social_identity')->alias('usi')
                    ->join('social_identity si', 'si.id = usi.identity_id')
                    ->where('usi.user_id', $userId)
                    ->where('si.provider', 'qq')
                    ->lock(true)
                    ->value('si.id');
                if (empty($qqIdentity)) {
                    throw new \RuntimeException(t('profile_username.qq_only'));
                }
                if (hash_equals((string)$user['username'], $username)) {
                    return ['user' => $user, 'changed' => false];
                }
                if (Db::name('user')->where('username', $username)->where('id', '<>', $userId)->find()) {
                    throw new \DomainException(t('profile_username.duplicate'));
                }
                $updated = Db::name('user')->where('id', $userId)->where('username', $user['username'])
                    ->update(['username' => $username]);
                if ($updated !== 1) {
                    throw new \RuntimeException(t('profile_username.failed'));
                }
                $user['username'] = $username;
                return ['user' => $user, 'changed' => true];
            });
        } catch (\Throwable $e) {
            $duplicate = $e instanceof \DomainException
                || strpos(strtolower($e->getMessage()), 'duplicate') !== false
                || (string)$e->getCode() === '23000';
            return message($duplicate ? t('profile_username.duplicate') : sf_public_exception_message($e, t('profile_username.failed')), false);
        }

        $user = $result['user'];
        cookie('userSign', data_auth_sign($user['appid'] . $user['username'] . $user['password'] . sf_password_hash()));
        if (!empty($result['changed'])) {
            event('ActionLog', [
                'Title' => t('profile_username.action_title'),
                '操作' => t('profile_username.action_title'),
                'Result' => 'success',
            ]);
            Log::notice('QQ OAuth user changed username', ['user_id' => $userId]);
        }
        return message(!empty($result['changed']) ? t('profile_username.success') : t('profile_username.unchanged'), true);
    }
}
