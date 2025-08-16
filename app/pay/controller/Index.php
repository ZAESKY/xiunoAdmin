<?php
declare (strict_types = 1);

namespace app\pay\controller;

use app\common\controller\PayBackend;
use app\pay\validate\Index as IndexValidate;
use think\exception\ValidateException;
use think\facade\View;
use think\facade\Db;
use app\pay\library\epay\EpaySubmit;
use app\pay\library\epay\KaYiXinSubmit;
use app\pay\library\alipay\model\builder\AlipayTradeWapPayContentBuilder;
use app\pay\library\alipay\model\builder\AlipayTradePagePayContentBuilder;
use app\pay\library\alipay\AlipayTradeService;
use think\facade\Config;

class Index extends PayBackend
{
    private $siteurl = SITE_URL;

    public function index()
    {
        $param = request()->param();
        $trade_no = !empty($param['trade_no'])?$param['trade_no']:null;
        View::assign('time', 5);
        View::assign('url', '/');
        if(empty($trade_no))return $this->render('public/error', ['msg' => '订单号不能为空！[errorCode:PayOrderIdEmpty]']);
        $row = Db::name('pay')->where('trade_no', $trade_no)->find();
        if(!$row)return $this->render('public/error', ['msg' => '不存在此订单！[errorCode:PayOrderIdError]']);
        View::assign([
            'row' => $row,
        ]);
        return $this->render();
    }

    public function submit(){
        $param = request()->param();
        $type = !empty($param['type'])?$param['type']:null;
        $orderid = !empty($param['orderid'])?$param['orderid']:null;
        View::assign('time', 5);
        View::assign('url', '/');
        if(empty($type))return $this->render('public/error', ['msg' => '支付类型不能为空！[errorCode:PayTypeEmpty]']);
        if(empty($orderid))return $this->render('public/error', ['msg' => '订单号不能为空！[errorCode:PayOrderIdEmpty]']);
        View::assign([
            'type' => $type,
            'orderid' =>$orderid
        ]);

        return $this->render();
    }

