#!/usr/bin/env bash
set -euo pipefail

SITE_DIR="/www/wwwroot/admin.noteweb.top/releases/20260816_230630"
PHP_BIN="/www/server/php/82/bin/php"

if [ "$#" -ne 2 ]; then
  echo "usage: $0 <staging-directory> <deployment-stamp>" >&2
  exit 64
fi

STAGE_DIR="$1"
DEPLOYMENT_STAMP="$2"
BACKUP_DIR="/www/wwwroot/admin.noteweb.top/backups/license_legacy_bridge_${DEPLOYMENT_STAMP}"
CLIENT_CNF=""
FILES_CHANGED=0

cleanup() {
  if [ -n "$CLIENT_CNF" ] && [ -e "$CLIENT_CNF" ]; then
    unlink "$CLIENT_CNF"
  fi
}

rollback_files() {
  status=$?
  if [ "$status" -ne 0 ] && [ "$FILES_CHANGED" -eq 1 ] && [ -d "$BACKUP_DIR/files" ]; then
    echo "部署失败，正在恢复服务文件和 .env" >&2
    cp -a "$BACKUP_DIR/files/app/common/service/LicenseService.php" "$SITE_DIR/app/common/service/LicenseService.php"
    cp -a "$BACKUP_DIR/files/app/api/service/LicenseV2Service.php" "$SITE_DIR/app/api/service/LicenseV2Service.php"
    cp -a "$BACKUP_DIR/files/app/user/service/AuthService.php" "$SITE_DIR/app/user/service/AuthService.php"
    cp -a "$BACKUP_DIR/env.before" "$SITE_DIR/.env"
    "$PHP_BIN" "$SITE_DIR/think" clear >/dev/null 2>&1 || true
  fi
  cleanup
  exit "$status"
}

trap rollback_files EXIT

cd "$SITE_DIR"

echo "[1/8] 校验待部署文件"
printf '%s  %s\n' \
  '502c0e807c9ca356b1d587a90c771a0b13b64a5a2ccddec4ff390af515e11ef7' "$STAGE_DIR/LicenseService.php" \
  '5bf6947b9bd13927331243b14fe8baa4cba30f9e5876eca3fe011e344ab0d833' "$STAGE_DIR/LicenseV2Service.php" \
  '4bad28b1d41e0f36d6e4e06745375e87c9ebadc725de4d04838d5f72d02b008d' "$STAGE_DIR/UserAuthService.php" \
  'aceb4b59848d4945b6b6eab2dcf10019b8d6b2013d15088490927f78385846ed' "$STAGE_DIR/20261002_license_legacy_bridge.sql" \
  | sha256sum -c -
"$PHP_BIN" -l "$STAGE_DIR/LicenseService.php"
"$PHP_BIN" -l "$STAGE_DIR/LicenseV2Service.php"
"$PHP_BIN" -l "$STAGE_DIR/UserAuthService.php"

echo "[2/8] 创建代码与配置备份"
if [ -e "$BACKUP_DIR" ]; then
  echo "备份目录已存在，拒绝覆盖：$BACKUP_DIR" >&2
  exit 73
fi
install -d -m 700 \
  "$BACKUP_DIR/files/app/common/service" \
  "$BACKUP_DIR/files/app/api/service" \
  "$BACKUP_DIR/files/app/user/service" \
  "$BACKUP_DIR/files/database/migrations"
cp -a app/common/service/LicenseService.php "$BACKUP_DIR/files/app/common/service/LicenseService.php"
cp -a app/api/service/LicenseV2Service.php "$BACKUP_DIR/files/app/api/service/LicenseV2Service.php"
cp -a app/user/service/AuthService.php "$BACKUP_DIR/files/app/user/service/AuthService.php"
if [ -e database/migrations/20261002_license_legacy_bridge.sql ]; then
  cp -a database/migrations/20261002_license_legacy_bridge.sql \
    "$BACKUP_DIR/files/database/migrations/20261002_license_legacy_bridge.sql"
fi
cp -a .env "$BACKUP_DIR/env.before"
chmod 600 "$BACKUP_DIR/env.before"

echo "[3/8] 创建完整数据库备份"
env_value() {
  "$PHP_BIN" -r '
    $values = parse_ini_file(".env", false, INI_SCANNER_RAW);
    $key = $argv[1];
    echo trim((string)($values[$key] ?? ""), "\"\047 ");
  ' "$1"
}

db_host="$(env_value database_hostname)"
db_port="$(env_value database_hostport)"
db_name="$(env_value database_database)"
db_user="$(env_value database_username)"
db_pass="$(env_value database_password)"
db_host="${db_host:-127.0.0.1}"
db_port="${db_port:-3306}"
test -n "$db_name"
test -n "$db_user"

