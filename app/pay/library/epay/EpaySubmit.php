<?php

namespace app\pay\library\epay;

/* *
 * 类名：EpaySubmit
 * 功能：易支付接口请求提交类
 * 详细：构造易支付接口表单HTML文本，获取远程HTTP数据
 * @author：陌上花开
 * @datetime：2022.06.25
 */
class EpaySubmit
{
    public $epay_config;

    function __construct($epay_config)
    {
        $this->epay_config = $epay_config;
        $this->epay_gateway_new = $this->epay_config['apiurl'] . 'submit.php';
        $this->epay_qrcode = $this->epay_config['apiurl'] . 'qrcode.php';
        $this->epayCommon = new EpayCommon();
    }

    function epaySubmit($epay_config)
    {
        $this->__construct($epay_config);
    }

    /**
     * 生成签名结果
     * @param $para_sort 已排序要签名的数组
     * return 签名结果字符串
     */
    function buildRequestMysign($para_sort)
    {
        //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $prestr = $this->epayCommon->createLinkstring($para_sort);

        $mysign = $this->epayCommon->md5Sign($prestr, $this->epay_config['key']);

        return $mysign;
    }

    /**
     * 生成要请求给支付宝的参数数组
     * @param $para_temp 请求前的参数数组
     * @return 要请求的参数数组
     */
    function buildRequestPara($para_temp)
    {
        //除去待签名参数数组中的空值和签名参数
        $para_filter = $this->epayCommon->paraFilter($para_temp);

        //对待签名参数数组排序
        $para_sort = $this->epayCommon->argSort($para_filter);

        //生成签名结果
        $mysign = $this->buildRequestMysign($para_sort);

        //签名结果与签名方式加入请求提交参数组中
        $para_sort['sign'] = $mysign;
        $para_sort['sign_type'] = strtoupper(trim($this->epay_config['sign_type']));

        return $para_sort;
    }

    /**
     * 生成要请求给支付宝的参数数组
     * @param $para_temp 请求前的参数数组
     * @return 要请求的参数数组字符串
     */
    function buildRequestParaToString($para_temp)
    {
        //待请求参数数组
        $para = $this->buildRequestPara($para_temp);

        //把参数组中所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串，并对字符串做urlencode编码
        $request_data = $this->epayCommon->createLinkstringUrlencode($para);

        return $request_data;
    }

    /**
     * 建立请求，以表单HTML形式构造（默认）
     * @param $para_temp 请求参数数组
     * @param $method 提交方式。两个值可选：post、get
     * @param $button_name 确认按钮显示文字
     * @return 提交表单HTML文本
     */
    function buildRequestForm($para_temp, $method = 'POST', $button_name = '正在跳转')
    {
        //待请求参数数组
        $para = $this->buildRequestPara($para_temp);

        $sHtml = "<div class=\"page-loading\"><div class=\"signal-loader\"><span></span><span></span><span></span><span></span></div></div><form id='epaysubmit' name='epaysubmit' action='" . $this->epay_gateway_new . "' method='" . $method . "'>";
        foreach ($para as $key => $val) {
            $sHtml .= "<input type='hidden' name='" . $key . "' value='" . $val . "'/>";
        }

        //submit按钮控件请不要含有name属性
        $sHtml = $sHtml . "<input type='submit' value='" . $button_name . "'></form>";

        $sHtml = $sHtml . "<script>document.forms['epaysubmit'].submit();</script>";

        return $sHtml;
    }

    /**
     * 建立请求，以跳转链接
     * @param $para_temp 请求参数数组
     * @return 跳转链接
     */
    function buildRequestUrl($para_temp)
    {
        //待请求参数数组
        $para = $this->buildRequestPara($para_temp);

        //把参数组中所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串，并对字符串做urlencode编码
        $request_data = $this->epayCommon->createLinkstringUrlencode($para);

        $url = $this->epay_gateway_new . '?' . $request_data;

        return $url;
    }

    /**
     * 获取付款二维码接口
     */
    function getqrcode($para_temp)
    {
        $url = $this->epay_gateway_api;
        $post = $this->buildRequestParaToString($para_temp);
        $response = $this->epayCommon->getHttpResponsePOST($url, null, $post);
        return json_decode($response, true);
    }
}

