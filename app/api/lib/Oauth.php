<?php

namespace app\api\lib;

/**
 * QQ 互联 OAuth 2.0 客户端。
 *
 * AppID、AppKey 和固定回调域名均从环境变量读取，避免凭据进入代码仓库。
 */
class Oauth
{
    private string $appid;
    private string $appkey;
    private string $callback;

    public function __construct(string $callback)
    {
        $this->appid = trim((string)env('qq_oauth_appid', ''));
        $this->appkey = trim((string)env('qq_oauth_appkey', ''));

        if ($this->appid === '' || $this->appkey === '') {
            throw new \RuntimeException(t('qq.config_incomplete'));
        }

        $baseUrl = rtrim(trim((string)env('qq_oauth_callback_base', '')), '/');
        if ($baseUrl === '') {
            $isHttps = (($_SERVER['SERVER_PORT'] ?? '') === '443')
                || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
            $host = preg_replace('/[^a-z0-9.:-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
            if ($host === '') {
                throw new \RuntimeException(t('qq.callback_domain_unknown'));
            }
            $baseUrl = ($isHttps ? 'https://' : 'http://') . $host;
        }

        if (!preg_match('#^https://[a-z0-9.-]+(?::\d+)?$#i', $baseUrl)) {
            throw new \RuntimeException(t('qq.callback_domain_invalid'));
        }

        $this->callback = $baseUrl . '/' . ltrim($callback, '/');
    }

    public function getAppId(): string
    {
        return $this->appid;
    }

    public function getCallback(): string
    {
        return $this->callback;
    }

    /**
     * 获取 QQ 授权地址。
     *
     * 第二个参数用于新的服务端 OAuth 流程；未传时继续兼容安装程序。
     */
    public function login(string $type = 'qq', ?string $state = null): array
    {
        if ($state === null || $state === '') {
            $state = bin2hex(random_bytes(32));
            session('Oauth_state', $state);
        }

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->appid,
            'redirect_uri' => $this->callback,
            'state' => $state,
            'scope' => 'get_user_info',
        ], '', '&', PHP_QUERY_RFC3986);

        return ['code' => 0, 'url' => 'https://graph.qq.com/oauth2.0/authorize?' . $query];
    }

    /**
     * 用一次性 code 换取 access token、OpenID 和用户公开资料。
     */
    public function callback(string $code): array
    {
        if ($code === '') {
            return ['code' => -1, 'msg' => t('qq.authorization_code_required')];
        }

        $tokenUrl = 'https://graph.qq.com/oauth2.0/token?' . http_build_query([
            'grant_type' => 'authorization_code',
            'client_id' => $this->appid,
            'client_secret' => $this->appkey,
            'code' => $code,
            'redirect_uri' => $this->callback,
            'fmt' => 'json',
        ], '', '&', PHP_QUERY_RFC3986);

        $tokenResponse = $this->getCurl($tokenUrl);
        if (!$tokenResponse['success']) {
            return ['code' => -1, 'msg' => t('qq.authorization_service_unavailable')];
        }

        $tokenData = json_decode($tokenResponse['body'], true);
        if (!is_array($tokenData)) {
            parse_str($tokenResponse['body'], $tokenData);
        }
        if (!empty($tokenData['error']) || empty($tokenData['access_token'])) {
            return ['code' => -1, 'msg' => t('qq.token_fetch_failed')];
        }

        $accessToken = (string)$tokenData['access_token'];
        $openidUrl = 'https://graph.qq.com/oauth2.0/me?' . http_build_query([
            'access_token' => $accessToken,
            'unionid' => 1,
            'fmt' => 'json',
        ], '', '&', PHP_QUERY_RFC3986);
        $openidResponse = $this->getCurl($openidUrl);
        if (!$openidResponse['success']) {
            return ['code' => -1, 'msg' => t('qq.user_identifier_fetch_failed')];
        }

        $openidBody = trim($openidResponse['body']);
        if (strpos($openidBody, 'callback') === 0) {
            $left = strpos($openidBody, '(');
            $right = strrpos($openidBody, ')');
            if ($left !== false && $right !== false && $right > $left) {
                $openidBody = trim(substr($openidBody, $left + 1, $right - $left - 1));
            }
        }
        $openidData = json_decode($openidBody, true);
        if (!is_array($openidData) || !empty($openidData['error']) || empty($openidData['openid'])) {
            return ['code' => -1, 'msg' => t('qq.user_identifier_fetch_failed')];
        }

        $openid = (string)$openidData['openid'];
        $profile = $this->query($accessToken, $openid);

        return [
            'code' => 0,
            // 兼容旧安装流程：该字段历史上实际保存的是 OpenID，而不是临时 access token。
            'access_token' => $openid,
            'social_uid' => $openid,
            'unionid' => (string)($openidData['unionid'] ?? ''),
            'nickname' => (string)($profile['nickname'] ?? ''),
            'avatar' => (string)($profile['avatar'] ?? ''),
        ];
    }

    public function query(string $accessToken, string $openid): array
    {
        $url = 'https://graph.qq.com/user/get_user_info?' . http_build_query([
            'access_token' => $accessToken,
            'oauth_consumer_key' => $this->appid,
            'openid' => $openid,
            'format' => 'json',
        ], '', '&', PHP_QUERY_RFC3986);
        $response = $this->getCurl($url);
        if (!$response['success']) {
            return [];
        }

        $data = json_decode($response['body'], true);
        if (!is_array($data) || (isset($data['ret']) && intval($data['ret']) !== 0)) {
            return [];
        }

        return [
            'nickname' => trim((string)($data['nickname'] ?? '')),
            'avatar' => trim((string)($data['figureurl_qq_2'] ?? $data['figureurl_qq_1'] ?? '')),
        ];
    }

    private function getCurl(string $url): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'QH-QQ-OAuth/1.0',
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $body = curl_exec($ch);
        $error = curl_errno($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'success' => $error === 0 && $status >= 200 && $status < 300 && is_string($body),
            'body' => is_string($body) ? $body : '',
        ];
    }
}
