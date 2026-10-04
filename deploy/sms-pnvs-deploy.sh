#!/usr/bin/env bash
set -euo pipefail

SITE_DIR="/www/wwwroot/admin.noteweb.top/releases/20260816_230630"
PHP_BIN="/www/server/php/82/bin/php"
BACKUP_ROOT="/www/backup/admin.noteweb.top"

[ "$#" -eq 3 ] || { echo "usage: $0 <staging-directory> <deployment-stamp> <template-code>" >&2; exit 64; }
STAGE_DIR="$1"
STAMP="$2"
TEMPLATE_CODE="$3"
case "$STAMP" in *[!0-9_]*|'') echo "invalid deployment stamp" >&2; exit 64 ;; esac
printf '%s' "$TEMPLATE_CODE" | grep -Eq '^(SMS_[0-9]{6,24}|[0-9]{6,20})$' || {
  echo "invalid template code" >&2
  exit 64
}

BACKUP_DIR="$BACKUP_ROOT/sms_pnvs_$STAMP"
CLIENT_CNF=""
FILES_CHANGED=0
DB_CHANGED=0
FILES=(
  app/SF_Auth.sql
  app/admin/controller/Set.php
  app/common/service/AliyunSmsService.php
  database/migrations/20261004_qq_sms_registration.sql
  deploy/qq-sms-registration-deploy.sh
  deploy/sms-pnvs-deploy.sh
)

cleanup() {
  [ -z "$CLIENT_CNF" ] || [ ! -f "$CLIENT_CNF" ] || unlink "$CLIENT_CNF"
}

finish() {
  status=$?
  trap - EXIT
  set +e
  if [ "$status" -ne 0 ]; then
    if [ "$DB_CHANGED" -eq 1 ] && [ -s "$BACKUP_DIR/sms-template.sql" ]; then
      "$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" \
        -e "DELETE FROM SF_config WHERE name='sms_template_code'"
      "$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" \
        < "$BACKUP_DIR/sms-template.sql"
    fi
    if [ "$FILES_CHANGED" -eq 1 ]; then
      while IFS= read -r rel; do
        [ -n "$rel" ] || continue
        install -d "$(dirname "$SITE_DIR/$rel")"
        cp -a "$BACKUP_DIR/files/$rel" "$SITE_DIR/$rel"
      done < "$BACKUP_DIR/existing-files.txt"
      while IFS= read -r rel; do
        [ -n "$rel" ] || continue
        [ ! -f "$SITE_DIR/$rel" ] || [ -L "$SITE_DIR/$rel" ] || unlink "$SITE_DIR/$rel"
      done < "$BACKUP_DIR/new-files.txt"
    fi
    "$PHP_BIN" "$SITE_DIR/think" clear >/dev/null 2>&1 || true
    echo "部署验证失败，已恢复本次文件与短信模板配置" >&2
  fi
  cleanup
  exit "$status"
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

echo "[1/7] 校验暂存文件"
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

echo "[2/7] 备份线上文件和数据库"
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
"$DUMP_BIN" --defaults-extra-file="$CLIENT_CNF" --no-create-info --skip-add-locks \
  --no-tablespaces \
  --skip-comments --skip-extended-insert --where="name='sms_template_code'" \
  "$DB_NAME" SF_config > "$BACKUP_DIR/sms-template.sql"
[ "$(wc -c < "$BACKUP_DIR/database.sql")" -gt 1024 ]
grep -q 'Dump completed' "$BACKUP_DIR/database.sql"
grep -q 'sms_template_code' "$BACKUP_DIR/sms-template.sql"
sha256sum "$BACKUP_DIR/database.sql" > "$BACKUP_DIR/database.sql.sha256"
chmod 600 "$BACKUP_DIR/database.sql" "$BACKUP_DIR/database.sql.sha256" "$BACKUP_DIR/sms-template.sql"

echo "[3/7] 原子部署文件"
deploy_file() {
  src="$1"
  dst="$2"
  tmp="${dst}.pnvs.$$"
  install -d "$(dirname "$dst")"
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
for rel in "${FILES[@]}"; do deploy_file "$STAGE_DIR/$rel" "$SITE_DIR/$rel"; done
chmod 0755 "$SITE_DIR/deploy/qq-sms-registration-deploy.sh" "$SITE_DIR/deploy/sms-pnvs-deploy.sh"

echo "[4/7] 切换为号码认证赠送模板"
db_q "UPDATE SF_config SET value='$TEMPLATE_CODE', tip='号码认证赠送模板填数字 CODE（如 100001），普通短信模板填 SMS_ 开头的 CODE；模板须包含 \${code}' WHERE name='sms_template_code'"
DB_CHANGED=1
[ "$(db_q "SELECT value FROM SF_config WHERE name='sms_template_code' LIMIT 1")" = "$TEMPLATE_CODE" ]

echo "[5/7] 清缓存并验证 PHP"
cd "$SITE_DIR"
"$PHP_BIN" think clear
for rel in "${FILES[@]}"; do
  cmp -s "$STAGE_DIR/$rel" "$SITE_DIR/$rel"
  case "$rel" in
    *.php) "$PHP_BIN" -l "$SITE_DIR/$rel" >/dev/null ;;
    *.sh) bash -n "$SITE_DIR/$rel" ;;
  esac
done

echo "[6/7] 验证短信接口路由和配置"
"$PHP_BIN" -r 'require "vendor/autoload.php"; $app=new think\App(); $app->initialize(); $m=new ReflectionMethod("app\\common\\service\\AliyunSmsService","isPnvsTemplate"); $m->setAccessible(true); exit($m->invoke(null,"100001") && !$m->invoke(null,"SMS_339025797") && app\common\service\AliyunSmsService::configured() ? 0 : 1);'
grep -q "DYPNS_ENDPOINT = 'https://dypnsapi.aliyuncs.com/'" app/common/service/AliyunSmsService.php
curl -fsS --connect-timeout 5 --max-time 10 -o /dev/null https://dypnsapi.aliyuncs.com/ || true

echo "[7/7] 检查近期致命错误"
find runtime/log -type f -mmin -10 -print0 2>/dev/null \
  | xargs -0 -r grep -Ei 'Fatal error|Parse error|Uncaught|Critical' \
  | tail -20 || true

FILES_CHANGED=0
DB_CHANGED=0
trap - EXIT
cleanup
echo "号码认证短信接口部署完成"
echo "backup=$BACKUP_DIR"
