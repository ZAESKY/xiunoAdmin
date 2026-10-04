<?php

namespace app\command;

use app\common\service\CryptoService;
use app\common\service\ManifestService;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

/**
 * 构建并签名发布物 / 补丁
 *
 * 更新包：
 *   php think sf:sign release /path/SF.zip \
 *       --product=zaesky_theme_light --build=260100 --edition=26.1.0 \
 *       --channel=stable --min-bbs=4.0.0 --max-bbs=4.0.3 --min-php=7.4 \
 *       --note="修复若干问题"
 *
 * 核心兼容补丁：
 *   php think sf:sign patch /path/php8-html-safe.zip \
 *       --product=zaesky_theme_light --patch-id=php8-html-safe --revision=1 --level=1 \
 *       --theme-build=260100 --theme-edition=26.1.0 \
 *       --title="PHP 8 HTML 净化兼容" --min-bbs=4.0.0 --max-bbs=4.0.3 --min-php=8.0
 *
 * --dry-run 只构建与验签，不落库、不复制文件。
 *
 * @since 2026-08-16 P3
 */
class Sign extends Command
{
    protected function configure()
    {
        $this->setName('sf:sign')
            ->addArgument('kind', Argument::REQUIRED, 'release 或 patch')
            ->addArgument('zip', Argument::REQUIRED, 'ZIP 包路径')
            ->addOption('product', null, Option::VALUE_REQUIRED, '产品标识（必须显式提供）', '')
            ->addOption('build', null, Option::VALUE_REQUIRED, 'build 号（release 必填）', '0')
            ->addOption('edition', null, Option::VALUE_REQUIRED, '展示版本号', '')
            ->addOption('channel', null, Option::VALUE_REQUIRED, 'stable/beta', 'stable')
            ->addOption('patch-id', null, Option::VALUE_REQUIRED, '补丁标识（patch 必填）', '')
            ->addOption('revision', null, Option::VALUE_REQUIRED, '补丁修订号', '1')
            ->addOption('level', null, Option::VALUE_REQUIRED, '1=overwrite 覆写 2=直写核心', '1')
            ->addOption('theme-build', null, Option::VALUE_REQUIRED, '补丁绑定的主题 build（patch 必填）', '0')
            ->addOption('theme-edition', null, Option::VALUE_REQUIRED, '补丁绑定的主题展示版本（patch 必填）', '')
            ->addOption('title', null, Option::VALUE_REQUIRED, '补丁标题', '')
            ->addOption('description', null, Option::VALUE_REQUIRED, '补丁说明', '')
            ->addOption('expect', null, Option::VALUE_REQUIRED, 'level2 原文哈希 JSON 文件路径', '')
            ->addOption('min-bbs', null, Option::VALUE_REQUIRED, '最低 Xiuno 版本', '')
            ->addOption('max-bbs', null, Option::VALUE_REQUIRED, '最高 Xiuno 版本', '')
            ->addOption('min-php', null, Option::VALUE_REQUIRED, '最低 PHP 版本', '')
            ->addOption('note', null, Option::VALUE_REQUIRED, '更新说明', '')
            ->addOption('security', null, Option::VALUE_NONE, '标记为安全更新')
            ->addOption('publish', null, Option::VALUE_NONE, '直接置为已发布（默认草稿）')
            ->addOption('dry-run', null, Option::VALUE_NONE, '只构建与验签，不落库')
            ->setDescription('构建并用 Ed25519 签名更新包 / 核心补丁');
    }

    protected function execute(Input $input, Output $output)
    {
        $kind = (string)$input->getArgument('kind');
        $zip  = (string)$input->getArgument('zip');
        $dry  = (bool)$input->getOption('dry-run');

        if (!in_array($kind, ['release', 'patch'], true)) {
            $output->error('kind 只能是 release 或 patch');
            return 1;
        }
        if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', (string)$input->getOption('product'))) {
            $output->error('必须通过 --product 显式提供合法产品标识');
            return 1;
        }
        if (!CryptoService::configured(CryptoService::PURPOSE_RELEASE)) {
            $output->error('未配置 release_sign_secret_key，请先执行 php think sf:keygen release');
            return 1;
        }

        $expect = [];
        $expectFile = (string)$input->getOption('expect');
        if ($expectFile !== '') {
            if (!is_file($expectFile)) {
                $output->error('expect 文件不存在：' . $expectFile);
                return 1;
            }
            $expect = json_decode((string)file_get_contents($expectFile), true);
            if (!is_array($expect)) {
                $output->error('expect 文件不是合法 JSON');
                return 1;
            }
        }

