<?php

namespace app\command;

use app\common\service\RebateSettlementService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class RebateSettle extends Command
{
    protected function configure()
    {
        $this->setName('sf:rebate-settle')
            ->addOption('limit', null, Option::VALUE_REQUIRED, '单次最多结算条数', '500')
            ->setDescription('结算已达到冻结期限的返利记录');
    }

    protected function execute(Input $input, Output $output)
    {
        $limit = max(1, min(2000, intval($input->getOption('limit'))));
        try {
            $result = RebateSettlementService::settleAllMatured($limit);
        } catch (\Throwable $e) {
            $output->error('返利结算失败：' . $e->getMessage());
            return 1;
        }

        $output->writeln(sprintf(
            '<info>结算完成：成功 %d 条，金额 %s 元；风控拒绝 %d 条。</info>',
            intval($result['settled'] ?? 0),
            sf_money_format($result['amount'] ?? 0),
            intval($result['rejected'] ?? 0)
        ));
        return 0;
    }
}
