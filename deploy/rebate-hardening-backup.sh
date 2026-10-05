#!/usr/bin/env bash
set -euo pipefail

SITE_DIR="/www/wwwroot/admin.noteweb.top/releases/20260816_230630"
BACKUP_ROOT="/www/backup/admin.noteweb.top"
PHP_BIN="/www/server/php/82/bin/php"

stamp="$(date +%Y%m%d_%H%M%S)"
backup_dir="${BACKUP_ROOT}/${stamp}_rebate_hardening"
mkdir -p "$backup_dir/files"
chmod 700 "$backup_dir"
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

client_cnf="$(mktemp)"
trap 'test ! -e "$client_cnf" || unlink "$client_cnf"' EXIT
chmod 600 "$client_cnf"
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\ndefault-character-set=utf8mb4\n' \
  "$db_host" "$db_port" "$db_user" "$db_pass" > "$client_cnf"

dump_bin="$(command -v mysqldump || true)"
if [ -z "$dump_bin" ]; then
  dump_bin="/www/server/mysql/bin/mysqldump"
fi
"$dump_bin" --defaults-extra-file="$client_cnf" \
  --single-transaction --quick --routines --triggers --events --no-tablespaces \
  --default-character-set=utf8mb4 "$db_name" > "$backup_dir/database.sql"
test "$(wc -c < "$backup_dir/database.sql")" -gt 1024
grep -q 'Table structure for table `QH_user`' "$backup_dir/database.sql"

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
  app/QH_Auth.sql
  app/QH_rebate_migration.sql
  database/migrations/20260816_legacy_429_bridge_tables.sql
  app/command/LegacyBridge.php
  database/migrate.sh
)

backed_up=0
for file in "${files[@]}"; do
  if [ -e "$file" ]; then
    mkdir -p "$backup_dir/files/$(dirname "$file")"
    cp -a "$file" "$backup_dir/files/$file"
    backed_up=$((backed_up + 1))
  fi
done

cp -a .env "$backup_dir/env.before"
chmod 600 "$backup_dir/env.before" "$backup_dir/database.sql"
sha256sum "$backup_dir/database.sql" > "$backup_dir/database.sql.sha256"

printf 'backup_dir=%s\ndatabase_bytes=%s\nexisting_files_backed_up=%s\n' \
  "$backup_dir" "$(wc -c < "$backup_dir/database.sql")" "$backed_up"