        $meta = [
            'kind'            => $kind,
            'product_id'      => (string)$input->getOption('product'),
            'build_no'        => (int)$input->getOption('build'),
            'edition'         => (string)$input->getOption('edition'),
            'channel'         => (string)$input->getOption('channel'),
            'patch_id'        => (string)$input->getOption('patch-id'),
            'revision'        => (int)$input->getOption('revision'),
            'level'           => (int)$input->getOption('level'),
            'theme_build'     => (int)$input->getOption('theme-build'),
            'theme_edition'   => (string)$input->getOption('theme-edition'),
            'expect_sha256'   => $expect,
            'min_bbs_version' => (string)$input->getOption('min-bbs'),
            'max_bbs_version' => (string)$input->getOption('max-bbs'),
            'min_php_version' => (string)$input->getOption('min-php'),
        ];

        if ($kind === 'patch' && $meta['patch_id'] === '') {
            $output->error('patch 必须提供 --patch-id');
            return 1;
        }
        if ($kind === 'patch' && ($meta['theme_build'] <= 0 || $meta['theme_edition'] === '')) {
            $output->error('patch 必须提供 --theme-build 与 --theme-edition');
            return 1;
        }

        // 1) 构建清单（含逐文件哈希与危险条目拦截）
        $built = ManifestService::build($zip, $meta);
        if (!$built['ok']) {
            $output->error($built['msg']);
            return 1;
        }
        $manifest = $built['manifest'];

        // 2) 签名
        try {
            $signed = ManifestService::sign($manifest);
        } catch (\Throwable $e) {
            $output->error('签名失败：' . $e->getMessage());
            return 1;
        }

        // 3) 自检验签 —— 签完立刻用公钥验一遍，杜绝发出无法验证的包
        $pubs = CryptoService::publicKeys(CryptoService::PURPOSE_RELEASE);
        $pub = $pubs[$signed['key_id']] ?? null;
        if ($pub === null) {
            $output->error('找不到 key_id=' . $signed['key_id'] . ' 对应的公钥，无法自检');
            return 1;
        }
        if (!ManifestService::verify($manifest, $signed['sig'], $pub)) {
            $output->error('自检验签失败，已中止（请检查密钥配置）');
            return 1;
        }

        $output->writeln('');
        $output->writeln('<info>清单构建并验签通过</info>');
        $output->writeln('  类型      : ' . $kind);
        $output->writeln('  产品      : ' . $manifest['product_id']);
        if ($kind === 'release') {
            $output->writeln('  build     : ' . $manifest['build_no'] . '  (' . $manifest['edition'] . ')');
            $output->writeln('  通道      : ' . $manifest['channel']);
        } else {
            $output->writeln('  补丁      : ' . $manifest['patch_id'] . ' r' . $manifest['revision'] . '  level=' . $manifest['level']);
            $output->writeln('  绑定主题  : ' . $manifest['theme_build'] . '  (' . $manifest['theme_edition'] . ')');
        }
        $output->writeln('  文件数    : ' . $manifest['file_count']);
        $output->writeln('  包大小    : ' . number_format($manifest['package_size'] / 1024, 1) . ' KB');
        $output->writeln('  包 SHA256 : ' . $manifest['package_sha256']);
        $output->writeln('  兼容区间  : bbs ' . ($manifest['min_bbs_version'] ?: '*') . ' ~ ' . ($manifest['max_bbs_version'] ?: '*')
            . ' / php >= ' . ($manifest['min_php_version'] ?: '*'));
        $output->writeln('  key_id    : ' . $signed['key_id']);
        $output->writeln('');

        if ($dry) {
            $output->writeln('<comment>--dry-run：未落库、未复制文件。</comment>');
            return 0;
        }

        // 4) 复制包到分发目录 + 落库
        $subDir = $kind === 'patch' ? 'patch' : 'v2';
        // HTTP 中间件会定义 APP_PATH/DS，但 CLI 命令不会经过该中间件。
        // 使用框架路径助手，保证 sf:sign 在真实发布机的命令行环境可运行。
        $destDir = app_path('common' . DIRECTORY_SEPARATOR . 'download' . DIRECTORY_SEPARATOR . $subDir);
        if (!is_dir($destDir) && !@mkdir($destDir, 0755, true)) {
            $output->error('无法创建分发目录：' . $destDir);
            return 1;
        }

