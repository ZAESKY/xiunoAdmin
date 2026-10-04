<?php

namespace app\pay\library\wxpay;

use app\pay\service\CommonService;
use think\facade\Db;
use think\facade\Log;

class PayNotifyCallBack extends WxPayNotify
{
    private $service;

    public function __construct()
    {
        $this->service = new CommonService();
    }
    //查询订单
    public function Queryorder($transaction_id)
    {
        $input = new WxPayOrderQuery();
        $input->SetTransaction_id($transaction_id);
        $result = WxPayApi::orderQuery($input);
        //Log::DEBUG("query:" . json_encode($result));
        if(is_array($result) && array_key_exists("return_code", $result)
            && array_key_exists("result_code", $result)
            && $result["return_code"] == "SUCCESS"
            && $result["result_code"] == "SUCCESS")
        {
            return $result;
        }
        return null;
    }

    //重写回调处理函数
    public function NotifyProcess($data, &$msg)
    {
        //file_put_contents('log.txt',"call back:" . json_encode($data));
        $notfiyOutput = array();

        if(!is_array($data) || !array_key_exists("transaction_id", $data)){
            $msg = "输入参数不正确";
            return false;
        }
        //查询订单，判断订单真实性
        $queryResult = $this->Queryorder($data["transaction_id"]);
        if (!$queryResult) {
            $msg = "订单查询失败";
            return false;
        }
        if($data['return_code'] == 'SUCCESS'){
            if($data['result_code'] == 'SUCCESS'){
                $out_trade_no = $data['out_trade_no'];
                $srow = Db::name('pay')->where('trade_no', $out_trade_no)->find();
                if (!$srow || !isset($data['total_fee'])
                    || sf_money_to_cents($srow['money']) !== (int)$data['total_fee']
                    || (string)($queryResult['out_trade_no'] ?? '') !== (string)$out_trade_no
                    || (string)($queryResult['transaction_id'] ?? '') !== (string)$data['transaction_id']
                    || (int)($queryResult['total_fee'] ?? -1) !== (int)$data['total_fee']) {
                    $msg = '订单金额校验失败';
                    return false;
                }
                if (!$this->completeOrder($srow, (string)$data['transaction_id'])) {
                    $msg = '订单入账失败';
                    return false;
                }
                $msg = 'OK';
                return true;
            }else{
                $msg='['.$data['err_code'].']'.$data['err_code_des'];
                return false;
            }
        }else{
            $msg='['.$data['return_code'].']'.$data['return_msg'];
            return false;
        }
        return true;
    }

    private function completeOrder(array $row, string $apiTradeNo): bool
    {
        try {
            return (bool)Db::transaction(function () use ($row, $apiTradeNo) {
                $current = Db::name('pay')->where('trade_no', $row['trade_no'])->lock(true)->find();
                if (!$current) {
                    return false;
                }
                if ((int)$current['status'] >= 1) {
                    return true;
                }
                Db::name('pay')->where('trade_no', $current['trade_no'])->update([
                    'endtime' => datetime(),
                    'api_trade_no' => $apiTradeNo,
                ]);
                if ($this->service->processOrder($current) !== true) {
                    throw new \RuntimeException('微信支付订单入账失败');
                }
                return Db::name('pay')
                    ->where('trade_no', $current['trade_no'])
                    ->where('status', 0)
                    ->update(['status' => 1]) === 1;
            });
        } catch (\Throwable $e) {
            Log::error('微信支付订单入账失败', ['order' => (string)($row['trade_no'] ?? ''), 'error' => $e->getMessage()]);
            return false;
        }
    }
}
