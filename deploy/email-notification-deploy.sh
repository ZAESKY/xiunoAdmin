#!/usr/bin/env bash
set -euo pipefail

SITE_DIR="/www/wwwroot/admin.noteweb.top/releases/20260816_230630"
PHP_BIN="/www/server/php/82/bin/php"
BACKUP_ROOT="/www/backup/admin.noteweb.top"

[ "$#" -eq 2 ] || { echo "usage: $0 <staging-directory> <deployment-stamp>" >&2; exit 64; }
STAGE_DIR="$1"
STAMP="$2"
case "$STAMP" in *[!0-9_]*|'') echo "invalid deployment stamp" >&2; exit 64 ;; esac

BACKUP_DIR="$BACKUP_ROOT/email_notifications_$STAMP"
CLIENT_CNF=""
FILES_CHANGED=0
MIGRATION_APPLIED=0
FEATURE_TABLES_BEFORE=0
FILES=(
  addons/mail/Plugin.php
  app/QH_Auth.sql
  app/common/model/NotificationModel.php
  app/common/service/NotificationEventService.php
  app/common/service/EmailNotificationService.php
  app/common/service/PluginRewardService.php
  app/common/service/RebateSettlementService.php
  app/pay/service/CommonService.php
  app/pay/service/PluginPayCallbackService.php
  app/admin/controller/Set.php
  app/admin/controller/Order.php
  app/admin/model/PluginModel.php
  app/admin/model/PluginCommentModel.php
  app/admin/model/FeedbackModel.php
  app/admin/service/MenuService.php
  app/admin/view/set/email_notification.html
  app/api/service/PluginApiService.php
  app/user/controller/MyInfo.php
  app/user/controller/Withdraw.php
  app/user/model/FeedbackModel.php
  app/user/service/UserPluginService.php
  app/user/view/my_info/index.html
  app/common/lang/zh-cn.php
  app/common/lang/en-us.php
  database/migrate.sh
  database/migrations/20261004_email_notifications.sql
  database/migrations/20261004_email_notifications_rollback.sql
  deploy/email-notification-deploy.sh
)

cleanup() {
  [ -z "$CLIENT_CNF" ] || [ ! -f "$CLIENT_CNF" ] || unlink "$CLIENT_CNF"
}

finish() {
  deploy_result=$?
  trap - EXIT
  set +e
  if [ "$deploy_result" -ne 0 ]; then
    if [ "$MIGRATION_APPLIED" -eq 1 ] && [ "$FEATURE_TABLES_BEFORE" -eq 0 ]; then
      mysql --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" < "$STAGE_DIR/database/migrations/20261004_email_notifications_rollback.sql"
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
      "$PHP_BIN" "$SITE_DIR/think" clear >/dev/null 2>&1 || true
      echo "部署验证失败，已恢复本次覆盖文件；新建邮件通知表已按安全条件回滚" >&2
    fi
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
"$PHP_BIN" -r '$zh=require "app/common/lang/zh-cn.php";$en=require "app/common/lang/en-us.php";if(array_keys($zh)!==array_keys($en)){exit(1);}'
grep -q "class EmailNotificationService" app/common/service/EmailNotificationService.php
grep -q "plugin_comment_pending" app/common/service/NotificationEventService.php
grep -q "sandbox=\"\"" app/admin/view/set/email_notification.html

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
DUMP_BIN="$(command -v mysqldump || true)"; DUMP_BIN="${DUMP_BIN:-/www/server/mysql/bin/mysqldump}"
FEATURE_TABLES_BEFORE="$(mysql --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('QH_notification_email_preference','QH_notification_email_template','QH_notification_email_log');")"

echo "[2/7] 备份线上文件和数据库"
[ ! -e "$BACKUP_DIR" ] || { echo "备份目录已存在" >&2; exit 73; }
[ "$(df -Pk "$BACKUP_ROOT" | awk 'NR==2 {print $4}')" -gt 153600 ] || {
  echo "备份磁盘可用空间不足 150MB，拒绝部署" >&2
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
chmod 600 "$BACKUP_DIR/database.sql" "$BACKUP_DIR/database.sql.sha256"

echo "[3/7] 原子部署文件"
deploy_file() {
  deploy_src="$1"
  deploy_dst="$2"
  deploy_tmp="${deploy_dst}.emailnotify.$$"
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
chmod 0755 "$SITE_DIR/database/migrate.sh" "$SITE_DIR/deploy/email-notification-deploy.sh"

echo "[4/7] 执行邮件通知专项迁移"
mysql --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" < "$SITE_DIR/database/migrations/20261004_email_notifications.sql"
MIGRATION_APPLIED=1

echo "[5/7] 清缓存并执行功能自检"
cd "$SITE_DIR"
"$PHP_BIN" think clear
"$PHP_BIN" -r 'require "vendor/autoload.php";$app=new \think\App();$app->initialize();\think\facade\Cache::delete("QH_AdminMenu");\think\facade\Cache::tag("QH_Menu")->clear();echo "menu cache cleared\n";'
for rel in "${FILES[@]}"; do cmp -s "$STAGE_DIR/$rel" "$SITE_DIR/$rel"; done
table_count="$(mysql --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('QH_notification_email_preference','QH_notification_email_template','QH_notification_email_log');")"
[ "$table_count" = "3" ]
config_count="$(mysql --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e "SELECT COUNT(*) FROM QH_config WHERE name='email_notification_enabled';")"
[ "$config_count" = "1" ]
menu_count="$(mysql --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e "SELECT COUNT(*) FROM QH_menu WHERE url='Set/emailNotification' AND power=1 AND status=1;")"
[ "$menu_count" = "1" ]
"$PHP_BIN" -r 'require "vendor/autoload.php";$app=new \think\App();$app->initialize();$u=\app\common\service\EmailNotificationService::catalog("user",2147483647);$a=\app\common\service\EmailNotificationService::catalog("admin",2147483647);if(count($u)!==10||count($a)!==4){exit(1);}$p=\app\common\service\EmailNotificationService::preview("plugin_audit","{{site_name}} {{plugin_name}}","<script>x</script><h2>{{review_status}}</h2>");if(strpos($p["html"],"<script")!==false){exit(2);}echo "email notification self-check passed\n";'

echo "[6/7] 线上 HTTP 健康检查"
for target in \
  "https://www.noteweb.top/" \
  "https://www.noteweb.top/user.php/MyInfo/index.html" \
  "https://www.noteweb.top/admin.php/Set/emailNotification.html"; do
  status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 "$target")"
  case "$status" in 200|301|302|303|403) ;; *) echo "线上 HTTP 健康检查失败：$target -> $status" >&2; exit 1 ;; esac
done

echo "[7/7] 检查近期致命错误"
find runtime/log -type f -mmin -10 -print0 2>/dev/null \
  | xargs -0 -r grep -Ei 'Fatal error|Parse error|Uncaught|Critical' \
  | tail -20 || true

FILES_CHANGED=0
echo "部署完成，备份目录：$BACKUP_DIR"
