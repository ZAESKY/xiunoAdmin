<?php

namespace app\api\controller;

use app\common\controller\ApiBackend;

class WechatMp extends ApiBackend
{
    /**
     * Keep the retired callback route stable for previously configured WeChat servers.
     * POST requests must still acknowledge delivery so WeChat does not retry them.
     */
    public function callback()
    {
        if (!IS_GET) {
            return 'success';
        }

        return response(t('wechat_mp.disabled'), 410);
    }

    public function qrcode()
    {
        return $this->disabledResponse();
    }

    public function status()
    {
        return $this->disabledResponse();
    }

    public function chooseUser()
    {
        return $this->disabledResponse();
    }

    private function disabledResponse()
    {
        return json(message(t('wechat_mp.disabled'), false), 410);
    }
}
