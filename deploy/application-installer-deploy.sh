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
BACKUP_DIR="$BACKUP_ROOT/application_installer_$DEPLOYMENT_STAMP"
KEY_FILE="$STAGE_DIR/release-signing-key.env"
CLIENT_CNF=""
FILES_CHANGED=0
ENV_CHANGED=0

FILES=(
  app/admin/controller/App.php
  app/admin/service/AppService.php
  app/admin/service/UploadService.php
  app/admin/model/VersionModel.php
  app/admin/view/app/list.html
  app/admin/view/app/edit.html
  app/admin/view/version/list.html
  app/index/controller/DownloadInfo.php
  app/index/controller/DownloadMail.php
  app/api/controller/Qrlogin.php
  app/api/service/DownloadService.php
  app/common/service/LicenseService.php
  app/common/service/ApplicationInstallerService.php
  app/common/service/VersionReleaseService.php
  database/migrations/20261002_application_installer_packages.sql
  database/migrations/20261002_application_installer_packages_rollback.sql
)

cleanup() {
  if [ -n "$CLIENT_CNF" ] && [ -f "$CLIENT_CNF" ]; then
    unlink "$CLIENT_CNF"
  fi
  if [ -f "$KEY_FILE" ]; then
    unlink "$KEY_FILE"
  fi
}

rollback() {
  status=$?
  if [ "$status" -ne 0 ]; then
    echo "部署失败，正在恢复授权系统文件与 .env" >&2
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
            app/common/service/ApplicationInstallerService.php|app/common/service/VersionReleaseService.php|database/migrations/20261002_application_installer_packages.sql|database/migrations/20261002_application_installer_packages_rollback.sql)
              [ -f "$SITE_DIR/$rel" ] && unlink "$SITE_DIR/$rel"
              ;;
          esac
        done < "$BACKUP_DIR/new-files.txt"
      fi
    fi
    if [ "$ENV_CHANGED" -eq 1 ] && [ -f "$BACKUP_DIR/env.before" ]; then
      cp -a "$BACKUP_DIR/env.before" "$SITE_DIR/.env"
    fi
    "$PHP_BIN" "$SITE_DIR/think" clear >/dev/null 2>&1 || true
    echo "代码已回滚；幂等新增的 4 个空元数据列予以保留，不影响旧版运行。" >&2
  fi
  cleanup
  exit "$status"
}
trap rollback EXIT

if [ ! -d "$SITE_DIR" ] || [ ! -f "$SITE_DIR/public/index.php" ] || [ ! -f "$SITE_DIR/.env" ]; then
  echo "线上目录校验失败，拒绝部署" >&2
  exit 73
fi
if [ ! -d "$STAGE_DIR" ] || [ ! -f "$STAGE_DIR/SHA256SUMS" ] || [ ! -f "$KEY_FILE" ]; then
  echo "暂存目录不完整，拒绝部署" >&2
  exit 73
fi

echo "[1/9] 校验暂存文件与签名密钥"
cd "$STAGE_DIR"
sha256sum -c SHA256SUMS
for rel in "${FILES[@]}"; do
  [ -f "$STAGE_DIR/$rel" ] || { echo "缺少待部署文件：$rel" >&2; exit 73; }
done
for rel in "${FILES[@]}"; do
  case "$rel" in
    *.php) "$PHP_BIN" -l "$STAGE_DIR/$rel" >/dev/null ;;
  esac
done
"$PHP_BIN" -r '
  $v=parse_ini_file($argv[1], false, INI_SCANNER_RAW);
  $id=trim((string)($v["release_sign_key_id"]??""), "\"\047 ");
  $sec=base64_decode(trim((string)($v["release_sign_secret_key"]??""), "\"\047 "), true);
  $pub=base64_decode(trim((string)($v["release_sign_public_key"]??""), "\"\047 "), true);
  if ($id!=="release-20260816-3cb616f2" || strlen((string)$sec)!==64 || strlen((string)$pub)!==32
      || !hash_equals($pub, sodium_crypto_sign_publickey_from_secretkey($sec))) { exit(1); }
' "$KEY_FILE"

echo "[2/9] 创建代码、配置与完整数据库备份"
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
cp -a "$SITE_DIR/.env" "$BACKUP_DIR/env.before"
chmod 600 "$BACKUP_DIR/env.before"

