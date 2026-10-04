<?php
declare(strict_types=1);

namespace app\common\middleware;

use Closure;
use think\Request;

/**
 * Reject cross-site state-changing browser requests for cookie-authenticated apps.
 */
class SameOrigin
{
    public function handle(Request $request, Closure $next)
    {
        if (!in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }
        if ($this->isSignedPaymentCallback($request)) {
            return $next($request);
        }

        $source = trim((string)$request->header('origin'));
        if ($source === '') {
            $source = trim((string)$request->header('referer'));
        }

        $requestScheme = $request->isSsl() ? 'https' : 'http';
        if ($source === '' || !$this->matchesRequestHost($source, (string)$request->host(), $requestScheme)) {
            return json(message('请求来源校验失败，请刷新页面后重试', false), 403);
        }

        return $next($request);
    }

    /**
     * Payment providers cannot send browser Origin/Referer headers. These
     * narrowly scoped actions authenticate with the provider signature and
     * amount/order checks instead; every other payment POST remains same-origin.
     */
    private function isSignedPaymentCallback(Request $request): bool
    {
        $controller = strtolower($request->controller());
        $action = strtolower($request->action());
        $allowed = [
            'alipay' => ['alipaynotify', 'kayixinnotify', 'kayixinreturn'],
            'codepay' => ['notify'],
            'qqpay' => ['notify'],
            'wxpay' => ['notify'],
            'epay' => ['notify'],
        ];
        return isset($allowed[$controller]) && in_array($action, $allowed[$controller], true);
    }

    private function matchesRequestHost(string $source, string $requestHost, string $requestScheme = 'https'): bool
    {
        $sourceParts = parse_url($source);
        $requestScheme = strtolower($requestScheme) === 'https' ? 'https' : 'http';
        $targetParts = parse_url($requestScheme . '://' . $requestHost);
        if (!is_array($sourceParts) || !is_array($targetParts)) {
            return false;
        }

        $sourceScheme = strtolower((string)($sourceParts['scheme'] ?? ''));
        if ($sourceScheme === '' || !hash_equals($requestScheme, $sourceScheme)) {
            return false;
        }

        $sourceHost = strtolower(rtrim((string)($sourceParts['host'] ?? ''), '.'));
        $targetHost = strtolower(rtrim((string)($targetParts['host'] ?? ''), '.'));
        if ($sourceHost === '' || $targetHost === '' || !hash_equals($targetHost, $sourceHost)) {
            return false;
        }

        $defaultPort = $requestScheme === 'https' ? 443 : 80;
        $sourcePort = isset($sourceParts['port']) ? (int)$sourceParts['port'] : $defaultPort;
        $targetPort = isset($targetParts['port']) ? (int)$targetParts['port'] : $defaultPort;
        return $sourcePort === $targetPort;
    }
}
