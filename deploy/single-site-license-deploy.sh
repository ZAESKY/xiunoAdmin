#!/usr/bin/env bash
set -euo pipefail

SITE_DIR="/www/wwwroot/admin.noteweb.top/releases/20260816_230630"
PHP_BIN="/www/server/php/82/bin/php"
BACKUP_ROOT="/www/backup/admin.noteweb.top"

if [ "$#" -ne 2 ]; then
  echo "usage: $0 <staging-directory> <deployment-stamp>" >&2
  exit 64
fi

STAGE_DIR="$1"
DEPLOYMENT_STAMP="$2"
case "$DEPLOYMENT_STAMP" in
  *[!0-9_]*|'') echo "deployment stamp must contain digits and underscores only" >&2; exit 64 ;;
esac

BACKUP_DIR="$BACKUP_ROOT/single_site_license_$DEPLOYMENT_STAMP"
SNAP_LICENSE="QH_deploy_license_$DEPLOYMENT_STAMP"
SNAP_SITE="QH_deploy_site_$DEPLOYMENT_STAMP"
CLIENT_CNF=""
FILES_CHANGED=0
DATABASE_TOUCHED=0
SNAPSHOT_READY=0

FILES=(
  app/common/service/LicenseService.php
  app/user/service/AuthService.php
  database/migrate.sh
  database/migrations/20260816_p1_p3_license_v2.sql
  database/migrations/20261002_single_site_license.sql
  database/migrations/20261002_single_site_license_rollback.sql
)

cleanup_client() {
  if [ -n "$CLIENT_CNF" ] && [ -f "$CLIENT_CNF" ]; then
    unlink "$CLIENT_CNF"
  fi
}

rollback() {
  status=$?
  trap - EXIT
  set +e
  if [ "$status" -ne 0 ]; then
    echo "部署失败，正在恢复本次修改的文件和授权绑定字段" >&2
    if [ "$FILES_CHANGED" -eq 1 ] && [ -f "$BACKUP_DIR/existing-files.txt" ]; then
      while IFS= read -r rel; do
        [ -n "$rel" ] || continue
        if [ -f "$BACKUP_DIR/files/$rel" ]; then
          install -d "$(dirname "$SITE_DIR/$rel")"
          cp -a "$BACKUP_DIR/files/$rel" "$SITE_DIR/$rel"
        fi
      done < "$BACKUP_DIR/existing-files.txt"
      if [ -f "$BACKUP_DIR/new-files.txt" ]; then
        while IFS= read -r rel; do
          case "$rel" in
            database/migrations/20261002_single_site_license.sql|database/migrations/20261002_single_site_license_rollback.sql)
              [ -f "$SITE_DIR/$rel" ] && unlink "$SITE_DIR/$rel"
              ;;
          esac
        done < "$BACKUP_DIR/new-files.txt"
      fi
    fi

    if [ "$DATABASE_TOUCHED" -eq 1 ] && [ "$SNAPSHOT_READY" -eq 1 ] && [ -n "$CLIENT_CNF" ]; then
      "$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" <<SQL
