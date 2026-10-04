<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

/**
 * Non-destructive bridge from a copied 4.2.9 schema to the pre-v2 schema.
 *
 * This command must only be run against the new database. It creates missing
 * tables and columns; it never changes existing values or removes anything.
 */
class LegacyBridge extends Command
{
    private const OLD_DATABASE = 'admin_idaily_top';
    private const DEFAULT_TARGET = 'admin_noteweb_to';

    private const TABLES = [
        'SF_feedback',
        'SF_feedback_reply',
        'SF_notification',
        'SF_point_log',
        'SF_wechat_mp_login',
        'SF_point_product',
        'SF_point_exchange_record',
        'SF_point_product_reward',
        'SF_balance_log',
        'SF_withdraw',
        'SF_checkin_record',
        'SF_carousel',
        'SF_user_notice',
        'SF_discount_code',
        'SF_rebate_record',
        'SF_plugin',
        'SF_plugin_versions',
        'SF_plugin_resources',
        'SF_plugin_order',
        'SF_plugin_comment',
        'SF_plugin_rating',
        'SF_plugin_download',
        'SF_plugin_download_token',
        'SF_plugin_purchase',
        'SF_loginlog',
    ];

    private const COLUMNS = [
        'SF_admin' => [
            'wechat_openid' => "varchar(64) NOT NULL DEFAULT '' COMMENT '微信公众号openid'",
        ],
        'SF_user' => [
            'wechat_openid' => "varchar(64) NOT NULL DEFAULT '' COMMENT '微信公众号openid'",
            'created_at' => "datetime DEFAULT NULL COMMENT '注册时间'",
            'is_developer' => "tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否为开发者'",
            'withdrawable_balance' => "decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '可提现收益余额，为总余额的子集'",
        ],
        'SF_order' => [
            'discount_code' => "varchar(32) DEFAULT NULL COMMENT '使用的折扣码'",
        ],
        'SF_pay' => [
            'discount_code' => "varchar(32) DEFAULT NULL COMMENT '使用的折扣码'",
        ],
        'SF_power_price' => [
            'rebate_enabled' => "tinyint(1) NOT NULL DEFAULT 0 COMMENT '启用返利 0=否 1=是'",
            'rebate_rate' => "decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '返利比例(%)'",
            'discount_code_enabled' => "tinyint(1) NOT NULL DEFAULT 0 COMMENT '启用折扣码功能 0=否 1=是'",
        ],
    ];

    protected function configure()
    {
        $this->setName('sf:legacy-bridge')
            ->addOption('dry-run', null, Option::VALUE_NONE, '只检查并报告，不写入')
            ->addOption('apply', null, Option::VALUE_NONE, '执行仅新增表和字段的迁移')
            ->addOption(
                'expected-database',
                null,
                Option::VALUE_REQUIRED,
                '必须与当前连接的数据库名完全一致',
                self::DEFAULT_TARGET
            )
            ->setDescription('将复制后的 4.2.9 数据库补齐到新版迁移前结构（仅限新数据库）');
    }

