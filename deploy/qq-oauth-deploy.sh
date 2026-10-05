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

BACKUP_DIR="$BACKUP_ROOT/qq_oauth_$DEPLOYMENT_STAMP"
CLIENT_CNF=""
FILES_CHANGED=0

FILES=(
  .env.example
  app/QH_Auth.sql
  app/common.php
  app/api/lib/Oauth.php
  app/api/controller/Social.php
  app/api/controller/Qrlogin.php
  app/user/controller/Login.php
  app/admin/controller/Login.php
  app/user/controller/MyInfo.php
  app/admin/controller/Index.php
  app/user/controller/Index.php
  app/common/view/layout/main_layout.html
  app/user/view/login/index.html
  app/admin/view/login/index.html
  app/user/view/my_info/index.html
  app/user/view/auth/list.html
  app/user/view/my_list/auth.html
  app/index/view/index/qrcode.html
  public/template/modules/login/default/index/index.html
  public/qrlogin/qrlogin.html
  public/Assets/js/qq-oauth.js
  database/migrate.sh
  database/migrations/20261001_qq_oauth_identity.sql
  database/migrations/20261001_qq_oauth_identity_rollback.sql
  database/migrations/20261001_qq_account_uniqueness.sql
  database/migrations/20261001_qq_account_uniqueness_rollback.sql
  database/migrations/20261003_qq_oauth_unification.sql
  database/migrations/20261003_qq_oauth_unification_rollback.sql
  deploy/qq-oauth-deploy.sh
)

cleanup() {
  if [ -n "$CLIENT_CNF" ] && [ -f "$CLIENT_CNF" ]; then
    unlink "$CLIENT_CNF"
  fi
}

rollback() {
  status=$?
  trap - EXIT
  set +e
  if [ "$status" -ne 0 ]; then
    echo "QQ OAuth 部署失败，正在恢复本次覆盖的文件" >&2
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
          [ -n "$rel" ] || continue
          if [ -f "$SITE_DIR/$rel" ] && [ ! -L "$SITE_DIR/$rel" ]; then
            unlink "$SITE_DIR/$rel"
          fi
        done < "$BACKUP_DIR/new-files.txt"
      fi
    fi
    "$PHP_BIN" "$SITE_DIR/think" clear >/dev/null 2>&1 || true
    echo "代码已回滚；新增的 QH_qq_identity_claim 表保留，避免删除部署期间可能写入的关系数据。" >&2
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
if [ -e "$STAGE_DIR/.env" ]; then
  echo "暂存目录不得包含 .env" >&2
  exit 73
fi

echo "[1/8] 校验暂存文件与环境"
cd "$STAGE_DIR"
sha256sum -c SHA256SUMS
for rel in "${FILES[@]}"; do
  [ -f "$STAGE_DIR/$rel" ] || { echo "缺少待部署文件：$rel" >&2; exit 73; }
  case "$rel" in
    *.php) "$PHP_BIN" -l "$STAGE_DIR/$rel" >/dev/null ;;
    *.sh) bash -n "$STAGE_DIR/$rel" ;;
  esac
done

env_value() {
  "$PHP_BIN" -r '
    $values=parse_ini_file($argv[1], false, INI_SCANNER_RAW);
    echo trim((string)($values[$argv[2]]??""), "\"\047 ");
  ' "$SITE_DIR/.env" "$1"
}

qq_appid="$(env_value qq_oauth_appid)"
qq_appkey="$(env_value qq_oauth_appkey)"
qq_base="$(env_value qq_oauth_callback_base)"
qq_path="$(env_value qq_oauth_callback_path)"
[ -n "$qq_appid" ] && [ -n "$qq_appkey" ] || { echo "QQ OAuth AppID/AppKey 未配置" >&2; exit 78; }
case "$qq_base" in
  https://*) ;;
  *) echo "qq_oauth_callback_base 必须为 HTTPS" >&2; exit 78 ;;
esac
case "$qq_path" in
  ''|api.php/Social/callback|user.php/login/index.html|admin.php/login/index.html) ;;
  *) echo "qq_oauth_callback_path 不在允许列表中" >&2; exit 78 ;;
esac
unset qq_appkey

echo "[2/8] 创建代码和数据库备份"
if [ -e "$BACKUP_DIR" ]; then
  echo "备份目录已存在，拒绝覆盖：$BACKUP_DIR" >&2
  exit 73
fi
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
grep -q 'Table structure for table `QH_user`' "$BACKUP_DIR/database.sql"
grep -q 'Dump completed' "$BACKUP_DIR/database.sql"
sha256sum "$BACKUP_DIR/database.sql" > "$BACKUP_DIR/database.sql.sha256"
chmod 600 "$BACKUP_DIR/database.sql" "$BACKUP_DIR/database.sql.sha256"

db_q() {
  "$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" -N -B -e "$1"
}