        $fileName = $kind === 'patch'
            ? sprintf('%s_b%d_%s_r%d_%s.zip', $manifest['product_id'], $manifest['theme_build'], $manifest['patch_id'], $manifest['revision'], substr($manifest['package_sha256'], 0, 12))
            : sprintf('%s_%d.zip', $manifest['product_id'], $manifest['build_no']);
        $fileName = preg_replace('/[^A-Za-z0-9_.-]/', '_', $fileName);

        if (!copy($zip, $destDir . $fileName)) {
            $output->error('复制包文件失败');
            return 1;
        }

        // 复制后复核哈希，防止复制过程损坏
        if (!hash_equals($manifest['package_sha256'], (string)hash_file('sha256', $destDir . $fileName))) {
            @unlink($destDir . $fileName);
            $output->error('复制后哈希不一致，已删除目标文件');
            return 1;
        }

        $status = $input->getOption('publish') ? 1 : 0;

        try {
            if ($kind === 'release') {
                $row = [
                    'product_id'      => $manifest['product_id'],
                    'build_no'        => $manifest['build_no'],
                    'edition'         => $manifest['edition'],
                    'channel'         => $manifest['channel'],
                    'package_file'    => $fileName,
                    'package_sha256'  => $manifest['package_sha256'],
                    'package_size'    => $manifest['package_size'],
                    'manifest_json'   => json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'manifest_sig'    => $signed['sig'],
                    'sig_key_id'      => $signed['key_id'],
                    'min_bbs_version' => $manifest['min_bbs_version'],
                    'max_bbs_version' => $manifest['max_bbs_version'],
                    'min_php_version' => $manifest['min_php_version'],
                    'is_security'     => $input->getOption('security') ? 1 : 0,
                    'release_note'    => (string)$input->getOption('note'),
                    'status'          => $status,
                    'published_at'    => $status === 1 ? date('Y-m-d H:i:s') : null,
                    'created_at'      => date('Y-m-d H:i:s'),
                ];
                $exists = Db::name('release')
                    ->where('product_id', $row['product_id'])
                    ->where('build_no', $row['build_no'])
                    ->find();
                if ($exists) {
                    Db::name('release')->where('id', $exists['id'])->update($row);
                    $output->writeln('<info>已更新 SF_release #' . $exists['id'] . '</info>');
                } else {
                    $id = Db::name('release')->insertGetId($row);
                    $output->writeln('<info>已写入 SF_release #' . $id . '</info>');
                }
            } else {
                $row = [
                    'product_id'      => $manifest['product_id'],
                    'patch_id'        => $manifest['patch_id'],
                    'revision'        => $manifest['revision'],
                    'level'           => $manifest['level'],
                    'theme_build'     => $manifest['theme_build'],
                    'theme_edition'   => $manifest['theme_edition'],
                    'title'           => (string)$input->getOption('title'),
                    'description'     => (string)$input->getOption('description'),
                    'package_file'    => $fileName,
                    'package_sha256'  => $manifest['package_sha256'],
                    'package_size'    => $manifest['package_size'],
                    'manifest_json'   => json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'manifest_sig'    => $signed['sig'],
                    'sig_key_id'      => $signed['key_id'],
                    'min_bbs_version' => $manifest['min_bbs_version'],
                    'max_bbs_version' => $manifest['max_bbs_version'],
                    'min_php_version' => $manifest['min_php_version'],
                    'status'          => $status,
                    'created_at'      => date('Y-m-d H:i:s'),
                ];
                $exists = Db::name('patch')
                    ->where('product_id', $row['product_id'])
                    ->where('theme_build', $row['theme_build'])
                    ->where('patch_id', $row['patch_id'])
                    ->where('revision', $row['revision'])
                    ->find();
                if ($exists) {
                    Db::name('patch')->where('id', $exists['id'])->update($row);
                    $output->writeln('<info>已更新 SF_patch #' . $exists['id'] . '</info>');
                } else {
                    $id = Db::name('patch')->insertGetId($row);
                    $output->writeln('<info>已写入 SF_patch #' . $id . '</info>');
                }
            }
        } catch (\Throwable $e) {
            $output->error('落库失败：' . $e->getMessage());
            return 1;
        }

        $output->writeln('  分发文件  : ' . $destDir . $fileName);
        $output->writeln('  状态      : ' . ($status === 1 ? '已发布' : '草稿（加 --publish 直接发布）'));
        $output->writeln('');

        return 0;
    }
}