CLIENT_CNF="$(mktemp)"
chmod 600 "$CLIENT_CNF"
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\ndefault-character-set=utf8mb4\n' \
  "$db_host" "$db_port" "$db_user" "$db_pass" > "$CLIENT_CNF"

dump_bin="$(command -v mysqldump || true)"
if [ -z "$dump_bin" ]; then
  dump_bin="/www/server/mysql/bin/mysqldump"
fi
"$dump_bin" --defaults-extra-file="$CLIENT_CNF" \
  --single-transaction --quick --routines --triggers --events --no-tablespaces \
  --default-character-set=utf8mb4 "$db_name" > "$BACKUP_DIR/database.sql"
test "$(wc -c < "$BACKUP_DIR/database.sql")" -gt 1024
grep -q 'Table structure for table `QH_auth`' "$BACKUP_DIR/database.sql"
grep -q 'Dump completed' "$BACKUP_DIR/database.sql"
sha256sum "$BACKUP_DIR/database.sql" > "$BACKUP_DIR/database.sql.sha256"
chmod 600 "$BACKUP_DIR/database.sql" "$BACKUP_DIR/database.sql.sha256"

echo "[4/8] 执行幂等数据库迁移"
mysql --defaults-extra-file="$CLIENT_CNF" "$db_name" \
  < "$STAGE_DIR/20261002_license_legacy_bridge.sql"
column_count="$(mysql --defaults-extra-file="$CLIENT_CNF" "$db_name" -N -B \
  -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='QH_license' AND COLUMN_NAME='source_auth_id'")"
index_count="$(mysql --defaults-extra-file="$CLIENT_CNF" "$db_name" -N -B \
  -e "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='QH_license' AND INDEX_NAME='uk_product_source_auth'")"
test "$column_count" = 1
test "$index_count" = 2

echo "[5/8] 原子部署服务文件"
deploy_file() {
  src="$1"
  dst="$2"
  tmp="${dst}.qhnew.$$"
  install -m 0644 "$src" "$tmp"
  if [ -e "$dst" ]; then
    chown --reference="$dst" "$tmp"
    chmod --reference="$dst" "$tmp"
  else
    chown --reference="$(dirname "$dst")" "$tmp"
  fi
  mv -f "$tmp" "$dst"
}

FILES_CHANGED=1
deploy_file "$STAGE_DIR/LicenseService.php" app/common/service/LicenseService.php
deploy_file "$STAGE_DIR/LicenseV2Service.php" app/api/service/LicenseV2Service.php
deploy_file "$STAGE_DIR/UserAuthService.php" app/user/service/AuthService.php
deploy_file "$STAGE_DIR/20261002_license_legacy_bridge.sql" \
  database/migrations/20261002_license_legacy_bridge.sql

echo "[6/8] 配置产品映射"
if grep -Eq '^[[:space:]]*license_product_app_map[[:space:]]*=' .env; then
  grep -Eq '^[[:space:]]*license_product_app_map[[:space:]]*=[[:space:]]*zaesky_theme_light:1[[:space:]]*$' .env
else
  env_tmp=".env.qhnew.$$"
  cp -p .env "$env_tmp"
  printf '\n# v2 产品标识到旧授权应用的显式映射\nlicense_product_app_map = zaesky_theme_light:1\n' \
    >> "$env_tmp"
  mv -f "$env_tmp" .env
fi
chmod 600 .env

echo "[7/8] 清理框架缓存并复检"
"$PHP_BIN" -l app/common/service/LicenseService.php
"$PHP_BIN" -l app/api/service/LicenseV2Service.php
"$PHP_BIN" -l app/user/service/AuthService.php
"$PHP_BIN" think clear

legacy_count="$(mysql --defaults-extra-file="$CLIENT_CNF" "$db_name" -N -B \
  -e 'SELECT COUNT(*) FROM QH_auth')"
v2_count="$(mysql --defaults-extra-file="$CLIENT_CNF" "$db_name" -N -B \
  -e 'SELECT COUNT(*) FROM QH_license')"
product_count="$(mysql --defaults-extra-file="$CLIENT_CNF" "$db_name" -N -B \
  -e 'SELECT COUNT(*) FROM QH_app WHERE id=1')"
test "$product_count" = 1

echo "[8/8] 部署结果"
printf 'backup=%s\nlegacy_auth=%s\nv2_license=%s\nsource_auth_column=%s\nsource_auth_index_rows=%s\n' \
  "$BACKUP_DIR" "$legacy_count" "$v2_count" "$column_count" "$index_count"
grep -E '^[[:space:]]*license_product_app_map[[:space:]]*=' .env
sha256sum \
  app/common/service/LicenseService.php \
  app/api/service/LicenseV2Service.php \
  app/user/service/AuthService.php \
  database/migrations/20261002_license_legacy_bridge.sql

FILES_CHANGED=0
trap cleanup EXIT