social_table="$(db_q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='QH_social_identity'")"
binding_table="$(db_q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='QH_user_social_identity'")"
identity_unique="$(db_q "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='QH_user_social_identity' AND INDEX_NAME='uk_social_identity_once'")"
user_unique="$(db_q "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='QH_user_social_identity' AND INDEX_NAME='uk_social_user_once'")"
duplicate_qq="$(db_q "SELECT COUNT(*) FROM (SELECT qq FROM QH_user WHERE qq IS NOT NULL AND TRIM(qq)<>'' GROUP BY qq HAVING COUNT(*)>1) AS duplicate_rows")"
duplicate_usernames="$(db_q "SELECT COUNT(*) FROM (SELECT username FROM QH_user GROUP BY username HAVING COUNT(*)>1) AS duplicate_rows")"
if [ "$social_table" != 1 ] || [ "$binding_table" != 1 ] || [ "$identity_unique" != 1 ] || [ "$user_unique" != 1 ]; then
  echo "线上 QQ OAuth 基础迁移不完整，拒绝只部署统一层" >&2
  exit 1
fi
if [ "$duplicate_qq" != 0 ] || [ "$duplicate_usernames" != 0 ]; then
  echo "线上存在重复 QQ 或用户名，需先人工审核迁移影响" >&2
  exit 1
fi
printf 'duplicate_qq_groups=%s\nduplicate_usernames=%s\n' "$duplicate_qq" "$duplicate_usernames" \
  > "$BACKUP_DIR/pre_migration_counts.txt"

echo "[3/8] 创建可信历史 QQ 关系表"
"$MYSQL_BIN" --defaults-extra-file="$CLIENT_CNF" "$DB_NAME" \
  < "$STAGE_DIR/database/migrations/20261003_qq_oauth_unification.sql"

echo "[4/8] 原子部署 QQ OAuth 文件"
deploy_file() {
  src="$1"; dst="$2"; tmp="${dst}.qqoauth.$$"
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
chmod 0755 "$SITE_DIR/database/migrate.sh" "$SITE_DIR/deploy/qq-oauth-deploy.sh"

echo "[5/8] 清理缓存并复检语法与哈希"
cd "$SITE_DIR"
for rel in "${FILES[@]}"; do
  cmp -s "$STAGE_DIR/$rel" "$SITE_DIR/$rel"
  case "$rel" in
    *.php) "$PHP_BIN" -l "$SITE_DIR/$rel" >/dev/null ;;
    *.sh) bash -n "$SITE_DIR/$rel" ;;
  esac
done
"$PHP_BIN" think clear

echo "[6/8] 验证数据库约束和运行配置"
claim_table="$(db_q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='QH_qq_identity_claim'")"
claim_unique="$(db_q "SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='QH_qq_identity_claim' AND INDEX_NAME IN ('uk_qq_claim_identity','uk_qq_claim_user','uk_qq_claim_number')")"
qq_enabled="$(db_q "SELECT COUNT(*) FROM QH_config WHERE name='login_switch' AND FIND_IN_SET('qq',REPLACE(value,' ',''))>0")"
[ "$claim_table" = 1 ] && [ "$claim_unique" = 3 ] && [ "$qq_enabled" -ge 1 ]
callback_path_after="$(env_value qq_oauth_callback_path)"
[ "$callback_path_after" = "$qq_path" ]
grep -q "private const UNIFIED_CALLBACK = 'api.php/Social/callback'" app/api/controller/Social.php
grep -q 'private function isTrustedPost(): bool' app/api/controller/Social.php
grep -q "event.origin !== window.location.origin" public/Assets/js/qq-oauth.js

echo "[7/8] 最小化线上健康检查"
nginx -t >/dev/null 2>&1
home_status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 https://www.noteweb.top/)"
user_login_status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 https://www.noteweb.top/user.php/login/index.html)"
oauth_js_status="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 https://www.noteweb.top/Assets/js/qq-oauth.js?v=20261003)"
for result in "$home_status" "$user_login_status" "$oauth_js_status"; do
  case "$result" in
    200|301|302|303|403) ;;
    *) echo "线上 HTTP 健康检查失败：$result" >&2; exit 1 ;;
  esac
done

echo "[8/8] 部署完成"
claim_rows="$(db_q 'SELECT COUNT(*) FROM QH_qq_identity_claim')"
identity_rows="$(db_q 'SELECT COUNT(*) FROM QH_social_identity')"
binding_rows="$(db_q 'SELECT COUNT(*) FROM QH_user_social_identity')"
printf 'backup=%s\nhome_http=%s\nuser_login_http=%s\noauth_js_http=%s\ncallback_mode=%s\nidentity_rows=%s\nbinding_rows=%s\nclaim_rows=%s\n' \
  "$BACKUP_DIR" "$home_status" "$user_login_status" "$oauth_js_status" \
  "$([ -n "$callback_path_after" ] && printf unified || printf legacy-compatible)" \
  "$identity_rows" "$binding_rows" "$claim_rows"

FILES_CHANGED=0
cleanup
trap - EXIT
