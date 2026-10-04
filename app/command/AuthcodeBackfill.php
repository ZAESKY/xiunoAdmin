<?php

namespace app\command;

use app\common\service\AuthcodeService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

/**
 * P1 回填：为存量 SF_auth 记录计算 authcode_hash
 *
 *   php think sf:authcode-backfill --dry-run    先看影响面
 *   php think sf:authcode-backfill              实际回填
 *
 * 安全性：
 *   - 只写 authcode_hash / authcode_last4 / pepper_version 三列
 *   - 不修改、不清空 authcode 明文列 —— v1 接口继续正常工作
 *   - 分批处理，可随时中断后重跑（幂等）
 *
 * @since 2026-08-16 P1
 */
class AuthcodeBackfill extends Command
{
    protected function configure()
    {
        $this->setName('sf:authcode-backfill')
            ->addOption('dry-run', null, Option::VALUE_NONE, '只统计不写入')
            ->addOption('batch', null, Option::VALUE_REQUIRED, '每批条数', '500')
            ->setDescription('为存量授权记录回填授权码哈希（P1）');
    }

    protected function execute(Input $input, Output $output)
    {
        $dry = (bool)$input->getOption('dry-run');
        $batch = max(50, (int)$input->getOption('batch'));

        // 前置检查：pepper 必须已配置，否则回填出来的哈希在配置后会全部失效
        if (trim((string)env('security_pepper', '')) === '') {
            $output->warning('未配置 security_pepper，将回退使用内置常量盐值。');
            $output->warning('生产环境请先在 .env 中设置 security_pepper 再回填，否则后续配置 pepper 时需要重新回填。');
            $output->writeln('');
        }

        try {
            $total = Db::name('auth')
                ->where('authcode', '<>', '')
                ->where('authcode_hash', '')
                ->count();
        } catch (\Throwable $e) {
            $output->error('查询失败，请确认已执行 20260816_p1_p3_license_v2.sql：' . $e->getMessage());
            return 1;
        }

        $output->writeln('待回填记录数：' . $total);
        if ($total === 0) {
            $output->writeln('<info>无需回填。</info>');
            return 0;
        }
        if ($dry) {
            $sample = Db::name('auth')
                ->where('authcode', '<>', '')
                ->where('authcode_hash', '')
                ->field('id,authcode')
                ->limit(3)
                ->select()
                ->toArray();
            $output->writeln('<comment>--dry-run，未写入。样例（授权码已脱敏）：</comment>');
            foreach ($sample as $r) {
                $output->writeln(sprintf(
                    '  id=%d  %s  ->  %s',
                    $r['id'],
                    AuthcodeService::mask((string)$r['authcode']),
                    substr(AuthcodeService::hash((string)$r['authcode']), 0, 16) . '...'
                ));
            }
            return 0;
        }

        $done = 0;
        $failed = 0;

        while (true) {
            $rows = Db::name('auth')
                ->where('authcode', '<>', '')
                ->where('authcode_hash', '')
                ->field('id,authcode')
                ->limit($batch)
                ->select()
                ->toArray();

            if (!$rows) {
                break;
            }

            foreach ($rows as $r) {
                $code = (string)$r['authcode'];
                try {
                    Db::name('auth')->where('id', (int)$r['id'])->update([
                        'authcode_hash'  => AuthcodeService::hash($code),
                        'authcode_last4' => AuthcodeService::last4($code),
                        'pepper_version' => AuthcodeService::PEPPER_VERSION,
                        // 存量码全部由旧的可预测算法生成，一律标记应换发
                        'must_rotate'    => 1,
                    ]);
                    $done++;
                } catch (\Throwable $e) {
                    $failed++;
                    $output->warning('id=' . $r['id'] . ' 回填失败：' . $e->getMessage());
                }
            }

            $output->writeln(sprintf('  进度 %d / %d', $done, $total));

            // 防止极端情况下的死循环
            if ($done + $failed >= $total + $batch) {
                break;
            }
        }

        $output->writeln('');
        $output->writeln(sprintf('<info>回填完成：成功 %d，失败 %d</info>', $done, $failed));
        $output->writeln('<comment>明文列未改动，v1 接口不受影响。</comment>');
        $output->writeln('<comment>全部客户迁移到 v2 后，再执行明文清空语句（见迁移脚本末尾说明）。</comment>');

        return $failed > 0 ? 1 : 0;
    }
}
