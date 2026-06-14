<?php
namespace app\pay\library\qqpay;
/**
 * QpayNotify.php 业务调用方可做二次封装
 * Created by HelloWorld
 * vers: v1.0.0
 * User: Tencent.com
 */

class QpayNotify{
    private $params;
	private $sign;

	function getParams() {
		$post_data = file_get_contents("php://input");
		$params =  QpayMchUtil::xmlToArray($post_data);
		$this->params = is_array($params) ? $params : [];
		$this->sign = isset($params['sign']) ? $params['sign'] : '';
		return $this->params;
	}

	function verifySign() {
		if (empty($this->params) || empty($this->sign)) return false;
		$sign = QpayMchUtil::getSign($this->params);
		return $sign == $this->sign;
	}

}