UPDATE \`QH_license\` AS live
INNER JOIN \`$SNAP_LICENSE\` AS old ON old.id = live.id
SET live.max_sites = old.max_sites,
    live.allow_cross_root = old.allow_cross_root,
    live.bound_host = old.bound_host,
    live.updated_at = old.updated_at;
UPDATE \`QH_license_site\` AS live
INNER JOIN \`$SNAP_SITE\` AS old ON old.id = live.id
SET live.status = old.status,
    live.role = old.role,
    live.last_seen_at = old.last_seen_at;
ALTER TABLE \`QH_license\`
  MODIFY \`max_sites\` tinyint(4) NOT NULL DEFAULT 2 COMMENT '旧版双站点授权上限',
  MODIFY \`allow_cross_root\` tinyint(1) NOT NULL DEFAULT 0 COMMENT '旧版跨根域控制字段';
DROP TABLE \`$SNAP_LICENSE\`, \`$SNAP_SITE\`;
SQL
      if [ "$?" -eq 0 ]; then
        echo "授权绑定字段已恢复；完整数据库备份仍保留在 $BACKUP_DIR/database.sql" >&2
      else
        echo "自动恢复数据库字段失败，请使用 $BACKUP_DIR/database.sql 和快照表人工恢复" >&2
      fi
    fi
    "$PHP_BIN" "$SITE_DIR/think" clear >/dev/null 2>&1 || true
  fi
  cleanup_client
  exit "$status"
}
trap rollback EXIT

if [ ! -d "$SITE_DIR" ] || [ ! -f "$SITE_DIR/public/index.php" ] || [ ! -f "$SITE_DIR/.env" ]; then
  echo "线上目录校验失败，拒绝部署" >&2
  exit 73
fi
if [ ! -d "$STAGE_DIR" ] || [ ! -f "$STAGE_DIR/SHA256SUMS" ]; then
  echo "暂存目录不完整，拒绝部署" >&2
  exit 73
fi

echo "[1/8] 校验待部署文件"
cd "$STAGE_DIR"
sha256sum -c SHA256SUMS
for rel in "${FILES[@]}"; do
  [ -f "$STAGE_DIR/$rel" ] || { echo "缺少待部署文件：$rel" >&2; exit 73; }
  case "$rel" in
    *.php) "$PHP_BIN" -l "$STAGE_DIR/$rel" >/dev/null ;;
    *.sh) bash -n "$STAGE_DIR/$rel" ;;
  esac
done

echo "[2/8] 创建代码与完整数据库备份"
if [ -e "$BACKUP_DIR" ]; then
  echo "备份目录已存在，拒绝覆盖：$BACKUP_DIR" >&2
  exit 73
fi
install -d -m 700 "$BACKUP_DIR/files"
touch "$BACKUP_DIR/existing-files.txt" "$BACKUP_DIR/new-files.txt"
chmod 600 "$BACKUP_DIR/existing-files.txt" "$BACKUP_DIR/new-files.txt"
for rel in "${FILES[@]}"; do
  if [ -f "$SITE_DIR/$rel" ]; then
    install -d "$BACKUP_DIR/files/$(dirname "$rel")"
    cp -a "$SITE_DIR/$rel" "$BACKUP_DIR/files/$rel"
    printf '%s\n' "$rel" >> "$BACKUP_DIR/existing-files.txt"
  else
    printf '%s\n' "$rel" >> "$BACKUP_DIR/new-files.txt"
  fi
done

env_value() {
  "$PHP_BIN" -r '
    $values=parse_ini_file($argv[1], false, INI_SCANNER_RAW);
    echo trim((string)($values[$argv[2]]??""), "\"\047 ");
  ' "$SITE_DIR/.env" "$1"
}
DB_HOST="$(env_value database_hostname)"; DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="$(env_value database_hostport)"; DB_PORT="${DB_PORT:-3306}"
DB_NAME="$(env_value database_database)"
DB_USER="$(env_value database_username)"
DB_PASS="$(env_value database_password)"
[ -n "$DB_NAME" ] && [ -n "$DB_USER" ]
CLIENT_CNF="$(mktemp)"
chmod 600 "$CLIENT_CNF"
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\ndefault-character-set=utf8mb4\n' \
  "$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASS" > "$CLIENT_CNF"
unset DB_PASS
MYSQL_BIN="$(command -v mysql || true)"; MYSQL_BIN="${MYSQL_BIN:-/www/server/mysql/bin/mysql}"
DUMP_BIN="$(command -v mysqldump || true)"; DUMP_BIN="${DUMP_BIN:-/www/server/mysql/bin/mysqldump}"
"$DUMP_BIN" --defaults-extra-file="$CLIENT_CNF" --single-transaction --quick \
  --routines --triggers --events --no-tablespaces --default-character-set=utf8mb4 \
  "$DB_NAME" > "$BACKUP_DIR/database.sql"
[ "$(wc -c < "$BACKUP_DIR/database.sql")" -gt 1024 ]
grep -q 'Table structure for table `QH_license`' "$BACKUP_DIR/database.sql"
grep -q 'Dump completed' "$BACKUP_DIR/database.sql"
sha256sum "$BACKUP_DIR/database.sql" > "$BACKUP_DIR/database.sql.sha256"
chmod 600 "$BACKUP_DIR/database.sql" "$BACKUP_DIR/database.sql.sha256"

echo "[3/8] 统计影响范围并创建精确回滚快照"
pre_licenses="$($MYSQL_BIN --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e 'SELECT COUNT(*) FROM QH_license')"
pre_multi="$($MYSQL_BIN --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e 'SELECT COUNT(*) FROM (SELECT license_id FROM QH_license_site WHERE status=1 GROUP BY license_id HAVING COUNT(*)>1) AS grouped')"
pre_secondary="$($MYSQL_BIN --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e "SELECT COUNT(*) FROM QH_license_site WHERE status=1 AND role<>'primary'")"
printf 'licenses=%s\nlicenses_with_multiple_active_rows=%s\nactive_secondary_rows=%s\n' \
  "$pre_licenses" "$pre_multi" "$pre_secondary" | tee "$BACKUP_DIR/pre_migration_counts.txt"
"$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" <<SQL
CREATE TABLE \`$SNAP_LICENSE\` LIKE \`QH_license\`;
INSERT INTO \`$SNAP_LICENSE\` SELECT * FROM \`QH_license\`;
CREATE TABLE \`$SNAP_SITE\` LIKE \`QH_license_site\`;
INSERT INTO \`$SNAP_SITE\` SELECT * FROM \`QH_license_site\`;
SQL
SNAPSHOT_READY=1

echo "[4/8] 执行单站点授权迁移"
DATABASE_TOUCHED=1
"$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" \
  < "$STAGE_DIR/database/migrations/20261002_single_site_license.sql"

echo "[5/8] 原子部署授权服务文件"
deploy_file() {
  src="$1"; dst="$2"; tmp="${dst}.single-site.$$"
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
for rel in "${FILES[@]}"; do
  deploy_file "$STAGE_DIR/$rel" "$SITE_DIR/$rel"
done

echo "[6/8] 清除缓存并验证代码和数据库"
cd "$SITE_DIR"
for rel in "${FILES[@]}"; do
  cmp -s "$STAGE_DIR/$rel" "$SITE_DIR/$rel"
  case "$rel" in
    *.php) "$PHP_BIN" -l "$SITE_DIR/$rel" >/dev/null ;;
    *.sh) bash -n "$SITE_DIR/$rel" ;;
  esac
done
"$PHP_BIN" think clear
invalid_policy="$($MYSQL_BIN --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e 'SELECT COUNT(*) FROM QH_license WHERE max_sites<>1 OR allow_cross_root<>0')"
multi_active="$($MYSQL_BIN --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e 'SELECT COUNT(*) FROM (SELECT license_id FROM QH_license_site WHERE status=1 GROUP BY license_id HAVING COUNT(*)>1) AS grouped')"
active_secondary="$($MYSQL_BIN --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e "SELECT COUNT(*) FROM QH_license_site WHERE status=1 AND role<>'primary'")"
column_default="$($MYSQL_BIN --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e "SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='QH_license' AND COLUMN_NAME='max_sites'")"
[ "$invalid_policy" = 0 ] && [ "$multi_active" = 0 ] && [ "$active_secondary" = 0 ] && [ "$column_default" = 1 ]
grep -q 'MAX_SITES_PER_LICENSE = 1' app/common/service/LicenseService.php
! grep -q '第二个站点需与主域名同根域' app/common/service/LicenseService.php

echo "[7/8] 最小化线上健康检查"
nginx -t >/dev/null 2>&1
home_status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 https://www.noteweb.top/)"
api_status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 -X POST https://www.noteweb.top/api.php/v2/license/status)"
case "$home_status" in 200|301|302|403) ;; *) echo "首页 HTTP $home_status" >&2; exit 1;; esac
case "$api_status" in 200|400|401|403|405|422|429) ;; *) echo "授权 API HTTP $api_status" >&2; exit 1;; esac

echo "[8/8] 清理数据库内临时快照并完成部署"
"$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" \
  -e "DROP TABLE \`$SNAP_LICENSE\`, \`$SNAP_SITE\`"
SNAPSHOT_READY=0
DATABASE_TOUCHED=0
FILES_CHANGED=0
printf 'backup=%s\nhome_http=%s\napi_http=%s\nlicenses=%s\nprevious_multi_bindings=%s\nprevious_active_secondary=%s\n' \
  "$BACKUP_DIR" "$home_status" "$api_status" "$pre_licenses" "$pre_multi" "$pre_secondary"
sha256sum "${FILES[@]}"

cleanup_client
trap - EXIT
