<?php
/**
 * API 应用路由（v2 授权与更新）
 *
 * 规范路径（相对本应用入口 public/api.php）：
 *     /api.php/v2/license/activate
 *     /api.php/v2/update/check
 *     ...
 *
 * ⚠ 签名规范中的「请求路径」一律取 **应用相对路径**（即 request()->pathinfo() 前加 /），
 *   例如 /v2/license/status。这样无论前端是否配置了 /api/v2/... 重写，
 *   客户端与服务端计算出的规范串都完全一致。
 *
 * 可选的 Nginx 美化重写（不影响签名）：
 *     location /api/ { rewrite ^/api/(.*)$ /api.php/$1 last; }
 *
 * v1 接口（/api.php/Auth/*、/api.php/Download/*）继续由自动路由提供，本文件不影响它们。
 *
 * @since 2026-08-16 P2
 */

use think\facade\Route;

Route::group('v2', function () {

    // ---------- 授权 ----------
    Route::group('license', function () {
        // 无 HMAC（此时客户端尚无 license_secret），靠授权码 + 域名归属证明 + 频率限制
        Route::post('activate', 'LicenseV2/activate');
        Route::post('restore',  'LicenseV2/restore');
        Route::post('redeem',   'LicenseV2/redeem');
        Route::post('trial',    'LicenseV2/trial');

        // HMAC 认证
        Route::post('status',   'LicenseV2/status');
        Route::post('rebind',   'LicenseV2/rebind');
        Route::post('channel',  'LicenseV2/channel');

    });

    // ---------- 更新 ----------
    Route::group('update', function () {
        Route::post('check',  'UpdateV2/check');
        Route::post('stable', 'UpdateV2/stable');
        Route::post('ticket', 'UpdateV2/ticket');
        Route::get('download', 'UpdateV2/download');
        Route::post('report', 'UpdateV2/report');
    });

    // 旧独立补丁通道已并入主题签名完整包。保留稳定的 410 墓碑响应，避免
    // 老客户端被框架兜底页误导为可继续使用的接口。
    Route::group('patch', function () {
        Route::post('check', 'UpdateV2/retiredPatch');
        Route::post('ticket', 'UpdateV2/retiredPatch');
        Route::get('download', 'UpdateV2/retiredPatch');
        Route::post('report', 'UpdateV2/retiredPatch');
    });

});
