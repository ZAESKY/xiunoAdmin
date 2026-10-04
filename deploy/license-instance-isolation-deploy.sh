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
case "$DEPLOYMENT_STAMP" in *[!0-9_]*|'') echo "invalid deployment stamp" >&2; exit 64 ;; esac

BACKUP_DIR="$BACKUP_ROOT/license_instance_isolation_$DEPLOYMENT_STAMP"
SNAP_LICENSE="SF_deploy_license_$DEPLOYMENT_STAMP"
SNAP_SITE="SF_deploy_site_$DEPLOYMENT_STAMP"
CLIENT_CNF=""
FILES_CHANGED=0
DATABASE_TOUCHED=0
SNAPSHOT_READY=0

# 这是一次性数据修复的公开标识，不包含完整授权 ID、授权码或密钥。
LICENSE_PREFIX="bda63b7c"
ONLINE_SOURCE_ID=121
LOCAL_SOURCE_ID=142
ONLINE_HOST="demo.idaily.top"
LOCAL_HOST="xiuno.demo.com"
LOCAL_OLD_SECRET_PREFIX="415d9256fb2201c9"
ONLINE_SECRET_PREFIX="1d278c2496d852ea"

FILES=(
  app/common/service/LicenseAuthService.php
  app/common/service/LicenseService.php
  database/migrate.sh
  database/migrations/20260816_p1_p3_license_v2.sql
)

cleanup_client() {
  if [ -n "$CLIENT_CNF" ] && [ -f "$CLIENT_CNF" ]; then unlink "$CLIENT_CNF"; fi
}

