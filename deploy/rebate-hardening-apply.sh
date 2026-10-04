#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -ne 1 ]; then
  echo "usage: $0 STAGING_DIR" >&2
  exit 2
fi

SITE_DIR="/www/wwwroot/admin.noteweb.top/releases/20260816_230630"
PHP_BIN="/www/server/php/82/bin/php"
STAGING_DIR="$1"
MIGRATION="database/migrations/20261002_rebate_withdrawal_hardening.sql"

test -d "$SITE_DIR"
test -d "$STAGING_DIR"
test -f "$STAGING_DIR/$MIGRATION"
cd "$SITE_DIR"

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

while IFS= read -r -d '' php_file; do
  "$PHP_BIN" -l "$php_file" >/dev/null
done < <(find "$STAGING_DIR/app" "$STAGING_DIR/config" -type f -name '*.php' -print0)

client_cnf="$(mktemp)"
trap 'test ! -e "$client_cnf" || unlink "$client_cnf"' EXIT
chmod 600 "$client_cnf"
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\ndefault-character-set=utf8mb4\n' \
  "$db_host" "$db_port" "$db_user" "$db_pass" > "$client_cnf"

mysql_bin="$(command -v mysql || true)"
if [ -z "$mysql_bin" ]; then
  mysql_bin="/www/server/mysql/bin/mysql"
fi

# The database backup must already exist before this script is called.
"$mysql_bin" --defaults-extra-file="$client_cnf" "$db_name" < "$STAGING_DIR/$MIGRATION"

files=(
  app/common/service/WithdrawableBalanceService.php
  app/common/service/RebateRiskService.php
  app/common/service/RebateSettlementService.php
  app/pay/service/CommonService.php
  app/common/controller/CommonBase.php
  app/user/controller/Rebate.php
  app/user/controller/Withdraw.php
  app/admin/controller/Order.php
  app/admin/config/site.php
  app/pay/service/PluginPayCallbackService.php
  app/api/service/PluginApiService.php
  app/user/service/UserPluginService.php
  app/user/service/RebateService.php
  app/user/model/RebateModel.php
  app/user/view/rebate/index.html
  app/user/view/withdraw/index.html
  app/command/RebateSettle.php
  config/console.php
  database/migrations/20261002_rebate_withdrawal_hardening.sql
  database/migrations/20261002_rebate_withdrawal_hardening_rollback.sql
  app/SF_Auth.sql
  app/SF_rebate_migration.sql
  database/migrations/20260816_legacy_429_bridge_tables.sql
  app/command/LegacyBridge.php
  database/migrate.sh
)

for file in "${files[@]}"; do
  test -f "$STAGING_DIR/$file"
  mkdir -p "$SITE_DIR/$(dirname "$file")"
  cp -a "$STAGING_DIR/$file" "$SITE_DIR/$file"
  chown www:www "$SITE_DIR/$file"
done

chmod 600 "$SITE_DIR/.env"
chmod 755 "$SITE_DIR/database/migrate.sh"

while IFS= read -r -d '' php_file; do
  "$PHP_BIN" -l "$php_file" >/dev/null
done < <(find \
  "$SITE_DIR/app/common/service/WithdrawableBalanceService.php" \
  "$SITE_DIR/app/common/service/RebateRiskService.php" \
  "$SITE_DIR/app/common/service/RebateSettlementService.php" \
  "$SITE_DIR/app/pay/service/CommonService.php" \
  "$SITE_DIR/app/common/controller/CommonBase.php" \
  "$SITE_DIR/app/user/controller/Rebate.php" \
  "$SITE_DIR/app/user/controller/Withdraw.php" \
  "$SITE_DIR/app/admin/controller/Order.php" \
  "$SITE_DIR/app/admin/config/site.php" \
  "$SITE_DIR/app/pay/service/PluginPayCallbackService.php" \
  "$SITE_DIR/app/api/service/PluginApiService.php" \
  "$SITE_DIR/app/user/service/UserPluginService.php" \
  "$SITE_DIR/app/user/service/RebateService.php" \
  "$SITE_DIR/app/user/model/RebateModel.php" \
  "$SITE_DIR/app/command/RebateSettle.php" \
  "$SITE_DIR/config/console.php" \
  "$SITE_DIR/app/command/LegacyBridge.php" \
  -type f -name '*.php' -print0)

"$PHP_BIN" think clear >/dev/null
"$PHP_BIN" think sf:rebate-settle --limit=500

field_count="$("$mysql_bin" --defaults-extra-file="$client_cnf" "$db_name" -N -B -e \
  "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='SF_user' AND COLUMN_NAME='withdrawable_balance') OR (TABLE_NAME='SF_rebate_record' AND COLUMN_NAME IN ('rebate_base_amount','settle_at','settled_at','risk_reason')));")"
trigger_count="$("$mysql_bin" --defaults-extra-file="$client_cnf" "$db_name" -N -B -e \
  "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME LIKE 'SF_user_withdrawable_before_%';")"
config_count="$("$mysql_bin" --defaults-extra-file="$client_cnf" "$db_name" -N -B -e \
  "SELECT COUNT(*) FROM SF_config WHERE name IN ('rebate_hold_days','rebate_pair_daily_count','rebate_daily_limit','rebate_monthly_limit');")"

test "$field_count" = "5"
test "$trigger_count" = "2"
test "$config_count" = "4"
printf 'deployment=ok\nfields=%s\ntriggers=%s\nconfigs=%s\n' \
  "$field_count" "$trigger_count" "$config_count"