    protected function execute(Input $input, Output $output)
    {
        $apply = (bool) $input->getOption('apply');
        $dryRun = (bool) $input->getOption('dry-run');
        if ($apply && $dryRun) {
            $output->error('--apply 与 --dry-run 不能同时使用。');
            return 2;
        }

        $database = $this->currentDatabase();
        $expected = trim((string) $input->getOption('expected-database'));
        if ($database === '' || $expected === '') {
            $output->error('无法确认当前数据库名，已拒绝执行。');
            return 3;
        }
        if (strcasecmp($database, self::OLD_DATABASE) === 0) {
            $output->error('当前连接指向旧生产数据库，已强制拒绝执行。');
            return 4;
        }
        if (!hash_equals($expected, $database)) {
            $output->error(sprintf('数据库保护检查失败：当前为 %s，期望为 %s。', $database, $expected));
            return 5;
        }

        $missingTables = array_values(array_filter(self::TABLES, function ($table) {
            return !$this->tableExists($table);
        }));
        $missingColumns = [];
        foreach (self::COLUMNS as $table => $columns) {
            if (!$this->tableExists($table)) {
                $output->error(sprintf('基础表 %s 不存在；请先导入旧数据库副本。', $table));
                return 6;
            }
            foreach ($columns as $column => $definition) {
                if (!$this->columnExists($table, $column)) {
                    $missingColumns[$table][$column] = $definition;
                }
            }
        }

        $output->writeln(sprintf('已确认目标数据库：<info>%s</info>', $database));
        $output->writeln(sprintf('待新增表：%d；待新增字段：%d。', count($missingTables), $this->columnCount($missingColumns)));
        foreach ($missingTables as $table) {
            $output->writeln('  + table ' . $table);
        }
        foreach ($missingColumns as $table => $columns) {
            foreach (array_keys($columns) as $column) {
                $output->writeln(sprintf('  + column %s.%s', $table, $column));
            }
        }

        if (!$apply) {
            $output->writeln('<comment>只读检查完成；未写入。正式执行需显式添加 --apply。</comment>');
            return 0;
        }

        if (!$this->acquireLock($database)) {
            $output->error('无法取得迁移互斥锁，已拒绝执行。');
            return 7;
        }

        try {
            $definitions = $this->loadTableDefinitions();
            foreach ($missingTables as $table) {
                if (!isset($definitions[$table])) {
                    throw new \RuntimeException('安全建表定义缺失：' . $table);
                }
                Db::execute($definitions[$table]);
                $output->writeln('<info>已创建表：</info>' . $table);
            }

            foreach ($missingColumns as $table => $columns) {
                foreach ($columns as $column => $definition) {
                    Db::execute(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition));
                    $output->writeln(sprintf('<info>已新增字段：</info>%s.%s', $table, $column));
                }
            }

            foreach (self::TABLES as $table) {
                if (!$this->tableExists($table)) {
                    throw new \RuntimeException('迁移后仍缺少表：' . $table);
                }
            }
            foreach (self::COLUMNS as $table => $columns) {
                foreach (array_keys($columns) as $column) {
                    if (!$this->columnExists($table, $column)) {
                        throw new \RuntimeException(sprintf('迁移后仍缺少字段：%s.%s', $table, $column));
                    }
                }
            }
        } catch (\Throwable $e) {
            $output->error('迁移桥执行失败：' . $e->getMessage());
            return 8;
        } finally {
            $this->releaseLock($database);
        }

        $output->writeln('<info>迁移桥执行并复核完成；未删除或更新任何已有数据。</info>');
        return 0;
    }

    private function currentDatabase(): string
    {
        $rows = Db::query('SELECT DATABASE() AS db');
        return isset($rows[0]['db']) ? (string) $rows[0]['db'] : '';
    }

    private function tableExists(string $table): bool
    {
        $rows = Db::query(
            'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );
        return (int) ($rows[0]['c'] ?? 0) === 1;
    }

    private function columnExists(string $table, string $column): bool
    {
        $rows = Db::query(
            'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
        return (int) ($rows[0]['c'] ?? 0) === 1;
    }

    private function loadTableDefinitions(): array
    {
        $path = root_path() . 'database/migrations/20260816_legacy_429_bridge_tables.sql';
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new \RuntimeException('无法读取安全建表文件。');
        }
        if (preg_match('/\b(?:DROP|TRUNCATE|DELETE|UPDATE|INSERT|REPLACE|ALTER|CALL|PROCEDURE)\b/i', $sql)) {
            throw new \RuntimeException('安全建表文件含禁止语句。');
        }

        preg_match_all(
            '/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`([^`]+)`\s*\(.*?\)\s*ENGINE\s*=.*?;/is',
            $sql,
            $matches,
            PREG_SET_ORDER
        );
        $definitions = [];
        foreach ($matches as $match) {
            $table = $match[1];
            if (!in_array($table, self::TABLES, true) || isset($definitions[$table])) {
                throw new \RuntimeException('安全建表文件含非允许或重复表：' . $table);
            }
            $definitions[$table] = $match[0];
        }
        if (count($definitions) !== count(self::TABLES)) {
            throw new \RuntimeException('安全建表文件的允许表数量不完整。');
        }
        return $definitions;
    }

    private function acquireLock(string $database): bool
    {
        $rows = Db::query('SELECT GET_LOCK(?, 0) AS acquired', ['sf_legacy_bridge_' . $database]);
        return (int) ($rows[0]['acquired'] ?? 0) === 1;
    }

    private function releaseLock(string $database): void
    {
        try {
            Db::query('SELECT RELEASE_LOCK(?)', ['sf_legacy_bridge_' . $database]);
        } catch (\Throwable $e) {
            // Connection teardown also releases named locks.
        }
    }

    private function columnCount(array $columns): int
    {
        return array_sum(array_map('count', $columns));
    }
}
