#!/usr/bin/env bash
set -euo pipefail

SITE_DIR="/www/wwwroot/admin.noteweb.top/releases/20260816_230630"
PHP_BIN="/www/server/php/82/bin/php"
BACKUP_ROOT="/www/backup/admin.noteweb.top"

[ "$#" -eq 2 ] || { echo "usage: $0 <staging-directory> <deployment-stamp>" >&2; exit 64; }
STAGE_DIR="$1"
STAMP="$2"
case "$STAMP" in *[!0-9_]*|'') echo "invalid deployment stamp" >&2; exit 64 ;; esac
BACKUP_DIR="$BACKUP_ROOT/qq_sms_registration_$STAMP"
CLIENT_CNF=""
FILES_CHANGED=0

FILES=(
  .env.example
  app/SF_Auth.sql
  app/admin/config/site.php
  app/admin/controller/Set.php
  app/admin/model/UserModel.php
  app/admin/service/UserService.php
  app/admin/view/set/index.html
  app/admin/view/user/list.html
  app/api/controller/Social.php
  app/common.php
  app/common/service/AliyunSmsService.php
  app/common/service/PhoneVerificationService.php
  app/common/service/PluginRewardService.php
  app/common/service/SecretConfigService.php
  app/user/controller/Login.php
  app/user/controller/MyInfo.php
  app/user/controller/Phone.php
  app/user/controller/Rebate.php
  app/user/controller/Withdraw.php
  app/user/controller/UserPlugin.php
  app/user/model/User.php
  app/user/view/login/reg.html
  app/user/view/my_info/index.html
  app/user/view/rebate/index.html
  app/user/view/withdraw/index.html
  app/user/view/user_plugin/create.html
  database/migrate.sh
  database/migrations/20261004_qq_sms_registration.sql
  database/migrations/20261004_qq_sms_registration_rollback.sql
  database/migrations/20261004_sms_template_scenes.sql
  database/migrations/20261004_sms_template_scenes_rollback.sql
  public/Assets/js/qq-oauth.js
  deploy/qq-sms-registration-deploy.sh
)

cleanup() {
  [ -z "$CLIENT_CNF" ] || [ ! -f "$CLIENT_CNF" ] || unlink "$CLIENT_CNF"
}

finish() {
  status=$?
  trap - EXIT
  set +e
  if [ "$status" -ne 0 ] && [ "$FILES_CHANGED" -eq 1 ]; then
    echo "部署验证失败，正在恢复本次覆盖的文件" >&2
    while IFS= read -r rel; do
      [ -n "$rel" ] || continue
      if [ -f "$BACKUP_DIR/files/$rel" ]; then
        install -d "$(dirname "$SITE_DIR/$rel")"
        cp -a "$BACKUP_DIR/files/$rel" "$SITE_DIR/$rel"
      fi
    done < "$BACKUP_DIR/existing-files.txt"
    while IFS= read -r rel; do
      [ -n "$rel" ] || continue
      [ ! -f "$SITE_DIR/$rel" ] || [ -L "$SITE_DIR/$rel" ] || unlink "$SITE_DIR/$rel"
    done < "$BACKUP_DIR/new-files.txt"
    "$PHP_BIN" "$SITE_DIR/think" clear >/dev/null 2>&1 || true
    echo "代码已恢复；幂等新增的短信表、字段和默认关闭配置予以保留。" >&2
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

echo "[1/8] 校验暂存文件和运行环境"
cd "$STAGE_DIR"
sha256sum -c SHA256SUMS
for rel in "${FILES[@]}"; do
  [ -f "$STAGE_DIR/$rel" ] || { echo "缺少待部署文件：$rel" >&2; exit 73; }
  case "$rel" in
    *.php) "$PHP_BIN" -l "$STAGE_DIR/$rel" >/dev/null ;;
    *.sh) bash -n "$STAGE_DIR/$rel" ;;
  esac
done
"$PHP_BIN" -r 'exit(extension_loaded("sodium") && extension_loaded("curl") ? 0 : 1);'

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

