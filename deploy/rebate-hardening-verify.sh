#!/usr/bin/env bash
set -euo pipefail

SITE_DIR="/www/wwwroot/admin.noteweb.top/releases/20260816_230630"
BACKUP_ROOT="/www/backup/admin.noteweb.top"
PHP_BIN="/www/server/php/82/bin/php"
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

client_cnf="$(mktemp)"
trap 'test ! -e "$client_cnf" || unlink "$client_cnf"' EXIT
chmod 600 "$client_cnf"
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\ndefault-character-set=utf8mb4\n' \
  "$db_host" "$db_port" "$db_user" "$db_pass" > "$client_cnf"

mysql_bin="$(command -v mysql || true)"
if [ -z "$mysql_bin" ]; then
  mysql_bin="/www/server/mysql/bin/mysql"
fi

"$PHP_BIN" think list | grep -q 'qh:rebate-settle'
"$PHP_BIN" think qh:rebate-settle --limit=500

field_count="$("$mysql_bin" --defaults-extra-file="$client_cnf" "$db_name" -N -B -e \
  "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='QH_user' AND COLUMN_NAME='withdrawable_balance') OR (TABLE_NAME='QH_rebate_record' AND COLUMN_NAME IN ('rebate_base_amount','settle_at','settled_at','risk_reason')));")"
trigger_count="$("$mysql_bin" --defaults-extra-file="$client_cnf" "$db_name" -N -B -e \
  "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME LIKE 'QH_user_withdrawable_before_%';")"
config_count="$("$mysql_bin" --defaults-extra-file="$client_cnf" "$db_name" -N -B -e \
  "SELECT COUNT(*) FROM QH_config WHERE name IN ('rebate_hold_days','rebate_pair_daily_count','rebate_daily_limit','rebate_monthly_limit');")"
invalid_balance_count="$("$mysql_bin" --defaults-extra-file="$client_cnf" "$db_name" -N -B -e \
  "SELECT COUNT(*) FROM QH_user WHERE withdrawable_balance < 0 OR withdrawable_balance > balance;")"
rebate_statuses="$("$mysql_bin" --defaults-extra-file="$client_cnf" "$db_name" -N -B -e \
  "SELECT CONCAT(status, ':', COUNT(*)) FROM QH_rebate_record GROUP BY status ORDER BY status;" | paste -sd, -)"
withdrawable_summary="$("$mysql_bin" --defaults-extra-file="$client_cnf" "$db_name" -N -B -e \
  "SELECT CONCAT(COUNT(*), ':', COALESCE(FORMAT(SUM(withdrawable_balance),2), '0.00')) FROM QH_user WHERE withdrawable_balance > 0;")"

test "$field_count" = "5"
test "$trigger_count" = "2"
test "$config_count" = "4"
test "$invalid_balance_count" = "0"

latest_backup="$(find "$BACKUP_ROOT" -maxdepth 1 -type d -name '*_rebate_hardening' -print | sort | tail -1)"
test -n "$latest_backup"
sha256sum -c "$latest_backup/database.sql.sha256" >/dev/null

env_mode="$(stat -c '%a' .env)"
test "$env_mode" = "600"

home_status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 https://admin.noteweb.top/)"
user_status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 https://admin.noteweb.top/user.php/Login/index.html)"
case "$home_status" in 200|301|302) ;; *) exit 20 ;; esac
case "$user_status" in 200|301|302) ;; *) exit 21 ;; esac

trust_value="$(cd /www/server/panel && PYTHONPATH=/www/server/panel/class /www/server/panel/pyenv/bin/python -c '
import os, subprocess, public
p = public.M("config").where("id=?", (1,)).getField("mysql_root")
e = os.environ.copy()
e["MYSQL_PWD"] = p
print(subprocess.check_output([
    "mysql", "-uroot", "-N", "-B", "-e",
    "SELECT @@log_bin_trust_function_creators;"
], env=e, text=True).strip())
')"
test "$trust_value" = "0"

printf 'verification=ok\nfields=%s\ntriggers=%s\nconfigs=%s\ninvalid_balances=%s\nrebate_statuses=%s\nwithdrawable_accounts_and_total=%s\nenv_mode=%s\nhttp=%s,%s\ntrust_function_creators=%s\nbackup=%s\n' \
  "$field_count" "$trigger_count" "$config_count" "$invalid_balance_count" \
  "${rebate_statuses:-none}" "$withdrawable_summary" "$env_mode" \
  "$home_status" "$user_status" "$trust_value" "$latest_backup"
