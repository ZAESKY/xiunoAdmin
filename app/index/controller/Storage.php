<?php
declare(strict_types=1);

namespace app\index\controller;

use app\common\controller\CommonBase;
use app\common\service\PluginStorageService;

/**
 * Stable media gateway for private OSS objects.
 *
 * Database and rich-text records keep this application URL instead of an OSS
 * URL. Every request is authenticated with a server HMAC and redirected to a
 * short-lived read-only OSS signature; package objects are deliberately not
 * accepted here and remain behind their download authorization flow.
 */
class Storage extends CommonBase
{
    public function media()
    {
        if (!$this->request->isGet() && !$this->request->isHead()) {
            return response('', 405);
        }

        $objectKey = trim((string)$this->request->get('key', ''));
        $token = trim((string)$this->request->get('token', ''));
        try {
            $url = (new PluginStorageService())->getMediaRedirectUrl($objectKey, $token, 60);
            return redirect($url, 302)->header([
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'Referrer-Policy' => 'no-referrer',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Throwable $e) {
            return response('', 404)->header([
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }
    }
}
