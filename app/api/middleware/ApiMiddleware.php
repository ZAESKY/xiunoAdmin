<?php
namespace app\api\middleware;

use Closure;
use think\Request;

class ApiMiddleware
{
    protected $header = [
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Max-Age'           => 1800,
        'Access-Control-Allow-Methods'     => 'GET, POST, PATCH, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers'     => 'Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-CSRF-TOKEN, X-Requested-With, X-Token',
    ];

    public function handle(Request $request, Closure $next)
    {
        $origin = rtrim(trim((string)$request->header('origin')), '/');
        if ($origin === '') {
            return $next($request);
        }

        $allowedOrigins = (array)config('app.cors_origins', []);
        // Request::domain(true) omits non-default ports in some ThinkPHP 6.1
        // server modes. Build the exact browser origin from scheme + Host.
        $sameOrigin = ($request->isSsl() ? 'https://' : 'http://') . (string)$request->host();
        $allowedOrigins[] = $sameOrigin;
        $allowed = false;
        foreach ($allowedOrigins as $candidate) {
            $candidate = rtrim(trim((string)$candidate), '/');
            if ($candidate !== '' && hash_equals(strtolower($candidate), strtolower($origin))) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            return json(message(t('api.origin_forbidden'), false), 403);
        }

        $headers = $this->header;
        $headers['Access-Control-Allow-Origin'] = $origin;
        $headers['Vary'] = 'Origin';
        if (strtoupper($request->method()) === 'OPTIONS') {
            return response('', 204)->header($headers);
        }

        return $next($request)->header($headers);
    }
}
