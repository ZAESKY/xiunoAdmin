<?php

namespace app\common\service;

/**
 * Minimal Aliyun Dysmsapi client for verification codes.
 * Uses the official RPC signature protocol and does not require an SDK.
 */
final class AliyunSmsService
{
    private const DYSMS_ENDPOINT = 'https://dysmsapi.aliyuncs.com/';
    private const DYPNS_ENDPOINT = 'https://dypnsapi.aliyuncs.com/';
    private const API_VERSION = '2017-05-25';
    private const TEMPLATE_CONFIG = [
        'login_register' => 'sms_template_login_register',
        'phone_change' => 'sms_template_phone_change',
        'password_reset' => 'sms_template_password_reset',
        'phone_bind' => 'sms_template_phone_bind',
        'phone_verify' => 'sms_template_phone_verify',
    ];

    public static function enabled(): bool
    {
        return (string)conf('sms_enabled') === '1';
    }

    public static function configured(string $purpose = 'default'): bool
    {
        if (!self::enabled()) {
            return false;
        }
        try {
            $config = self::config($purpose);
            return $config['access_key_id'] !== ''
                && $config['access_key_secret'] !== ''
                && $config['sign_name'] !== ''
                && ($config['template_code'] !== '' || ($purpose === 'default' && self::hasPurposeTemplate()));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return array{ok:bool,message:string,request_id:string,error_code:string,template_code:string} */
    public static function sendVerificationCode(string $phone, string $code, string $purpose = 'default'): array
    {
        if (!preg_match('/^1[3-9][0-9]{9}$/D', $phone)) {
            return self::failure('手机号格式不正确', 'INVALID_PHONE');
        }
        if (!preg_match('/^[0-9]{6}$/D', $code)) {
            return self::failure('验证码格式不正确', 'INVALID_CODE');
        }
        try {
            $config = self::config($purpose);
        } catch (\Throwable $e) {
            return self::failure($e->getMessage(), 'CONFIG_ERROR');
        }
        if (!self::enabled()) {
            return self::failure('短信认证尚未启用', 'SMS_DISABLED');
        }
        if ($config['access_key_id'] === '' || $config['access_key_secret'] === ''
            || $config['sign_name'] === '' || $config['template_code'] === '') {
            return self::failure('短信配置不完整，请联系管理员', 'CONFIG_INCOMPLETE', '', $config['template_code']);
        }

        $isPnvs = self::isPnvsTemplate($config['template_code']);
        $params = [
            'AccessKeyId' => $config['access_key_id'],
            'Action' => $isPnvs ? 'SendSmsVerifyCode' : 'SendSms',
            'Format' => 'JSON',
            'RegionId' => 'cn-hangzhou',
            'SignName' => $config['sign_name'],
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce' => bin2hex(random_bytes(16)),
            'SignatureVersion' => '1.0',
            'TemplateCode' => $config['template_code'],
            'TemplateParam' => json_encode(
                $isPnvs
                    ? ['code' => $code, 'min' => (string)ceil(self::codeTtl() / 60)]
                    : ['code' => $code],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'Version' => self::API_VERSION,
        ];
        if ($isPnvs) {
            // 号码认证服务的赠送签名只能搭配数字赠送模板（例如 100001）。
            $params['CountryCode'] = '86';
            $params['PhoneNumber'] = $phone;
            $params['ValidTime'] = (string)self::codeTtl();
            $params['Interval'] = '60';
        } else {
            // 普通短信服务使用 SMS_ 开头的模板 CODE。
            $params['PhoneNumbers'] = $phone;
        }
        ksort($params, SORT_STRING);
        $canonical = [];
        foreach ($params as $key => $value) {
            $canonical[] = self::percentEncode((string)$key) . '=' . self::percentEncode((string)$value);
        }
        $stringToSign = 'GET&%2F&' . self::percentEncode(implode('&', $canonical));
        $params['Signature'] = base64_encode(hash_hmac(
            'sha1',
            $stringToSign,
            $config['access_key_secret'] . '&',
            true
        ));

        $endpoint = $isPnvs ? self::DYPNS_ENDPOINT : self::DYSMS_ENDPOINT;
        $url = $endpoint . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'XiunoAdmin-SMS/1.0',
        ]);
        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($body === false) {
            return self::failure($error !== '' ? '短信服务连接失败' : '短信服务返回异常', 'HTTP_ERROR');
        }
        $data = json_decode((string)$body, true);
        if (!is_array($data)) {
            return self::failure($status === 200 ? '短信服务返回格式异常' : '短信服务返回异常', 'INVALID_RESPONSE');
        }
        $requestId = sf_plain_text($data['RequestId'] ?? '', 128);
        $providerCode = trim((string)($data['Code'] ?? ''));
        if ($status >= 200 && $status < 300 && strtoupper($providerCode) === 'OK') {
            return [
                'ok' => true,
                'message' => '验证码已发送',
                'request_id' => $requestId,
                'error_code' => '',
                'template_code' => $config['template_code'],
            ];
        }
        return self::failure(
            self::publicError($providerCode),
            $providerCode ?: 'PROVIDER_ERROR',
            $requestId,
            $config['template_code']
        );
    }

    public static function templateCodeForPurpose(string $purpose): string
    {
        $key = self::TEMPLATE_CONFIG[$purpose] ?? '';
        $specific = $key !== '' ? trim((string)conf($key)) : '';
        if ($specific !== '') {
            return $specific;
        }
        return trim((string)conf('sms_template_code'));
    }

    private static function hasPurposeTemplate(): bool
    {
        foreach (self::TEMPLATE_CONFIG as $key) {
            if (trim((string)conf($key)) !== '') {
                return true;
            }
        }
        return false;
    }

    private static function config(string $purpose = 'default'): array
    {
        $storedSecret = trim((string)conf('sms_access_key_secret'));
        $secret = $storedSecret !== ''
            ? SecretConfigService::decrypt($storedSecret)
            : trim((string)env('sms_access_key_secret', ''));
        return [
            'access_key_id' => trim((string)(conf('sms_access_key_id') ?: env('sms_access_key_id', ''))),
            'access_key_secret' => $secret,
            'sign_name' => trim((string)conf('sms_sign_name')),
            'template_code' => self::templateCodeForPurpose($purpose),
        ];
    }

    private static function percentEncode(string $value): string
    {
        return str_replace(['+', '*', '%7E'], ['%20', '%2A', '~'], rawurlencode($value));
    }

    private static function isPnvsTemplate(string $templateCode): bool
    {
        return preg_match('/^[0-9]{6,20}$/D', $templateCode) === 1;
    }

    private static function codeTtl(): int
    {
        return max(120, min(600, intval(conf('sms_code_ttl') ?: 300)));
    }

    private static function publicError(string $code): string
    {
        $map = [
            'ISV.BUSINESS_LIMIT_CONTROL' => '发送过于频繁，请稍后再试',
            'ISV.MOBILE_NUMBER_ILLEGAL' => '手机号格式不正确',
            'ISV.SMS_SIGNATURE_ILLEGAL' => '短信签名未通过审核或配置错误',
            'ISV.SMS_TEMPLATE_ILLEGAL' => '短信模板未通过审核或配置错误',
            'ISV.INVALID_PARAMETERS' => '短信模板参数配置错误',
            'INVALIDACCESSKEYID.NOTFOUND' => '短信 AccessKey ID 无效',
            'SIGNATUREDOESNOTMATCH' => '短信 AccessKey Secret 无效',
            'BUSINESS_LIMIT_CONTROL' => '发送过于频繁，请稍后再试',
            'FREQUENCY_FAIL' => '发送过于频繁，请稍后再试',
            'MOBILE_NUMBER_ILLEGAL' => '手机号格式不正确',
            'INVALID_PARAMETERS' => '短信模板参数配置错误',
            'FUNCTION_NOT_OPENED' => '号码认证短信服务尚未开通',
            'FORBIDDEN.RAM' => '短信 AccessKey 没有号码认证发送权限',
            'NOAUTH' => '短信 AccessKey 没有号码认证发送权限',
        ];
        return $map[strtoupper($code)] ?? '短信发送失败，请检查阿里云短信配置或稍后重试';
    }

    private static function failure(string $message, string $code, string $requestId = '', string $templateCode = ''): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'request_id' => $requestId,
            'error_code' => $code,
            'template_code' => $templateCode,
        ];
    }
}
