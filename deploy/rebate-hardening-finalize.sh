#!/usr/bin/env bash
set -euo pipefail

SITE_DIR="/www/wwwroot/admin.noteweb.top/releases/20260816_230630"
BACKUP_ROOT="/www/backup/admin.noteweb.top"
MANIFEST="/tmp/sf-rebate-hardening-20261002.sha256"
VERIFY_SCRIPT="/tmp/rebate-hardening-verify.sh"

cd "$SITE_DIR"
sha256sum -c "$MANIFEST"

latest_backup="$(find "$BACKUP_ROOT" -maxdepth 1 -type d -name '*_rebate_hardening' -print | sort | tail -1)"
test -n "$latest_backup"
crontab -l > "$latest_backup/crontab.before" 2>/dev/null || :

if ! crontab -l 2>/dev/null | grep -q 'sf-rebate-settle-managed'; then
  cron_tmp="$(mktemp)"
  crontab -l > "$cron_tmp" 2>/dev/null || :
  printf '%s\n' \
    '*/10 * * * * cd /www/wwwroot/admin.noteweb.top/releases/20260816_230630 && /www/server/php/82/bin/php think sf:rebate-settle --limit=500 >/dev/null 2>&1 # sf-rebate-settle-managed' \
    >> "$cron_tmp"
  crontab "$cron_tmp"
  unlink "$cron_tmp"
fi

chmod 700 "$VERIFY_SCRIPT"
"$VERIFY_SCRIPT"

unlink "$MANIFEST"
unlink "$VERIFY_SCRIPT"