[ "$(db_q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='SF_social_identity'")" = 1 ]
[ "$(db_q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='SF_user_social_identity'")" = 1 ]

echo "[2/8] 备份线上文件和数据库"
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
grep -q 'SF_user' "$BACKUP_DIR/database.sql"
grep -q 'Dump completed' "$BACKUP_DIR/database.sql"
sha256sum "$BACKUP_DIR/database.sql" > "$BACKUP_DIR/database.sql.sha256"
chmod 600 "$BACKUP_DIR/database.sql" "$BACKUP_DIR/database.sql.sha256"

echo "[3/8] 执行幂等短信迁移"
"$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" \
  < "$STAGE_DIR/database/migrations/20261004_qq_sms_registration.sql"
"$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" \
  < "$STAGE_DIR/database/migrations/20261004_sms_template_scenes.sql"

echo "[4/8] 原子部署文件"
deploy_file() {
  src="$1"
  dst="$2"
  tmp="${dst}.qqsms.$$"
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
chmod 0755 "$SITE_DIR/database/migrate.sh" "$SITE_DIR/deploy/qq-sms-registration-deploy.sh"

echo "[5/8] 清缓存并复检文件"
cd "$SITE_DIR"
for rel in "${FILES[@]}"; do
  cmp -s "$STAGE_DIR/$rel" "$SITE_DIR/$rel"
  case "$rel" in
    *.php) "$PHP_BIN" -l "$SITE_DIR/$rel" >/dev/null ;;
    *.sh) bash -n "$SITE_DIR/$rel" ;;
  esac
done
"$PHP_BIN" think clear

echo "[6/8] 验证数据库结构"
for table in SF_user_phone_identity SF_sms_audit; do
  [ "$(db_q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table'")" = 1 ]
done
for column in phone_verified_at phone_verified_source; do
  [ "$(db_q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='SF_user' AND COLUMN_NAME='$column'")" = 1 ]
done
[ "$(db_q "SELECT COUNT(*) FROM SF_config WHERE name LIKE 'sms_%'")" -ge 15 ]
[ "$(db_q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='SF_sms_audit' AND COLUMN_NAME='template_code'")" = 1 ]

echo "[7/8] 检查注册页和接口静态断言"
# 生产 Nginx 使用公网 IP 白名单，服务器回环访问公网域名会按预期得到 403。
# 公网 HTTP 冒烟检查由部署发起端在脚本成功后执行，这里只做不受白名单影响的断言。
grep -q '使用 QQ 快速注册' app/user/view/login/reg.html
! grep -q 'name="password"' app/user/view/login/reg.html
grep -q "url: '/api.php/Social/register'" app/user/view/login/reg.html
grep -q 'class Phone extends UserBackend' app/user/controller/Phone.php
grep -q "private const DYSMS_ENDPOINT = 'https://dysmsapi.aliyuncs.com/'" app/common/service/AliyunSmsService.php
grep -q "private const DYPNS_ENDPOINT = 'https://dypnsapi.aliyuncs.com/'" app/common/service/AliyunSmsService.php
"$PHP_BIN" -r 'require "vendor/autoload.php";$app=new think\App();$app->initialize();$map=["login_register"=>"sms_template_login_register","phone_change"=>"sms_template_phone_change","password_reset"=>"sms_template_password_reset","phone_bind"=>"sms_template_phone_bind","phone_verify"=>"sms_template_phone_verify"];foreach($map as $purpose=>$name){$stored=trim((string)conf($name));if($stored===""||app\common\service\AliyunSmsService::templateCodeForPurpose($purpose)!==$stored){exit(1);}}'

echo "[8/8] 检查近期致命错误"
find runtime/log -type f -mmin -10 -print0 2>/dev/null \
  | xargs -0 -r grep -Ei 'Fatal error|Parse error|Uncaught|Critical' \
  | tail -20 || true

FILES_CHANGED=0
trap - EXIT
cleanup
echo "QQ 一键注册和阿里云短信认证部署完成"
echo "backup=$BACKUP_DIR"