rollback() {
  status=$?
  trap - EXIT
  set +e
  if [ "$status" -ne 0 ]; then
    echo "部署失败，正在恢复授权实例隔离修改" >&2
    if [ "$FILES_CHANGED" -eq 1 ] && [ -f "$BACKUP_DIR/existing-files.txt" ]; then
      while IFS= read -r rel; do
        [ -n "$rel" ] || continue
        [ -f "$BACKUP_DIR/files/$rel" ] && cp -a "$BACKUP_DIR/files/$rel" "$SITE_DIR/$rel"
      done < "$BACKUP_DIR/existing-files.txt"
    fi
    if [ "$DATABASE_TOUCHED" -eq 1 ] && [ "$SNAPSHOT_READY" -eq 1 ] && [ -n "$CLIENT_CNF" ]; then
      "$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" <<SQL
DELETE live FROM \`SF_license_site\` AS live
INNER JOIN \`$SNAP_LICENSE\` AS lic ON lic.license_id = live.license_id;
INSERT INTO \`SF_license_site\` SELECT * FROM \`$SNAP_SITE\`;
DELETE live FROM \`SF_license\` AS live
INNER JOIN \`$SNAP_LICENSE\` AS old ON old.id = live.id;
INSERT INTO \`SF_license\` SELECT * FROM \`$SNAP_LICENSE\`;
SQL
    fi
    if [ "$SNAPSHOT_READY" -eq 1 ] && [ -n "$CLIENT_CNF" ]; then
      "$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" \
        -e "DROP TABLE IF EXISTS \`$SNAP_LICENSE\`, \`$SNAP_SITE\`" >/dev/null 2>&1 || true
    fi
    "$PHP_BIN" "$SITE_DIR/think" clear >/dev/null 2>&1 || true
    echo "自动回滚已执行；完整备份保留在 $BACKUP_DIR" >&2
  fi
  cleanup_client
  exit "$status"
}
trap rollback EXIT

[ -d "$SITE_DIR" ] && [ -f "$SITE_DIR/public/index.php" ] && [ -f "$SITE_DIR/.env" ] || {
  echo "线上目录校验失败" >&2; exit 73;
}
[ -d "$STAGE_DIR" ] && [ -f "$STAGE_DIR/SHA256SUMS" ] || {
  echo "暂存目录不完整" >&2; exit 73;
}
[ ! -e "$BACKUP_DIR" ] || { echo "备份目录已存在，拒绝覆盖" >&2; exit 73; }

echo "[1/8] 校验待部署文件"
cd "$STAGE_DIR"
sha256sum -c SHA256SUMS
for rel in "${FILES[@]}"; do
  [ -f "$STAGE_DIR/$rel" ] || { echo "缺少待部署文件：$rel" >&2; exit 73; }
  case "$rel" in *.php) "$PHP_BIN" -l "$STAGE_DIR/$rel" >/dev/null ;; *.sh) bash -n "$STAGE_DIR/$rel" ;; esac
done

echo "[2/8] 创建文件与数据库备份"
install -d -m 700 "$BACKUP_DIR/files"
touch "$BACKUP_DIR/existing-files.txt"
chmod 600 "$BACKUP_DIR/existing-files.txt"
for rel in "${FILES[@]}"; do
  [ -f "$SITE_DIR/$rel" ] || { echo "线上文件缺失：$rel" >&2; exit 73; }
  install -d "$BACKUP_DIR/files/$(dirname "$rel")"
  cp -a "$SITE_DIR/$rel" "$BACKUP_DIR/files/$rel"
  printf '%s\n' "$rel" >> "$BACKUP_DIR/existing-files.txt"
done

env_value() {
  "$PHP_BIN" -r '$v=parse_ini_file($argv[1],false,INI_SCANNER_RAW); echo trim((string)($v[$argv[2]]??""),"\"\047 ");' "$SITE_DIR/.env" "$1"
}
DB_HOST="$(env_value database_hostname)"; DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="$(env_value database_hostport)"; DB_PORT="${DB_PORT:-3306}"
DB_NAME="$(env_value database_database)"
DB_USER="$(env_value database_username)"
DB_PASS="$(env_value database_password)"
[ -n "$DB_NAME" ] && [ -n "$DB_USER" ]
CLIENT_CNF="$(mktemp)"; chmod 600 "$CLIENT_CNF"
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\ndefault-character-set=utf8mb4\n' \
  "$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASS" > "$CLIENT_CNF"
unset DB_PASS
MYSQL_BIN="$(command -v mysql || true)"; MYSQL_BIN="${MYSQL_BIN:-/www/server/mysql/bin/mysql}"
DUMP_BIN="$(command -v mysqldump || true)"; DUMP_BIN="${DUMP_BIN:-/www/server/mysql/bin/mysqldump}"
"$DUMP_BIN" --defaults-extra-file="$CLIENT_CNF" --single-transaction --quick --no-tablespaces \
  "$DB_NAME" SF_license SF_license_site SF_auth SF_license_event > "$BACKUP_DIR/affected_tables.sql"
[ "$(wc -c < "$BACKUP_DIR/affected_tables.sql")" -gt 1024 ]
grep -q 'Dump completed' "$BACKUP_DIR/affected_tables.sql"
sha256sum "$BACKUP_DIR/affected_tables.sql" > "$BACKUP_DIR/affected_tables.sql.sha256"
chmod 600 "$BACKUP_DIR/affected_tables.sql" "$BACKUP_DIR/affected_tables.sql.sha256"

echo "[3/8] 验证错误合并记录的唯一性"
license_rows="$("$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e \
  "SELECT COUNT(*) FROM SF_license WHERE license_id LIKE '${LICENSE_PREFIX}%' AND source_auth_id=$LOCAL_SOURCE_ID AND bound_host='$ONLINE_HOST' AND secret_hash LIKE '${LOCAL_OLD_SECRET_PREFIX}%' AND previous_secret_hash LIKE '${ONLINE_SECRET_PREFIX}%'")"
source_rows="$("$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e \
  "SELECT COUNT(*) FROM SF_auth WHERE (id=$ONLINE_SOURCE_ID AND LOWER(auth_info)='$ONLINE_HOST') OR (id=$LOCAL_SOURCE_ID AND LOWER(auth_info)='$LOCAL_HOST')")"
duplicate_code="$("$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e \
  "SELECT COUNT(DISTINCT authcode) FROM SF_auth WHERE id IN ($ONLINE_SOURCE_ID,$LOCAL_SOURCE_ID)")"
source_taken="$("$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e \
  "SELECT COUNT(*) FROM SF_license WHERE source_auth_id=$ONLINE_SOURCE_ID")"
previous_rows="$("$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e \
  "SELECT COUNT(*) FROM SF_license WHERE previous_secret_hash<>'' OR previous_secret_enc<>'' OR previous_secret_expires_at IS NOT NULL")"
stale_sites="$("$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e \
  "SELECT COUNT(*) FROM SF_license_site s JOIN SF_license l ON l.license_id=s.license_id WHERE l.license_id LIKE '${LICENSE_PREFIX}%' AND LOWER(s.host)='$LOCAL_HOST' AND s.status=0")"
[ "$license_rows" = 1 ] && [ "$source_rows" = 2 ] && [ "$duplicate_code" = 1 ] \
  && [ "$source_taken" = 0 ] && [ "$stale_sites" -ge 1 ] || {
    echo "线上数据与预期修复前状态不一致，拒绝自动修改" >&2; exit 1;
  }
[ "$previous_rows" = 1 ] || { echo "存在预期外的兼容密钥记录，拒绝批量清理" >&2; exit 1; }

"$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" <<SQL
CREATE TABLE \`$SNAP_LICENSE\` LIKE \`SF_license\`;
INSERT INTO \`$SNAP_LICENSE\` SELECT * FROM \`SF_license\` WHERE license_id LIKE '${LICENSE_PREFIX}%';
CREATE TABLE \`$SNAP_SITE\` LIKE \`SF_license_site\`;
INSERT INTO \`$SNAP_SITE\` SELECT s.* FROM \`SF_license_site\` s INNER JOIN \`$SNAP_LICENSE\` l ON l.license_id=s.license_id;
SQL
SNAPSHOT_READY=1

echo "[4/8] 拆分授权实例并撤销旧兼容密钥"
DATABASE_TOUCHED=1
"$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" <<SQL
START TRANSACTION;
UPDATE \`SF_license\`
SET source_auth_id=$ONLINE_SOURCE_ID,
    user_id=(SELECT userid FROM \`SF_auth\` WHERE id=$ONLINE_SOURCE_ID),
    secret_hash=previous_secret_hash,
    secret_enc=previous_secret_enc,
    previous_secret_hash='',
    previous_secret_enc='',
    previous_secret_expires_at=NULL,
    updated_at=NOW()
WHERE license_id LIKE '${LICENSE_PREFIX}%'
  AND source_auth_id=$LOCAL_SOURCE_ID
  AND bound_host='$ONLINE_HOST';
DELETE s FROM \`SF_license_site\` s
INNER JOIN \`SF_license\` l ON l.license_id=s.license_id
WHERE l.license_id LIKE '${LICENSE_PREFIX}%'
  AND LOWER(s.host)='$LOCAL_HOST'
  AND s.status=0;
UPDATE \`SF_license\`
SET previous_secret_hash='', previous_secret_enc='', previous_secret_expires_at=NULL
WHERE previous_secret_hash<>'' OR previous_secret_enc<>'' OR previous_secret_expires_at IS NOT NULL;
COMMIT;
SQL

echo "[5/8] 原子部署授权实例隔离代码"
deploy_file() {
  src="$1"; dst="$2"; tmp="${dst}.instance-isolation.$$"
  install -m 0644 "$src" "$tmp"
  chown --reference="$dst" "$tmp"; chmod --reference="$dst" "$tmp"
  mv -f "$tmp" "$dst"
}
FILES_CHANGED=1
for rel in "${FILES[@]}"; do deploy_file "$STAGE_DIR/$rel" "$SITE_DIR/$rel"; done

echo "[6/8] 校验代码与授权边界"
cd "$SITE_DIR"
for rel in "${FILES[@]}"; do
  cmp -s "$STAGE_DIR/$rel" "$SITE_DIR/$rel"
  case "$rel" in *.php) "$PHP_BIN" -l "$SITE_DIR/$rel" >/dev/null ;; *.sh) bash -n "$SITE_DIR/$rel" ;; esac
done
"$PHP_BIN" think clear
repaired="$("$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e \
  "SELECT COUNT(*) FROM SF_license WHERE license_id LIKE '${LICENSE_PREFIX}%' AND source_auth_id=$ONLINE_SOURCE_ID AND bound_host='$ONLINE_HOST' AND secret_hash LIKE '${ONLINE_SECRET_PREFIX}%' AND previous_secret_hash=''")"
stale_after="$("$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e \
  "SELECT COUNT(*) FROM SF_license_site s JOIN SF_license l ON l.license_id=s.license_id WHERE l.license_id LIKE '${LICENSE_PREFIX}%' AND LOWER(s.host)='$LOCAL_HOST'")"
previous_nonempty="$("$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e \
  "SELECT COUNT(*) FROM SF_license WHERE previous_secret_hash<>'' OR previous_secret_enc<>'' OR previous_secret_expires_at IS NOT NULL")"
[ "$repaired" = 1 ] && [ "$stale_after" = 0 ] && [ "$previous_nonempty" = 0 ]
grep -q 'function selectActivationSource' app/common/service/LicenseService.php
grep -q "'site_not_bound'" app/common/service/LicenseAuthService.php
! grep -q 'credential_fallback' app/common/service/LicenseAuthService.php

echo "[7/8] 最小化线上健康检查"
nginx -t >/dev/null 2>&1
home_status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 https://www.noteweb.top/)"
api_status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 -X POST https://www.noteweb.top/api.php/v2/license/status)"
case "$home_status" in 200|301|302|403) ;; *) echo "首页 HTTP $home_status" >&2; exit 1 ;; esac
case "$api_status" in 200|400|401|403|405|422|429) ;; *) echo "授权 API HTTP $api_status" >&2; exit 1 ;; esac

echo "[8/8] 清理临时快照并完成部署"
"$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" \
  -e "DROP TABLE \`$SNAP_LICENSE\`, \`$SNAP_SITE\`"
SNAPSHOT_READY=0; DATABASE_TOUCHED=0; FILES_CHANGED=0
printf 'backup=%s\nhome_http=%s\napi_http=%s\nrepaired=%s\nremoved_stale_sites=%s\n' \
  "$BACKUP_DIR" "$home_status" "$api_status" "$repaired" "$stale_sites"
sha256sum "${FILES[@]}"

cleanup_client
trap - EXIT
