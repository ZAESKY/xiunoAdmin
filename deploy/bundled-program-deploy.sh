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
BACKUP_DIR="$BACKUP_ROOT/bundled_program_$DEPLOYMENT_STAMP"
CLIENT_CNF=""
FILES_CHANGED=0

FILES=(
  app/common/service/ManifestService.php
  app/api/route/route.php
  app/api/controller/UpdateV2.php
  app/admin/view/version/list.html
  app/admin/model/VersionModel.php
  app/admin/service/VersionService.php
  app/admin/service/UploadService.php
  app/admin/controller/Version.php
  app/admin/controller/Upload.php
)

cleanup() {
  if [ -n "$CLIENT_CNF" ] && [ -f "$CLIENT_CNF" ]; then unlink "$CLIENT_CNF"; fi
}

rollback() {
  status=$?
  if [ "$status" -ne 0 ] && [ "$FILES_CHANGED" -eq 1 ]; then
    echo "部署失败，正在恢复授权系统文件" >&2
    while IFS= read -r rel; do
      [ -n "$rel" ] || continue
      install -d "$(dirname "$SITE_DIR/$rel")"
      cp -a "$BACKUP_DIR/files/$rel" "$SITE_DIR/$rel"
    done < "$BACKUP_DIR/existing-files.txt"
    "$PHP_BIN" "$SITE_DIR/think" clear >/dev/null 2>&1 || true
  fi
  cleanup
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

echo "[1/6] 校验待部署文件"
cd "$STAGE_DIR"
sha256sum -c SHA256SUMS
for rel in "${FILES[@]}"; do
  [ -f "$rel" ] || { echo "缺少待部署文件：$rel" >&2; exit 73; }
  case "$rel" in *.php) "$PHP_BIN" -l "$rel" >/dev/null ;; esac
done

echo "[2/6] 创建代码与完整数据库备份"
if [ -e "$BACKUP_DIR" ]; then echo "备份目录已存在：$BACKUP_DIR" >&2; exit 73; fi
install -d -m 700 "$BACKUP_DIR/files"
: > "$BACKUP_DIR/existing-files.txt"
chmod 600 "$BACKUP_DIR/existing-files.txt"
for rel in "${FILES[@]}"; do
  [ -f "$SITE_DIR/$rel" ] || { echo "线上缺少既有文件：$rel" >&2; exit 73; }
  install -d "$BACKUP_DIR/files/$(dirname "$rel")"
  cp -a "$SITE_DIR/$rel" "$BACKUP_DIR/files/$rel"
  printf '%s\n' "$rel" >> "$BACKUP_DIR/existing-files.txt"
done

env_value() {
  "$PHP_BIN" -r '$v=parse_ini_file($argv[1],false,INI_SCANNER_RAW); echo trim((string)($v[$argv[2]]??""),"\"\047 ");' "$SITE_DIR/.env" "$1"
}
db_host="$(env_value database_hostname)"; db_host="${db_host:-127.0.0.1}"
db_port="$(env_value database_hostport)"; db_port="${db_port:-3306}"
db_name="$(env_value database_database)"
db_user="$(env_value database_username)"
db_pass="$(env_value database_password)"
[ -n "$db_name" ] && [ -n "$db_user" ]
CLIENT_CNF="$(mktemp)"
chmod 600 "$CLIENT_CNF"
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\ndefault-character-set=utf8mb4\n' \
  "$db_host" "$db_port" "$db_user" "$db_pass" > "$CLIENT_CNF"
unset db_pass
dump_bin="$(command -v mysqldump || true)"; [ -n "$dump_bin" ] || dump_bin="/www/server/mysql/bin/mysqldump"
"$dump_bin" --defaults-extra-file="$CLIENT_CNF" --single-transaction --quick \
  --routines --triggers --events --no-tablespaces --default-character-set=utf8mb4 \
  "$db_name" > "$BACKUP_DIR/database.sql"
[ "$(wc -c < "$BACKUP_DIR/database.sql")" -gt 1024 ]
grep -q 'Dump completed' "$BACKUP_DIR/database.sql"
sha256sum "$BACKUP_DIR/database.sql" > "$BACKUP_DIR/database.sql.sha256"
chmod 600 "$BACKUP_DIR/database.sql" "$BACKUP_DIR/database.sql.sha256"

echo "[3/6] 原子部署授权系统文件"
deploy_file() {
  src="$1"; dst="$2"; temp="${dst}.zaesky-new.$$"
  install -m 0644 "$src" "$temp"
  chown --reference="$dst" "$temp"
  chmod --reference="$dst" "$temp"
  mv -f "$temp" "$dst"
}
FILES_CHANGED=1
for rel in "${FILES[@]}"; do deploy_file "$STAGE_DIR/$rel" "$SITE_DIR/$rel"; done

echo "[4/6] 清理缓存并复检"
cd "$SITE_DIR"
"$PHP_BIN" think clear
for rel in "${FILES[@]}"; do case "$rel" in *.php) "$PHP_BIN" -l "$rel" >/dev/null ;; esac; done
grep -q 'SCHEMA_VERSION = 3' app/common/service/ManifestService.php
grep -q 'program_files' app/common/service/ManifestService.php
grep -q 'UpdateV2/retiredPatch' app/api/route/route.php
! grep -q 'programPatch' app/admin/view/version/list.html
grep -q 'PROGRAM_PATCH_RETIRED' app/api/controller/UpdateV2.php

echo "[5/6] Web 最小健康检查"
nginx -t >/dev/null 2>&1
home_status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 https://www.noteweb.top/)"
admin_status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 https://www.noteweb.top/admin.php/Version/list.html)"
patch_status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 -X POST https://www.noteweb.top/api.php/v2/patch/check)"
case "$home_status" in 200|301|302|403) ;; *) echo "首页 HTTP $home_status" >&2; exit 1 ;; esac
case "$admin_status" in 200|301|302|303|403) ;; *) echo "后台 HTTP $admin_status" >&2; exit 1 ;; esac
# 服务器自身可能被生产 IP 白名单拦为 403；应用可达时旧标准路由应为 404/405。
case "$patch_status" in 403|410) ;; *) echo "已下线补丁接口 HTTP $patch_status" >&2; exit 1 ;; esac

echo "[6/6] 部署完成"
printf 'backup=%s\nhome_http=%s\nadmin_http=%s\nretired_patch_http=%s\n' \
  "$BACKUP_DIR" "$home_status" "$admin_status" "$patch_status"
sha256sum "${FILES[@]}"

FILES_CHANGED=0
trap cleanup EXIT
