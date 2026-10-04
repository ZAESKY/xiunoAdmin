#!/usr/bin/env bash
set -euo pipefail

SITE_DIR="/www/wwwroot/admin.noteweb.top/releases/20260816_230630"
PHP_BIN="/www/server/php/82/bin/php"
BACKUP_ROOT="/www/backup/admin.noteweb.top"

[ "$#" -eq 2 ] || { echo "usage: $0 <staging-directory> <deployment-stamp>" >&2; exit 64; }
STAGE_DIR="$1"
STAMP="$2"
case "$STAMP" in *[!0-9_]*|'') echo "invalid deployment stamp" >&2; exit 64 ;; esac

BACKUP_DIR="$BACKUP_ROOT/phone_publishing_$STAMP"
CLIENT_CNF=""
FILES_CHANGED=0
FILES=(
  app/admin/model/UserModel.php
  app/admin/service/UserService.php
  app/admin/view/user/list.html
  app/common/service/PhoneVerificationService.php
  app/user/controller/UserPlugin.php
  app/user/view/user_plugin/create.html
  deploy/qq-sms-registration-deploy.sh
  deploy/phone-publishing-deploy.sh
)

cleanup() {
  [ -z "$CLIENT_CNF" ] || [ ! -f "$CLIENT_CNF" ] || unlink "$CLIENT_CNF"
}

finish() {
  deploy_result=$?
  trap - EXIT
  set +e
  if [ "$deploy_result" -ne 0 ] && [ "$FILES_CHANGED" -eq 1 ]; then
    while IFS= read -r rel; do
      [ -n "$rel" ] || continue
      install -d "$(dirname "$SITE_DIR/$rel")"
      cp -a "$BACKUP_DIR/files/$rel" "$SITE_DIR/$rel"
    done < "$BACKUP_DIR/existing-files.txt"
    while IFS= read -r rel; do
      [ -n "$rel" ] || continue
      [ ! -f "$SITE_DIR/$rel" ] || [ -L "$SITE_DIR/$rel" ] || unlink "$SITE_DIR/$rel"
    done < "$BACKUP_DIR/new-files.txt"
    "$PHP_BIN" "$SITE_DIR/think" clear >/dev/null 2>&1 || true
    echo "部署验证失败，已恢复本次覆盖的文件" >&2
  fi
  cleanup
  exit "$deploy_result"
}
trap finish EXIT

[ -d "$SITE_DIR" ] && [ -f "$SITE_DIR/public/index.php" ] && [ -f "$SITE_DIR/.env" ] || {
  echo "线上目录校验失败" >&2
  exit 73
}
[ -d "$STAGE_DIR" ] && [ -f "$STAGE_DIR/SHA256SUMS" ] && [ ! -e "$STAGE_DIR/.env" ] || {
  echo "暂存目录校验失败" >&2
  exit 73
}

echo "[1/6] 校验暂存文件"
cd "$STAGE_DIR"
sha256sum -c SHA256SUMS
for rel in "${FILES[@]}"; do
  [ -f "$STAGE_DIR/$rel" ] || { echo "缺少待部署文件：$rel" >&2; exit 73; }
  case "$rel" in
    *.php) "$PHP_BIN" -l "$STAGE_DIR/$rel" >/dev/null ;;
    *.sh) bash -n "$STAGE_DIR/$rel" ;;
  esac
done

env_value() {
  "$PHP_BIN" -r '$v=parse_ini_file($argv[1],false,INI_SCANNER_RAW); echo trim((string)($v[$argv[2]]??""), "\"\047 ");' "$SITE_DIR/.env" "$1"
}
DB_HOST="$(env_value database_hostname)"; DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="$(env_value database_hostport)"; DB_PORT="${DB_PORT:-3306}"
DB_NAME="$(env_value database_database)"
DB_USER="$(env_value database_username)"
DB_PASS="$(env_value database_password)"
[ -n "$DB_NAME" ] && [ -n "$DB_USER" ] || { echo "数据库配置不完整" >&2; exit 78; }
CLIENT_CNF="$(mktemp)"
chmod 600 "$CLIENT_CNF"
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\ndefault-character-set=utf8mb4\n' \
  "$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASS" > "$CLIENT_CNF"
