<?php

namespace app\command;

use app\common\service\PluginPackageUploadService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class PluginUploadCleanup extends Command
{
    protected function configure()
    {
        $this->setName('qh:plugin-upload-cleanup')
            ->addOption('limit', null, Option::VALUE_REQUIRED, '单次最多清理数量', '500')
            ->setDescription('清理过期且从未提交发布的插件压缩包');
    }

    protected function execute(Input $input, Output $output)
    {
        $limit = max(1, min(2000, intval($input->getOption('limit'))));
        try {
            $result = PluginPackageUploadService::cleanupExpired($limit);
        } catch (\Throwable $e) {
            $output->error('插件待提交包清理失败：' . $e->getMessage());
            return 1;
        }
        $output->writeln(sprintf(
            '<info>清理完成：扫描 %d 个，删除 %d 个，待重试 %d 个。</info>',
            intval($result['selected'] ?? 0),
            intval($result['cleaned'] ?? 0),
            intval($result['failed'] ?? 0)
        ));
        return empty($result['failed']) ? 0 : 2;
    }
}