env_value() {
  "$PHP_BIN" -r '
    $values=parse_ini_file($argv[1], false, INI_SCANNER_RAW);
    echo trim((string)($values[$argv[2]]??""), "\"\047 ");
  ' "$SITE_DIR/.env" "$1"
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
dump_bin="$(command -v mysqldump || true)"
[ -n "$dump_bin" ] || dump_bin="/www/server/mysql/bin/mysqldump"
"$dump_bin" --defaults-extra-file="$CLIENT_CNF" --single-transaction --quick \
  --routines --triggers --events --no-tablespaces --default-character-set=utf8mb4 \
  "$db_name" > "$BACKUP_DIR/database.sql"
[ "$(wc -c < "$BACKUP_DIR/database.sql")" -gt 1024 ]
grep -q 'Table structure for table `QH_app`' "$BACKUP_DIR/database.sql"
grep -q 'Dump completed' "$BACKUP_DIR/database.sql"
sha256sum "$BACKUP_DIR/database.sql" > "$BACKUP_DIR/database.sql.sha256"
chmod 600 "$BACKUP_DIR/database.sql" "$BACKUP_DIR/database.sql.sha256"

echo "[3/9] 执行公开安装包元数据迁移"
mysql --defaults-extra-file="$CLIENT_CNF" "$db_name" \
  < "$STAGE_DIR/database/migrations/20261002_application_installer_packages.sql"

echo "[4/9] 安全写入完整包发布签名配置"
read_key() {
  "$PHP_BIN" -r '
    $values=parse_ini_file($argv[1], false, INI_SCANNER_RAW);
    echo trim((string)($values[$argv[2]]??""), "\"\047 ");
  ' "$KEY_FILE" "$1"
}
release_key_id="$(read_key release_sign_key_id)"
release_secret="$(read_key release_sign_secret_key)"
release_public="$(read_key release_sign_public_key)"
env_tmp="$SITE_DIR/.env.qhnew.$$"
awk '!/^[[:space:]]*release_sign_(key_id|secret_key|public_key)[[:space:]]*=/' "$SITE_DIR/.env" > "$env_tmp"
printf '\n# 完整安装包 Ed25519 发布签名（仅服务端持有私钥）\nrelease_sign_key_id = %s\nrelease_sign_secret_key = %s\nrelease_sign_public_key = %s\n' \
  "$release_key_id" "$release_secret" "$release_public" >> "$env_tmp"
chown --reference="$SITE_DIR/.env" "$env_tmp"
chmod 600 "$env_tmp"
mv -f "$env_tmp" "$SITE_DIR/.env"
ENV_CHANGED=1
unset release_secret

echo "[5/9] 原子部署授权系统文件"
deploy_file() {
  src="$1"; dst="$2"; tmp="${dst}.qhnew.$$"
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

echo "[6/9] 清理框架缓存并执行 PHP 语法复检"
cd "$SITE_DIR"
if [ ! -d app/common/download/v2 ]; then
  install -d -o www -g www -m 0750 app/common/download/v2
fi
for rel in "${FILES[@]}"; do
  case "$rel" in
    *.php) "$PHP_BIN" -l "$SITE_DIR/$rel" >/dev/null ;;
  esac
done
"$PHP_BIN" think clear

echo "[7/9] 验证数据库、产品映射和发布密钥"
for column in installer_file_name installer_sha256 installer_size installer_uploaded_at; do
  count="$(mysql --defaults-extra-file="$CLIENT_CNF" "$db_name" -N -B -e \
    "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='QH_app' AND COLUMN_NAME='$column'")"
  [ "$count" = 1 ] || { echo "缺少字段 QH_app.$column" >&2; exit 1; }
done
app_count="$(mysql --defaults-extra-file="$CLIENT_CNF" "$db_name" -N -B -e 'SELECT COUNT(*) FROM QH_app WHERE id=1')"
[ "$app_count" = 1 ]
grep -Eq '^[[:space:]]*license_product_app_map[[:space:]]*=[[:space:]]*zaesky_theme_light:1([[:space:]]*$|,)' .env
"$PHP_BIN" -r '
  $v=parse_ini_file($argv[1], false, INI_SCANNER_RAW);
  $sec=base64_decode(trim((string)($v["release_sign_secret_key"]??""), "\"\047 "), true);
  $pub=base64_decode(trim((string)($v["release_sign_public_key"]??""), "\"\047 "), true);
  if (strlen((string)$sec)!==64 || strlen((string)$pub)!==32
      || !hash_equals($pub, sodium_crypto_sign_publickey_from_secretkey($sec))) { exit(1); }
' .env

echo "[8/9] 最小化线上健康检查"
nginx -t >/dev/null 2>&1
home_status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 https://www.noteweb.top/)"
admin_status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 https://www.noteweb.top/admin.php/App/list.html)"
# 生产 Nginx 对非白名单来源返回 403；这表示 TLS/Web 服务可达且访问控制生效。
case "$home_status" in 200|301|302|403) ;; *) echo "首页 HTTP $home_status" >&2; exit 1;; esac
case "$admin_status" in 200|301|302|303|403) ;; *) echo "后台 HTTP $admin_status" >&2; exit 1;; esac

echo "[9/9] 部署完成"
printf 'backup=%s\nhome_http=%s\nadmin_http=%s\napp_id_1=%s\n' \
  "$BACKUP_DIR" "$home_status" "$admin_status" "$app_count"
sha256sum "${FILES[@]}"

FILES_CHANGED=0
ENV_CHANGED=0
trap cleanup EXIT