unset DB_PASS
MYSQL_BIN="$(command -v mysql || true)"; MYSQL_BIN="${MYSQL_BIN:-/www/server/mysql/bin/mysql}"
DUMP_BIN="$(command -v mysqldump || true)"; DUMP_BIN="${DUMP_BIN:-/www/server/mysql/bin/mysqldump}"
db_q() { "$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e "$1"; }

echo "[2/6] 备份线上文件和数据库"
[ ! -e "$BACKUP_DIR" ] || { echo "备份目录已存在" >&2; exit 73; }
install -d -m 700 "$BACKUP_DIR/files"
touch "$BACKUP_DIR/existing-files.txt" "$BACKUP_DIR/new-files.txt"
chmod 600 "$BACKUP_DIR/existing-files.txt" "$BACKUP_DIR/new-files.txt"
for rel in "${FILES[@]}"; do
  if [ -f "$SITE_DIR/$rel" ] && [ ! -L "$SITE_DIR/$rel" ]; then
    install -d "$BACKUP_DIR/files/$(dirname "$rel")"
    cp -a "$SITE_DIR/$rel" "$BACKUP_DIR/files/$rel"
    printf '%s\n' "$rel" >> "$BACKUP_DIR/existing-files.txt"
  else
    printf '%s\n' "$rel" >> "$BACKUP_DIR/new-files.txt"
  fi
done
"$DUMP_BIN" --defaults-extra-file="$CLIENT_CNF" --single-transaction --quick \
  --routines --triggers --events --no-tablespaces --default-character-set=utf8mb4 \
  "$DB_NAME" > "$BACKUP_DIR/database.sql"
[ "$(wc -c < "$BACKUP_DIR/database.sql")" -gt 1024 ]
grep -q 'Dump completed' "$BACKUP_DIR/database.sql"
sha256sum "$BACKUP_DIR/database.sql" > "$BACKUP_DIR/database.sql.sha256"
chmod 600 "$BACKUP_DIR/database.sql" "$BACKUP_DIR/database.sql.sha256"

echo "[3/6] 原子部署文件"
deploy_file() {
  deploy_src="$1"
  deploy_dst="$2"
  deploy_tmp="${deploy_dst}.phone.$$"
  install -d "$(dirname "$deploy_dst")"
  install -m 0644 "$deploy_src" "$deploy_tmp"
  if [ -e "$deploy_dst" ]; then
    chown --reference="$deploy_dst" "$deploy_tmp"
    chmod --reference="$deploy_dst" "$deploy_tmp"
  else
    chown --reference="$(dirname "$deploy_dst")" "$deploy_tmp"
  fi
  mv -f "$deploy_tmp" "$deploy_dst"
}
FILES_CHANGED=1
for rel in "${FILES[@]}"; do deploy_file "$STAGE_DIR/$rel" "$SITE_DIR/$rel"; done
chmod 0755 "$SITE_DIR/deploy/qq-sms-registration-deploy.sh" "$SITE_DIR/deploy/phone-publishing-deploy.sh"

echo "[4/6] 清缓存并复检文件"
cd "$SITE_DIR"
"$PHP_BIN" think clear
for rel in "${FILES[@]}"; do
  cmp -s "$STAGE_DIR/$rel" "$SITE_DIR/$rel"
  case "$rel" in
    *.php) "$PHP_BIN" -l "$SITE_DIR/$rel" >/dev/null ;;
    *.sh) bash -n "$SITE_DIR/$rel" ;;
  esac
done

echo "[5/6] 验证手机号管理和发布提示"
[ "$(db_q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='SF_user_phone_identity'")" = 1 ]
for column in phone_verified_at phone_verified_source; do
  [ "$(db_q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='SF_user' AND COLUMN_NAME='$column'")" = 1 ]
done
grep -q 'syncAdminPhone' app/common/service/PhoneVerificationService.php
grep -q 'phone_verified' app/admin/view/user/list.html
grep -q 'pluginPhoneBindingRequired' app/user/view/user_plugin/create.html
"$PHP_BIN" -r 'require "vendor/autoload.php"; $app=new think\App(); $app->initialize(); exit(app\common\service\PhoneVerificationService::normalizePhone("+8613800138000")==="13800138000" ? 0 : 1);'

echo "[6/6] 检查近期致命错误"
find runtime/log -type f -mmin -10 -print0 2>/dev/null \
  | xargs -0 -r grep -Ei 'Fatal error|Parse error|Uncaught|Critical' \
  | tail -20 || true

FILES_CHANGED=0
trap - EXIT
cleanup
echo "插件发布手机号提示和管理员手机号管理部署完成"
echo "backup=$BACKUP_DIR"
