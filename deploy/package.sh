#!/usr/bin/env bash
#
# QH 授权系统 —— 构建干净的部署包
#
# 在本地项目根目录执行：
#     bash deploy/package.sh              # 含 vendor（服务器无需 composer）
#     bash deploy/package.sh --no-vendor  # 不含 vendor（服务器上跑 composer install）
#     bash deploy/package.sh --with-upload # 附带 public/upload（30MB，一般单独同步）
#
# 排除清单的用意：
#   .git          104MB 的历史，生产环境不需要，且可能含已删除的敏感提交
#   .env          真实凭据绝不进包；服务器上从 .env.example 现场生成
#   runtime/      缓存与日志，带过去只会引入陈旧状态
#   app/common/download/ 生产更新包属于迁移数据，必须从旧生产只读复制
#   tests/        测试代码不上生产
#   phpMyAdmin*   该版本线存在已知高危漏洞，绝不应部署到公网
#   *.sql         根目录的历史 SQL 脚本不属于运行时资产
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

C_GRN=$'\033[32m'; C_YEL=$'\033[33m'; C_BLU=$'\033[36m'; C_RST=$'\033[0m'

WITH_VENDOR=1
WITH_UPLOAD=0
for a in "$@"; do
  case "$a" in
    --no-vendor)   WITH_VENDOR=0 ;;
    --with-upload) WITH_UPLOAD=1 ;;
    *) echo "未知参数：$a"; exit 1 ;;
  esac
done

STAMP="$(date +%Y%m%d_%H%M%S)"
OUT="$ROOT_DIR/deploy/qh_admin_${STAMP}.tar.gz"

EXCLUDES=(
  --exclude=./.git
  --exclude=./.git/*
  --exclude=./.env
  --exclude=./qh.env
  --exclude=./.idea
  --exclude=./.vscode
  --exclude=./.claude
  --exclude=./.stitch
  --exclude=./runtime
  --exclude=./app/common/download
  --exclude=./app/QH_Auth.sql
  --exclude=./app/checkin.sql
  --exclude=./app/QH_rebate_migration.sql
  --exclude=./tests
  --exclude=./storage
  --exclude=./phpMyAdmin4.8.5
  --exclude=./deploy/*_admin_*.tar.gz
  --exclude=./forum.html
  --exclude=./update_online_test.sql
  --exclude=./*.log
  --exclude=./.DS_Store
  --exclude=*/.DS_Store
  --exclude=./node_modules
)

[ "$WITH_VENDOR" = "0" ] && EXCLUDES+=( --exclude=./vendor )
[ "$WITH_UPLOAD" = "0" ] && EXCLUDES+=( --exclude=./public/upload )

echo "${C_BLU}构建部署包${C_RST}"
echo "  源目录  : $ROOT_DIR"
echo "  vendor  : $([ "$WITH_VENDOR" = 1 ] && echo 包含 || echo '不含（服务器需执行 composer install --no-dev -o）')"
echo "  upload  : $([ "$WITH_UPLOAD" = 1 ] && echo 包含 || echo '不含（建议用 rsync 单独同步）')"
echo

# 打包前的安全自检：绝不允许把密钥打进包里
echo "  安全自检..."
LEAK=0
if tar -tzf /dev/null >/dev/null 2>&1; then :; fi   # noop，保持兼容

if [ -f .env ]; then
  echo "    ${C_YEL}提示${C_RST}：本地 .env 存在，已排除，不会进入部署包"
fi

# 扫描将要打包的 PHP 文件里有没有疑似密钥常量
while IFS= read -r f; do
  if grep -qE "(secret_key|private_key|password)\s*=\s*['\"][A-Za-z0-9+/=]{24,}" "$f" 2>/dev/null; then
    echo "    ${C_YEL}可疑${C_RST}：$f 中出现疑似硬编码密钥，请人工确认"
    LEAK=1
  fi
done < <(find ./app ./config ./addons -name '*.php' -type f 2>/dev/null)
if [ "$LEAK" = "0" ]; then
  echo "    ${C_GRN}✓${C_RST} 未发现硬编码密钥"
else
  echo "    检测到疑似硬编码密钥，已中止打包；请先人工确认并移除。"
  exit 1
fi

# 确认关键的新增文件都在
for must in \
  app/common/service/CryptoService.php \
  app/common/service/LicenseService.php \
  app/api/route/route.php \
  app/command/Sign.php \
  database/migrate.sh \
  database/migrations/20260816_legacy_429_bridge_tables.sql \
  database/migrations/20260816_auth_legacy_snapshot.sql \
  database/migrations/20261002_single_site_license.sql
do
  [ -f "$must" ] || { echo "    缺少关键文件：$must"; exit 1; }
done
echo "    ${C_GRN}✓${C_RST} 关键文件齐全"

echo
echo "  打包中..."
COPYFILE_DISABLE=1 tar --no-xattrs -czf "$OUT" "${EXCLUDES[@]}" -C "$ROOT_DIR" . 2>/dev/null

# 不只信任参数层面的 exclude：对最终产物再做一次 fail-closed 审计，
# 防止以后调整 tar 参数或目录结构时把运行时数据/凭据带进生产包。
FORBIDDEN_ENTRY_RE='^\./(\.git(/|$)|\.env$|qh\.env$|runtime(/|$)|tests(/|$)|storage(/|$)|app/common/download(/|$)|public/upload(/|$)|phpMyAdmin[^/]*(/|$)|deploy/[^/]*_admin_[^/]*\.tar\.gz$|forum\.html$|update_online_test\.sql$|node_modules(/|$)|.*\.log$)'
FORBIDDEN_FOUND="$(tar -tzf "$OUT" | grep -E "$FORBIDDEN_ENTRY_RE" | head -20 || true)"
if [ -n "$FORBIDDEN_FOUND" ]; then
  echo "    部署包包含禁止条目，已中止："
  echo "$FORBIDDEN_FOUND"
  rm -f -- "$OUT"
  exit 1
fi
echo "    ${C_GRN}✓${C_RST} 产物不含凭据、运行时数据、测试目录或上传内容"

SIZE="$(du -h "$OUT" | cut -f1)"
COUNT="$(tar -tzf "$OUT" | wc -l | tr -d ' ')"

echo
echo "${C_GRN}完成${C_RST}"
echo "  文件  : $OUT"
echo "  大小  : $SIZE"
echo "  条目  : $COUNT"
echo "  SHA256: $(shasum -a 256 "$OUT" 2>/dev/null | cut -d' ' -f1 || sha256sum "$OUT" | cut -d' ' -f1)"
echo
echo "${C_YEL}上传后校验哈希，确保传输完整。${C_RST}"
echo
echo "下一步："
echo "  1) 把 deploy/preflight.sh 传到新服务器先跑一遍环境自检"
echo "  2) 按 deploy/DEPLOY.md 的步骤部署"
