#!/usr/bin/env bash
set -euo pipefail

SITE_DIR="/www/wwwroot/admin.noteweb.top/releases/20260816_230630"
PHP_BIN="/www/server/php/82/bin/php"
BACKUP_ROOT="/www/backup/admin.noteweb.top"

[ "$#" -eq 2 ] || { echo "usage: $0 <staging-directory> <deployment-stamp>" >&2; exit 64; }
STAGE_DIR="$1"
STAMP="$2"
case "$STAMP" in *[!0-9_]*|'') echo "invalid deployment stamp" >&2; exit 64 ;; esac

BACKUP_DIR="$BACKUP_ROOT/account_recovery_$STAMP"
CLIENT_CNF=""
FILES_CHANGED=0
DB_CHANGED=0
CONFIG_EXISTED=0
FILES=(
  app/SF_Auth.sql
  app/admin/controller/Set.php
  app/api/controller/Social.php
  app/common/lang/en-us.php
  app/common/lang/zh-cn.php
  app/common/service/PasswordRecoveryService.php
  app/common/service/PhoneVerificationService.php
  app/user/controller/Login.php
  app/user/controller/MyInfo.php
  app/user/service/MyInfoService.php
  app/user/view/login/forgot.html
  app/user/view/login/reg.html
  app/user/view/my_info/index.html
  database/migrate.sh
  database/migrations/20261004_password_recovery_channel.sql
  database/migrations/20261004_password_recovery_channel_rollback.sql
  deploy/account-recovery-deploy.sh
)

cleanup() {
  [ -z "$CLIENT_CNF" ] || [ ! -f "$CLIENT_CNF" ] || unlink "$CLIENT_CNF"
}

finish() {
  deploy_result=$?
  trap - EXIT
  set +e
  if [ "$deploy_result" -ne 0 ]; then
    if [ "$DB_CHANGED" -eq 1 ]; then
      "$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" \
        -e "DELETE FROM SF_config WHERE name='password_recovery_channel'"
      if [ "$CONFIG_EXISTED" -eq 1 ] && [ -s "$BACKUP_DIR/password-recovery-config.sql" ]; then
        "$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" \
          < "$BACKUP_DIR/password-recovery-config.sql"
      fi
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
    echo "部署验证失败，已恢复本次文件与找回密码配置" >&2
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
"$PHP_BIN" -r '$zh=require $argv[1];$en=require $argv[2];if(array_keys($zh)!==array_keys($en)){fwrite(STDERR,"language keys differ\n");exit(1);}' \
  "$STAGE_DIR/app/common/lang/zh-cn.php" "$STAGE_DIR/app/common/lang/en-us.php"

env_value() {
  "$PHP_BIN" -r '$v=parse_ini_file($argv[1],false,INI_SCANNER_RAW);echo trim((string)($v[$argv[2]]??""), "\"\047 ");' "$SITE_DIR/.env" "$1"
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
[ "$(df -Pk "$BACKUP_ROOT" | awk 'NR==2 {print $4}')" -gt 102400 ] || {
  echo "备份磁盘可用空间不足 100MB，拒绝部署" >&2
  exit 73
}
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
CONFIG_EXISTED="$(db_q "SELECT COUNT(*) FROM SF_config WHERE name='password_recovery_channel'")"
if [ "$CONFIG_EXISTED" -eq 1 ]; then
  "$DUMP_BIN" --defaults-extra-file="$CLIENT_CNF" --no-create-info --skip-add-locks \
    --skip-comments --skip-extended-insert --no-tablespaces \
    --where="name='password_recovery_channel'" "$DB_NAME" SF_config \
    > "$BACKUP_DIR/password-recovery-config.sql"
fi
chmod 600 "$BACKUP_DIR/database.sql" "$BACKUP_DIR/database.sql.sha256"
[ ! -f "$BACKUP_DIR/password-recovery-config.sql" ] || chmod 600 "$BACKUP_DIR/password-recovery-config.sql"

echo "[3/7] 原子部署文件"
deploy_file() {
  deploy_src="$1"
  deploy_dst="$2"
  deploy_tmp="${deploy_dst}.accountrecovery.$$"
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
chmod 0755 "$SITE_DIR/database/migrate.sh" "$SITE_DIR/deploy/account-recovery-deploy.sh"

echo "[4/7] 执行找回密码渠道专项迁移"
"$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" \
  < "$SITE_DIR/database/migrations/20261004_password_recovery_channel.sql"
DB_CHANGED=1

echo "[5/7] 清缓存并执行功能自检"
cd "$SITE_DIR"
"$PHP_BIN" think clear
for rel in "${FILES[@]}"; do
  cmp -s "$STAGE_DIR/$rel" "$SITE_DIR/$rel"
  case "$rel" in
    *.php) "$PHP_BIN" -l "$SITE_DIR/$rel" >/dev/null ;;
    *.sh) bash -n "$SITE_DIR/$rel" ;;
  esac
done
[ "$(db_q "SELECT COUNT(*) FROM SF_config WHERE name='password_recovery_channel' AND value IN ('email','sms')")" = "1" ]
"$PHP_BIN" -r 'require "vendor/autoload.php";$app=new think\App();$app->initialize();$channel=app\common\service\PasswordRecoveryService::channel();if(!in_array($channel,["email","sms"],true)){exit(1);}$zh=require "app/common/lang/zh-cn.php";$en=require "app/common/lang/en-us.php";if(array_keys($zh)!==array_keys($en)){exit(2);}echo "account recovery self-check passed: $channel\n";'

echo "[6/7] 线上 HTTP 健康检查"
for target in \
  "https://www.noteweb.top/" \
  "https://www.noteweb.top/user.php/login/reg.html" \
  "https://www.noteweb.top/user.php/login/forgot.html" \
  "https://www.noteweb.top/admin.php/Set/index.html"; do
  status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 "$target")"
  case "$status" in 200|301|302|303|403) ;; *) echo "线上 HTTP 健康检查失败：$target -> $status" >&2; exit 1 ;; esac
done

echo "[7/7] 检查近期致命错误"
find runtime/log -type f -mmin -10 -print0 2>/dev/null \
  | xargs -0 -r grep -Ei 'Fatal error|Parse error|Uncaught|Critical' \
  | tail -20 || true

FILES_CHANGED=0
DB_CHANGED=0
trap - EXIT
cleanup
echo "部署完成，备份目录：$BACKUP_DIR"
