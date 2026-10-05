<?php

namespace app\command;

use app\common\service\CryptoService;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

/**
 * 生成 Ed25519 密钥对
 *
 *   php think qh:keygen license    授权响应签名密钥
 *   php think qh:keygen release    发布物（更新包 / 补丁）签名密钥
 *
 * 输出只打印到终端，不写入任何文件 —— 私钥的落地位置由你决定。
 *
 * ⚠ 私钥安全：
 *   - release 私钥建议离线保存（硬件令牌 / 离线机），签名在隔离环境完成，
 *     CI 只接触签名产物，绝不接触私钥
 *   - license 私钥放在授权服务器的环境变量中，文件权限 0400，非 Web 目录
 *   - 两把私钥必须分离：授权服务器被攻破不应导致可以伪造更新包
 *
 * @since 2026-08-16 P3
 */
class KeyGen extends Command
{
    protected function configure()
    {
        $this->setName('qh:keygen')
            ->addArgument('purpose', Argument::OPTIONAL, 'license 或 release', 'license')
            ->setDescription('生成 Ed25519 签名密钥对');
    }

    protected function execute(Input $input, Output $output)
    {
        $purpose = (string)$input->getArgument('purpose');
        if (!in_array($purpose, [CryptoService::PURPOSE_LICENSE, CryptoService::PURPOSE_RELEASE], true)) {
            $output->error('purpose 只能是 license 或 release');
            return 1;
        }

        try {
            $pair = CryptoService::generateKeypair($purpose);
        } catch (\Throwable $e) {
            $output->error('生成失败：' . $e->getMessage());
            return 1;
        }

        $output->writeln('');
        $output->writeln('<info>========== 已生成 ' . $purpose . ' 密钥对 ==========</info>');
        $output->writeln('');
        $output->writeln('把以下内容写入授权服务器的 .env（不要提交到 Git）：');
        $output->writeln('');
        $output->writeln("{$purpose}_sign_key_id     = {$pair['key_id']}");
        $output->writeln("{$purpose}_sign_secret_key = {$pair['secret']}");
        $output->writeln("{$purpose}_sign_public_key = {$pair['public']}");
        $output->writeln('');
        $output->writeln('<comment>公钥（内置到主题 model/zaesky_license/Core.php 的 publicKeys）：</comment>');
        $output->writeln("    '{$pair['key_id']}' => '{$pair['public']}',");
        $output->writeln('');

        if ($purpose === CryptoService::PURPOSE_RELEASE) {
            $output->writeln('<comment>发布私钥请勿放在授权服务器上。</comment>');
            $output->writeln('<comment>建议流程：离线机执行 php think qh:sign 生成签名，只把签名结果导入线上。</comment>');
        }

        $output->writeln('');
        $output->writeln('<comment>轮换提示：旧公钥需在主题中与新公钥并存至少一个升级周期，</comment>');
        $output->writeln('<comment>否则仍在旧版本上的客户将无法验签、无法升级到含新公钥的版本。</comment>');
        $output->writeln('');

        return 0;
    }
}
