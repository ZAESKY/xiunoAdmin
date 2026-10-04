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

BACKUP_DIR="$BACKUP_ROOT/beta_channel_$DEPLOYMENT_STAMP"
FILES_CHANGED=0
CLIENT_CNF=""
FILES=(
  app/common/service/LicenseService.php
  app/api/service/LicenseV2Service.php
  app/api/service/UpdateV2Service.php
  app/api/controller/UpdateV2.php
  app/api/route/route.php
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
  if [ "$status" -ne 0 ] && [ "$FILES_CHANGED" -eq 1 ]; then
    echo "部署失败，正在恢复本次修改的授权系统文件" >&2
    for rel in "${FILES[@]}"; do
      if [ -f "$BACKUP_DIR/files/$rel" ]; then
        cp -a "$BACKUP_DIR/files/$rel" "$SITE_DIR/$rel"
      fi
    done
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
if [ -e "$BACKUP_DIR" ]; then
  echo "备份目录已存在，拒绝覆盖：$BACKUP_DIR" >&2
  exit 73
fi

echo "[1/6] 校验待部署文件"
cd "$STAGE_DIR"
sha256sum -c SHA256SUMS
for rel in "${FILES[@]}"; do
  [ -f "$STAGE_DIR/$rel" ] || { echo "缺少待部署文件：$rel" >&2; exit 73; }
  "$PHP_BIN" -l "$STAGE_DIR/$rel" >/dev/null
done

echo "[2/6] 备份线上文件和受影响授权表"
install -d -m 700 "$BACKUP_DIR/files"
for rel in "${FILES[@]}"; do
  [ -f "$SITE_DIR/$rel" ] || { echo "线上文件缺失：$rel" >&2; exit 73; }
  install -d "$BACKUP_DIR/files/$(dirname "$rel")"
  cp -a "$SITE_DIR/$rel" "$BACKUP_DIR/files/$rel"
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
DUMP_BIN="$(command -v mysqldump || true)"; DUMP_BIN="${DUMP_BIN:-/www/server/mysql/bin/mysqldump}"
"$DUMP_BIN" --defaults-extra-file="$CLIENT_CNF" --single-transaction --quick --no-tablespaces \
  "$DB_NAME" SF_license SF_license_site SF_auth SF_release > "$BACKUP_DIR/affected_tables.sql"
[ "$(wc -c < "$BACKUP_DIR/affected_tables.sql")" -gt 1024 ]
grep -q 'Dump completed' "$BACKUP_DIR/affected_tables.sql"
sha256sum "$BACKUP_DIR/affected_tables.sql" > "$BACKUP_DIR/affected_tables.sql.sha256"
chmod 600 "$BACKUP_DIR/affected_tables.sql" "$BACKUP_DIR/affected_tables.sql.sha256"

echo "[3/6] 原子部署授权服务文件"
deploy_file() {
  src="$1"; dst="$2"; tmp="${dst}.beta-channel.$$"
  install -m 0644 "$src" "$tmp"
  chown --reference="$dst" "$tmp"
  chmod --reference="$dst" "$tmp"
  mv -f "$tmp" "$dst"
}
FILES_CHANGED=1
for rel in "${FILES[@]}"; do
  deploy_file "$STAGE_DIR/$rel" "$SITE_DIR/$rel"
done

echo "[4/6] 清除缓存并复验文件"
cd "$SITE_DIR"
for rel in "${FILES[@]}"; do
  cmp -s "$STAGE_DIR/$rel" "$SITE_DIR/$rel"
  "$PHP_BIN" -l "$SITE_DIR/$rel" >/dev/null
done
"$PHP_BIN" think clear

echo "[5/6] 最小化线上接口验证"
nginx -t >/dev/null 2>&1
home_status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 https://www.noteweb.top/)"
stable_response="$BACKUP_DIR/stable_probe.response"
stable_http="$(curl -sS -o "$stable_response" -w '%{http_code}' --max-time 20 -X POST \
  -H 'Content-Type: application/json' --data '{}' https://www.noteweb.top/api.php/v2/update/stable)"
if [ "$stable_http" = 200 ]; then
  "$PHP_BIN" -r '$j=json_decode((string)file_get_contents($argv[1]),true); if(!is_array($j)||!isset($j["code"])||(int)$j["code"]!==4400){exit(1);}' "$stable_response"
  stable_probe="route_ready"
elif [ "$stable_http" = 403 ]; then
  # 生产白名单可能不允许服务器用公网出口回访自身；部署后由已授权 IP 再验证。
  stable_probe="blocked_by_allowlist"
else
  echo "正式版目标接口 HTTP $stable_http" >&2
  exit 1
fi
chmod 600 "$stable_response"
case "$home_status" in 200|301|302|403) ;; *) echo "首页 HTTP $home_status" >&2; exit 1;; esac

echo "[6/6] 完成"
FILES_CHANGED=0
printf 'backup=%s\nhome_http=%s\nstable_api=%s\n' \
  "$BACKUP_DIR" "$home_status" "$stable_probe"
sha256sum "${FILES[@]}"

cleanup_client
trap - EXIT