    public function check(){
        if(IS_POST) {
            $post = request()->post();
            $type = !empty($post['type']) ? $post['type'] : null;
            $orderid = !empty($post['orderid']) ? $post['orderid'] : null;
            try {
                validate(IndexValidate::class)->check($post);
            } catch (ValidateException $e) {
                // 验证失败 输出错误信息
                return message($e->getError(), false);
            }
            $row = Db::name('pay')->where('trade_no', $orderid)->find();
            if (!$row['trade_no']) return message('该订单号不存在，请返回来源地重新发起请求！', false);
            if ($row['money'] == '0' || !preg_match('/^[0-9.]+$/', $row['money'])) return message('订单金额不合法！', false);
            if ($row['status'] >= 1) return message('该订单已支付完成，请返回重新生成订单！', false);
            switch ($type) {
                case 'alipay':
                    switch (conf('alipay_api')) {
                        case 1://支付宝官方
                            if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
                                return $this->render('wxopen');
                            }
                            Db::name('pay')->where('trade_no', $orderid)->update(['type' => $type, 'channel' => 'alipay']);
                            $config = Config::get('payconfig.zfb.alipay_config');
                            //异步通知地址
                            $config['notify_url'] = SITE_URL . url('/AliPay/alipayNotify');
                            //同步通知地址
                            $config['return_url'] = SITE_URL . url('/AliPay/alipayReturn.php');
                            if (IS_MOBILE == true) {
                                //构造参数
                                $payRequestBuilder = new AlipayTradeWapPayContentBuilder();
                                $payRequestBuilder->setSubject($row['name']);
                                $payRequestBuilder->setTotalAmount($row['money']);
                                $payRequestBuilder->setOutTradeNo($orderid);
                                $aop = new AlipayTradeService($config);
                                return message('success', true, ['html' => $aop->wapPay($payRequestBuilder)]);
                            } else {
                                //构造参数
                                $payRequestBuilder = new AlipayTradePagePayContentBuilder();
                                $payRequestBuilder->setSubject($row['name']);
                                $payRequestBuilder->setTotalAmount($row['money']);
                                $payRequestBuilder->setOutTradeNo($orderid);

                                $aop = new AlipayTradeService($config);
                                return message('success', true, ['html' => $aop->pagePay($payRequestBuilder)]);
                            }
                        case 2://易支付
                            Db::name('pay')->where('trade_no', $orderid)->update(['type' => $type, 'channel' => 'zfb_epay']);
                            $payConfig = Config::get('payconfig.zfb.epay_config');
                            if(empty($payConfig['apiurl']))return message('获取支付配置失败！[errorCode:GetPayConfigApiUrlEmpty]', false);
                            if(empty($payConfig['partner']))return message('获取支付配置失败！[errorCode:GetPayConfigPartnerEmpty]', false);
                            if(empty($payConfig['key']))return message('获取支付配置失败！[errorCode:GetPayConfigKeyEmpty]', false);
                            $parameter = array(
                                "pid" => trim($payConfig['partner']),
                                "type" => $type,
                                "notify_url" => $this->siteurl . url('/EPay/notify'),
                                "return_url" => $this->siteurl . url('/EPay/return'),
                                "out_trade_no" => $orderid,
                                "name" => $row['name'],
                                "money" => $row['money'],
                                "sitename" => conf('title')
                            );
                            //建立请求
                            $epaySubmit = new EpaySubmit($payConfig);
                            if (is_https() && substr($payConfig['apiurl'], 0, 7) == 'http://') {
                                $jump_url = $epaySubmit->buildRequestUrl($parameter);
                                return message('success', true, ['url' => $jump_url]);
                            } else {
                                $html_text = $epaySubmit->buildRequestForm($parameter, 'POST', "正在跳转");
                                return message('success', true, ['html' => $html_text]);
                            }
                        case 3://当面付
                            Db::name('pay')->where('trade_no', $orderid)->update(['type' => $type, 'channel' => 'alipay']);
                            $jump_url = url('/AliPay') . '?trade_no=' . $orderid;
                            return message('success', true, ['url' => $jump_url]);
                        case 5://码支付
                            Db::name('pay')->where('trade_no', $orderid)->update(['type' => $type, 'channel' => 'codepay']);
                            $jump_url = url('/CodePay/index').'?type=' . $type . '&trade_no=' . $orderid;
                            return message('success', true, ['url' => $jump_url]);
                        case 7://卡易信
                            if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
                                return $this->render('wxopen');
                            }
                            Db::name('pay')->where('trade_no', $orderid)->update(['type' => $type, 'channel' => 'kayixin']);
                            $payConfig = Config::get('payconfig.zfb.kayixin_config');
                            if(empty($payConfig['partner']))return message('获取支付配置失败！[errorCode:GetPayConfigPartnerEmpty]', false);
                            if(empty($payConfig['key']))return message('获取支付配置失败！[errorCode:GetPayConfigKeyEmpty]', false);
                            if (IS_MOBILE == true) {
                                $alipay_service = "alipay.wap";
                            } else {
                                $alipay_service = "alipay.pc";
                            }
                            $parameter = array(
                                "service" => $alipay_service,
                                "partner" => trim($payConfig['partner']),
                                "notify_url" => $this->siteurl . url('/AliPay/kayixinNotify'),
                                "return_url" => $this->siteurl . url('/AliPay/kayixinReturn'),
                                "website_url" => DOMAIN,
                                "out_trade_no" => $orderid,
                                "subject" => $row['name'],
                                "body" => $row['name'],
                                "total_fee" => $row['money'],
                                "_input_charset" => strtolower('utf-8')
                            );
                            $alipaySubmit = new KaYiXinSubmit($payConfig);
                            $html_text = $alipaySubmit->buildRequestForm($parameter, "POST", "正在跳转");
                            return message('success', true, ['html' => $html_text]);
                        default:
                            return message('该支付方式已关闭！', false);
                    }
                case 'wxpay':
                    switch (conf('wxpay_api')) {
                        case 1:// 微信官方
                            Db::name('pay')->where('trade_no', $orderid)->update(['type' => $type, 'channel' => 'wxpay']);
                            if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
                                $jump_url = url('/WxPay/jsPay') . '?trade_no=' . $orderid . '&d=1';
                                return message('success', true, ['url' => $jump_url]);
                            } elseif (IS_MOBILE == true) {
                                $jump_url = url('/WxPay/wapPay') . '?trade_no=' . $orderid;
                                return message('success', true, ['url' => $jump_url]);
                            } else {
                                $jump_url = url('/WxPay/index') . '?trade_no=' . $orderid;
                                return message('success', true, ['url' => $jump_url]);
                            }
                        case 2://易支付
                            Db::name('pay')->where('trade_no', $orderid)->update(['type' => $type, 'channel' => 'wx_epay']);
                            $payConfig = Config::get('payconfig.wx.epay_config');
                            if(empty($payConfig['apiurl']))return message('获取支付配置失败！[errorCode:GetPayConfigApiUrlEmpty]', false);
                            if(empty($payConfig['partner']))return message('获取支付配置失败！[errorCode:GetPayConfigPartnerEmpty]', false);
                            if(empty($payConfig['key']))return message('获取支付配置失败！[errorCode:GetPayConfigKeyEmpty]', false);
                            $parameter = array(
                                "pid" => trim($payConfig['partner']),
                                "type" => $type,
                                "notify_url" => $this->siteurl . url('/EPay/notify'),
                                "return_url" => $this->siteurl . url('/EPay/return'),
                                "out_trade_no" => $orderid,
                                "name" => $row['name'],
                                "money" => $row['money'],
                                "sitename" => conf('title')
                            );
                            //建立请求
                            $epaySubmit = new EpaySubmit($payConfig);
                            if (is_https() && substr($payConfig['apiurl'], 0, 7) == 'http://') {
                                $jump_url = $epaySubmit->buildRequestUrl($parameter);
                                return message('success', true, ['url' => $jump_url]);
                            } else {
                                $html_text = $epaySubmit->buildRequestForm($parameter, 'POST', "正在跳转");
                                return message('success', true, ['html' => $html_text]);
                            }
                        case 5://码支付
                            Db::name('pay')->where('trade_no', $orderid)->update(['type' => $type, 'channel' => 'codepay']);
                            $jump_url = url('/CodePay/index').'?type=' . $type . '&trade_no=' . $orderid;
                            return message('success', true, ['url' => $jump_url]);
                        default:
                            return message('该支付方式已关闭！', false);
                    }
                case 'qqpay':
                    switch (conf('qqpay_api')) {
                        case 1://QQ官方
                            Db::name('pay')->where('trade_no', $orderid)->update(['type' => $type, 'channel' => 'qqpay']);
                            if (IS_MOBILE == true) {
                                $jump_url = url('/QqPay/wapPay') . '?trade_no=' . $orderid;
                                return message('success', true, ['url' => $jump_url]);
                            } else {
                                $jump_url = url('/QqPay/index') . '?trade_no=' . $orderid;
                                return message('success', true, ['url' => $jump_url]);
                            }
                        case 2://易支付
                            Db::name('pay')->where('trade_no', $orderid)->update(['type' => $type, 'channel' => 'qq_epay']);
                            $payConfig = Config::get('payconfig.qq.epay_config');
                            if(empty($payConfig['apiurl']))return message('获取支付配置失败！[errorCode:GetPayConfigApiUrlEmpty]', false);
                            if(empty($payConfig['partner']))return message('获取支付配置失败！[errorCode:GetPayConfigPartnerEmpty]', false);
                            if(empty($payConfig['key']))return message('获取支付配置失败！[errorCode:GetPayConfigKeyEmpty]', false);
                            $parameter = array(
                                "pid" => trim(Config::get('payconfig.qq.epay_config.partner')),
                                "type" => $type,
                                "notify_url" => $this->siteurl . url('/EPay/notify'),
                                "return_url" => $this->siteurl . url('/EPay/return'),
                                "out_trade_no" => $orderid,
                                "name" => $row['name'],
                                "money" => $row['money'],
                                "sitename" => conf('title')
                            );
                            //建立请求
                            $epaySubmit = new EpaySubmit($payConfig);
                            if (is_https() && substr(Config::get('payconfig.qq.epay_config.apiurl'), 0, 7) == 'http://') {
                                $jump_url = $epaySubmit->buildRequestUrl($parameter);
                                return message('success', true, ['url' => $jump_url]);
                            } else {
                                $html_text = $epaySubmit->buildRequestForm($parameter, 'POST', "正在跳转");
                                return message('success', true, ['html' => $html_text]);
                            }
                        case 5://码支付
                            Db::name('pay')->where('trade_no', $orderid)->update(['type' => $type, 'channel' => 'codepay']);
                            $jump_url = url('/CodePay/index').'?type=' . $type . '&trade_no=' . $orderid;
                            return message('success', true, ['url' => $jump_url]);
                        default:
                            return message('该支付方式已关闭！', false);
                    }
                default:
                    return message('支付类型错误！', false);
            }
        }
        return $this->render('/public/404.html');
    }

    public function getPayResult(){
        $post = request()->post();
        $trade_no = isset($post['trade_no'])?$post['trade_no']:null;
        $row = Db::name('pay')->where('trade_no', $trade_no)->find();
        if (!$row['trade_no']) return message('该订单号不存在，请返回来源地重新发起请求！', false);
        if($row['status'] >= 1){
            return message('该订单号不存在，请返回来源地重新发起请求！', true, ['backurl' => '/']);
        }else{
            return message('未付款！', false);
        }
    }
}
