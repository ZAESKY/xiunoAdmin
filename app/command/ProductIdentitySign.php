<?php

namespace app\command;

use app\common\service\ProductIdentityService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/** 生成可随客户端公开分发的签名产品身份文件。 */
class ProductIdentitySign extends Command
{
    protected function configure()
    {
        $this->setName('qh:product-sign')
            ->addOption('app-id', null, Option::VALUE_REQUIRED, '授权系统应用 ID')
            ->addOption('product', null, Option::VALUE_REQUIRED, 'v2 产品标识')
            ->addOption('aliases', null, Option::VALUE_REQUIRED, '逗号分隔的历史产品标识（凭据平滑迁移用）', '')
            ->addOption('output', null, Option::VALUE_REQUIRED, '输出 JSON 文件的绝对路径')
            ->setDescription('签发稳定应用 ID 与可变产品标识的客户端身份文件');
    }

    protected function execute(Input $input, Output $output)
    {
        $appId = (int)$input->getOption('app-id');
        $productId = trim((string)$input->getOption('product'));
        $target = (string)$input->getOption('output');
        if ($appId <= 0 || $productId === '' || $target === '') {
            $output->error('必须提供 --app-id、--product 和 --output');
            return 1;
        }

        $normalized = str_replace('\\', '/', $target);
        $directory = dirname($target);
        $realDirectory = realpath($directory);
        if (($normalized[0] !== '/' && !preg_match('#^[A-Za-z]:/#', $normalized))
            || $realDirectory === false || is_link($directory) || is_link($target)
            || strtolower(pathinfo($target, PATHINFO_EXTENSION)) !== 'json') {
            $output->error('输出路径必须是已存在安全目录中的绝对 JSON 路径');
            return 1;
        }

        $aliases = array_filter(array_map('trim', explode(',', (string)$input->getOption('aliases'))));
        $issued = ProductIdentityService::issue($appId, $productId, $aliases);
        if (empty($issued['ok'])) {
            $output->error((string)$issued['msg']);
            return 1;
        }
        $json = json_encode($issued['envelope'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($json)) {
            $output->error('产品身份 JSON 编码失败');
            return 1;
        }

        $temp = rtrim($realDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.'.basename($target).'.'.bin2hex(random_bytes(6)).'.tmp';
        if (file_put_contents($temp, $json."\n", LOCK_EX) === false) {
            $output->error('产品身份临时文件写入失败');
            return 1;
        }
        @chmod($temp, 0644);
        if (!@rename($temp, $target)) {
            @unlink($temp);
            $output->error('产品身份文件原子替换失败');
            return 1;
        }

        $output->writeln('<info>产品身份文件签发完成</info>');
        $output->writeln('  应用 ID   : '.$appId);
        $output->writeln('  产品标识  : '.$productId);
        $output->writeln('  key_id    : '.$issued['envelope']['key_id']);
        $output->writeln('  SHA256    : '.hash_file('sha256', $target));
        $output->writeln('  输出文件  : '.$target);
        return 0;
    }
}
