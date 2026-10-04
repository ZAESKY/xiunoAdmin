#!/usr/bin/env bash
#
# ═══════════════════════════════════════════════════════════════════
#  SF 授权系统 · 服务器只读巡检
#
#  用途：迁移前查清旧生产环境与新环境的真实状态，不做任何猜测。
#
#  ⚠ 本脚本对旧生产环境是**严格只读**的：
#     - 只执行文件读取、SHOW / SELECT 查询、ps / systemctl status 等只读命令
#     - 不写入、不修改、不删除、不重启、不清缓存
#     - 唯一的写操作是把报告写到 /tmp 下的临时文件（不在任何站点目录内）
#     - 数据库只用 SHOW / SELECT，不执行任何 DDL/DML
#
#  ⚠ 输出已做脱敏：密码、密钥、Token 一律显示为 ***，不会出现明文
#
#  用法：
#      bash inspect-server.sh > /tmp/inspect_report.txt 2>&1
#      # 然后把 /tmp/inspect_report.txt 的内容贴回给我
#
# ═══════════════════════════════════════════════════════════════════

set -uo pipefail

OLD_DOMAIN="admin.idaily.top"
NEW_DOMAIN="admin.noteweb.top"
OLD_DB="admin_idaily_top"
NEW_DB="admin_noteweb_to"

TMP_CNF=""
cleanup() { [ -n "$TMP_CNF" ] && rm -f "$TMP_CNF"; }
trap cleanup EXIT

hd()  { echo; echo "════════════════════════════════════════════════════════"; echo "  $*"; echo "════════════════════════════════════════════════════════"; }
sub() { echo; echo "── $* ──"; }
kv()  { printf "  %-28s %s\n" "$1" "$2"; }
note(){ echo "  [注] $*"; }

# 脱敏：把疑似凭据的值替换掉
redact() {
  sed -E \
    -e 's/(password|passwd|pwd|secret|token|api_key|apikey|private_key|access_key|auth_key)([[:space:]]*[=:][[:space:]]*)[^[:space:],;"'"'"']+/\1\2***REDACTED***/gi' \
    -e 's/(mysql:\/\/[^:]+:)[^@]+@/\1***@/gi'
}

echo "SF 授权系统 · 服务器只读巡检报告"
echo "生成时间: $(date '+%Y-%m-%d %H:%M:%S %Z')"
echo "主机: $(hostname)"
echo "说明: 本次运行未对任何站点或数据库做出修改"

# ═══════════════════════════════════════════════════════
hd "1. 服务器基础"
# ═══════════════════════════════════════════════════════
kv "系统" "$(cat /etc/os-release 2>/dev/null | grep -E '^PRETTY_NAME' | cut -d'"' -f2)"
kv "内核" "$(uname -r)"
kv "架构" "$(uname -m)"
kv "CPU 核数" "$(nproc 2>/dev/null || echo '?')"
kv "内存" "$(free -h 2>/dev/null | awk 'NR==2{print $2" 总 / "$3" 已用 / "$7" 可用"}')"
kv "系统时间" "$(date '+%Y-%m-%d %H:%M:%S %Z')"
kv "时区" "$(timedatectl 2>/dev/null | grep -i 'time zone' | sed 's/^ *//' || cat /etc/timezone 2>/dev/null || echo '?')"

sub "时间同步（授权签名有 ±300 秒时间窗，漂移会导致请求被判过期）"
if command -v timedatectl >/dev/null 2>&1; then
  timedatectl 2>/dev/null | grep -iE 'synchronized|NTP' | sed 's/^ */  /'
elif command -v chronyc >/dev/null 2>&1; then
  chronyc tracking 2>/dev/null | head -5 | sed 's/^/  /'
else
  note "未检测到 timedatectl/chronyc，请确认时间同步方式"
fi

sub "磁盘"
df -h 2>/dev/null | grep -vE '^(tmpfs|devtmpfs|overlay)' | sed 's/^/  /'

# ═══════════════════════════════════════════════════════
hd "2. 宝塔面板与站点清单"
# ═══════════════════════════════════════════════════════
if [ -d /www/server/panel ]; then
  kv "宝塔面板" "已安装"
  [ -f /www/server/panel/class/common.py ] && kv "面板版本" "$(cat /www/server/panel/config/version.pl 2>/dev/null || echo '?')"
else
  note "未检测到宝塔面板（/www/server/panel 不存在）"
fi

sub "所有站点（从 Nginx vhost 配置读取）"
VHOST_DIR=""
for d in /www/server/panel/vhost/nginx /www/server/panel/vhost/apache /etc/nginx/conf.d; do
  [ -d "$d" ] && VHOST_DIR="$d" && break
done
if [ -n "$VHOST_DIR" ]; then
  kv "vhost 目录" "$VHOST_DIR"
  for f in "$VHOST_DIR"/*.conf; do
    [ -f "$f" ] || continue
    name="$(basename "$f" .conf)"
    root="$(grep -oP '^\s*root\s+\K[^;]+' "$f" 2>/dev/null | head -1)"
    names="$(grep -oP '^\s*server_name\s+\K[^;]+' "$f" 2>/dev/null | head -1)"
    printf "  %-32s root=%-45s server_name=%s\n" "$name" "${root:-?}" "${names:-?}"
  done
else
  note "未找到 vhost 目录，请手动确认 Web 服务配置位置"
fi

sub "宝塔站点数据库（只读查询 default.db）"
if [ -f /www/server/panel/data/default.db ] && command -v sqlite3 >/dev/null 2>&1; then
  echo "  站点名 | 根目录 | PHP版本 | 状态"
  sqlite3 -readonly /www/server/panel/data/default.db \
    "SELECT name, path, php_version, status FROM sites;" 2>/dev/null \
    | sed 's/|/ | /g; s/^/  /' || note "查询失败（表结构可能不同，可忽略）"
else
  note "无 sqlite3 或 default.db 不可读，跳过（不影响，vhost 已给出关键信息）"
fi

# ═══════════════════════════════════════════════════════
hd "3. 旧生产环境：$OLD_DOMAIN （只读）"
# ═══════════════════════════════════════════════════════
OLD_CONF=""
for f in "$VHOST_DIR/$OLD_DOMAIN.conf" /www/server/panel/vhost/nginx/"$OLD_DOMAIN".conf; do
  [ -f "$f" ] && OLD_CONF="$f" && break
done

if [ -z "$OLD_CONF" ]; then
  note "未找到 $OLD_DOMAIN 的 vhost 配置，尝试按域名搜索"
  OLD_CONF="$([ -n "$VHOST_DIR" ] && grep -rl "$OLD_DOMAIN" "$VHOST_DIR" 2>/dev/null | head -1)"
fi

if [ -n "$OLD_CONF" ] && [ -f "$OLD_CONF" ]; then
  kv "vhost 文件" "$OLD_CONF"
  OLD_ROOT="$(grep -oP '^\s*root\s+\K[^;]+' "$OLD_CONF" | head -1)"
  kv "Web 根目录" "${OLD_ROOT:-?}"
  kv "PHP 版本(fastcgi)" "$(grep -oP 'php-\K[0-9]+(?=\.sock)' "$OLD_CONF" | head -1 || grep -oP 'unix:[^;]*php[^;]*' "$OLD_CONF" | head -1 || echo '?')"
  kv "SSL" "$(grep -q 'ssl_certificate' "$OLD_CONF" && echo '已配置' || echo '未配置')"
  kv "强制 HTTPS" "$(grep -qE '301.*https|rewrite.*https' "$OLD_CONF" && echo '已开启' || echo '未开启/未检测到')"

  sub "vhost 关键片段（已脱敏）"
  grep -nE 'root|server_name|listen|ssl_certificate|include|location|rewrite|fastcgi_pass' "$OLD_CONF" \
    | grep -v '^\s*#' | head -40 | redact | sed 's/^/  /'

  # 推断项目根（Web 根若是 .../public，项目根是它的上级）
  if [ -n "${OLD_ROOT:-}" ]; then
    OLD_PROJ="$OLD_ROOT"
    [ "$(basename "$OLD_ROOT")" = "public" ] && OLD_PROJ="$(dirname "$OLD_ROOT")"
    kv "推断项目根" "$OLD_PROJ"

    sub "项目根目录结构（一层）"
    ls -la "$OLD_PROJ" 2>/dev/null | head -40 | sed 's/^/  /'

    sub "⚠ Web 根位置安全性判断"
    if [ "$(basename "$OLD_ROOT")" = "public" ]; then
      echo "  ✓ Web 根指向 public/，源码与 .env 不在 Web 可达范围内"
    else
      echo "  ✗ Web 根**未**指向 public/ —— .env、app/、vendor/ 可能可被直接访问"
      echo "    验证：curl -s -o /dev/null -w '%{http_code}' https://$OLD_DOMAIN/.env"
      echo "    （新环境务必把运行目录设为 public，从根上消除该问题）"
    fi

    sub "版本与配置（只读，不显示凭据）"
    if [ -f "$OLD_PROJ/.env" ]; then
      echo "  .env 存在，包含的键（值已隐藏）："
      grep -oE '^[a-zA-Z_]+' "$OLD_PROJ/.env" 2>/dev/null | sort -u | sed 's/^/    /'
      echo
      echo "  非敏感项的值："
      grep -E '^(app_debug|system_version|system_sitename|system_nickname|domain_img_url|log_channel|oss_enabled|database_hostname|database_hostport|database_database|database_username)' \
        "$OLD_PROJ/.env" 2>/dev/null | sed 's/^/    /'
    else
      note "$OLD_PROJ/.env 不存在"
    fi

    [ -f "$OLD_PROJ/composer.json" ] && kv "composer name" "$(grep -oP '"name"\s*:\s*"\K[^"]+' "$OLD_PROJ/composer.json" | head -1)"
    [ -f "$OLD_PROJ/app/install/SF_Auth.Lock" ] && kv "安装锁" "存在" || kv "安装锁" "不存在"

    sub "ThinkPHP 版本"
    if [ -f "$OLD_PROJ/composer.lock" ]; then
      grep -A2 '"name": "topthink/framework"' "$OLD_PROJ/composer.lock" 2>/dev/null | grep '"version"' | head -1 | sed 's/^/  /'
    fi

    sub "本地代码与生产代码的差异线索"
    kv "app/ 最后修改" "$(find "$OLD_PROJ/app" -type f -name '*.php' -printf '%TY-%Tm-%Td %TH:%TM\n' 2>/dev/null | sort -r | head -1)"
    kv "文件总数(app)" "$(find "$OLD_PROJ/app" -type f -name '*.php' 2>/dev/null | wc -l)"
    echo "  是否存在本地已实现的 v2 文件（用于判断生产是否已包含新功能）："
    for f in app/common/service/CryptoService.php app/common/service/LicenseService.php app/api/route/route.php app/command/Sign.php; do
      [ -f "$OLD_PROJ/$f" ] && echo "    存在: $f" || echo "    缺失: $f"
    done

    sub "上传目录"
    if [ -d "$OLD_PROJ/public/upload" ]; then
      kv "路径" "$OLD_PROJ/public/upload"
      kv "体积" "$(du -sh "$OLD_PROJ/public/upload" 2>/dev/null | cut -f1)"
      kv "文件数" "$(find "$OLD_PROJ/public/upload" -type f 2>/dev/null | wc -l)"
      echo "  一级子目录："
      ls -1 "$OLD_PROJ/public/upload" 2>/dev/null | head -20 | sed 's/^/    /'
    fi

    sub "更新包目录（app/common/download）"
    if [ -d "$OLD_PROJ/app/common/download" ]; then
      kv "体积" "$(du -sh "$OLD_PROJ/app/common/download" 2>/dev/null | cut -f1)"
      find "$OLD_PROJ/app/common/download" -maxdepth 2 -type d 2>/dev/null | head -20 | sed 's/^/    /'
    else
      note "不存在"
    fi

    sub "runtime 目录（不应复制到新环境）"
    [ -d "$OLD_PROJ/runtime" ] && kv "体积" "$(du -sh "$OLD_PROJ/runtime" 2>/dev/null | cut -f1)"

    sub "目录属主与权限"
    stat -c '  %U:%G %a  %n' "$OLD_PROJ" "$OLD_PROJ/public" "$OLD_PROJ/runtime" "$OLD_PROJ/public/upload" 2>/dev/null
  fi
else
  note "未能定位 $OLD_DOMAIN 的站点配置，请手动确认"
fi

# ═══════════════════════════════════════════════════════
hd "4. 新环境：$NEW_DOMAIN"
# ═══════════════════════════════════════════════════════
NEW_CONF="$([ -n "$VHOST_DIR" ] && grep -rl "$NEW_DOMAIN" "$VHOST_DIR" 2>/dev/null | head -1)"
if [ -n "$NEW_CONF" ]; then
  kv "vhost 文件" "$NEW_CONF"
  NEW_ROOT="$(grep -oP '^\s*root\s+\K[^;]+' "$NEW_CONF" | head -1)"
  kv "Web 根目录" "${NEW_ROOT:-?}"
  kv "SSL" "$(grep -q 'ssl_certificate' "$NEW_CONF" && echo '已配置' || echo '未配置')"
  if [ -n "${NEW_ROOT:-}" ]; then
    NEW_PROJ="$NEW_ROOT"; [ "$(basename "$NEW_ROOT")" = "public" ] && NEW_PROJ="$(dirname "$NEW_ROOT")"
    kv "推断项目根" "$NEW_PROJ"
    sub "目录内容（判断是否为空、可否安全部署）"
    if [ -d "$NEW_PROJ" ]; then
      COUNT="$(find "$NEW_PROJ" -mindepth 1 -maxdepth 1 2>/dev/null | wc -l)"
      kv "一级条目数" "$COUNT"
      ls -la "$NEW_PROJ" 2>/dev/null | head -25 | sed 's/^/  /'
      if [ "$COUNT" -gt 3 ]; then
        echo "  ⚠ 目录非空，部署前需确认这些内容是否可覆盖（宝塔建站会生成默认首页/404，属正常）"
      fi
    fi
  fi
else
  note "$NEW_DOMAIN 尚未在宝塔中建站（这是预期的，将在部署阶段创建）"
fi

sub "域名解析"
for d in "$OLD_DOMAIN" "$NEW_DOMAIN"; do
  ip="$(getent hosts "$d" 2>/dev/null | awk '{print $1}' | head -1)"
  [ -z "$ip" ] && ip="$(dig +short "$d" 2>/dev/null | tail -1)"
  printf "  %-26s -> %s\n" "$d" "${ip:-未解析}"
done
kv "本机公网 IP" "$(curl -s -m 8 https://api.ipify.org 2>/dev/null || echo '获取失败')"

# ═══════════════════════════════════════════════════════
hd "5. PHP 环境"
# ═══════════════════════════════════════════════════════
sub "已安装的 PHP 版本"
for p in /www/server/php/*/bin/php; do
  [ -x "$p" ] || continue
  v="$("$p" -r 'echo PHP_VERSION;' 2>/dev/null)"
  printf "  %-40s %s\n" "$p" "$v"
done
command -v php >/dev/null 2>&1 && kv "PATH 中的 php" "$(command -v php) -> $(php -r 'echo PHP_VERSION;' 2>/dev/null)"

sub "各版本的扩展与关键配置"
for p in /www/server/php/*/bin/php; do
  [ -x "$p" ] || continue
  v="$("$p" -r 'echo PHP_VERSION;' 2>/dev/null)"
  echo
  echo "  ── PHP $v ($p)"
  printf "     扩展: "
  for e in sodium openssl curl pdo_mysql mbstring json zip fileinfo gd redis opcache bcmath exif intl; do
    if "$p" -r "exit(extension_loaded('$e')?0:1);" 2>/dev/null; then printf "%s " "$e"; else printf "\033[31m-%s\033[0m " "$e"; fi
  done
  echo
  echo "     （前缀 - 表示缺失）"
  printf "     Ed25519 可用: "; "$p" -r 'echo function_exists("sodium_crypto_sign_detached")?"是":"否";' 2>/dev/null; echo
  for k in memory_limit max_execution_time post_max_size upload_max_filesize date.timezone display_errors expose_php; do
    printf "     %-22s %s\n" "$k" "$("$p" -r "echo ini_get('$k');" 2>/dev/null)"
  done
  df_val="$("$p" -r "echo ini_get('disable_functions');" 2>/dev/null)"
  [ -n "$df_val" ] && echo "     disable_functions: $df_val"
done

# ═══════════════════════════════════════════════════════
hd "6. 数据库（只读：仅 SHOW / SELECT）"
# ═══════════════════════════════════════════════════════
sub "MySQL 服务"
if command -v mysql >/dev/null 2>&1; then
  kv "mysql 客户端" "$(mysql --version)"
else
  note "未找到 mysql 客户端"
fi
[ -f /www/server/mysql/bin/mysql ] && kv "宝塔 MySQL" "$(/www/server/mysql/bin/mysql --version 2>/dev/null)"

# 从旧站点的 .env 读取凭据（只读文件，凭据不落盘、不进 shell 历史、不打印）
if [ -n "${OLD_PROJ:-}" ] && [ -f "$OLD_PROJ/.env" ]; then
  get_env() {
    grep -E "^[[:space:]]*$1[[:space:]]*=" "$OLD_PROJ/.env" 2>/dev/null | head -1 | cut -d= -f2- \
      | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/"
  }
  DBH="$(get_env database_hostname)"; DBH="${DBH:-127.0.0.1}"
  DBP="$(get_env database_hostport)"; DBP="${DBP:-3306}"
  DBN="$(get_env database_database)"
  DBU="$(get_env database_username)"
  DBW="$(get_env database_password)"

  TMP_CNF="$(mktemp)"; chmod 600 "$TMP_CNF"
  printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' "$DBH" "$DBP" "$DBU" "$DBW" > "$TMP_CNF"

  q() { mysql --defaults-extra-file="$TMP_CNF" -N -B -e "$1" 2>&1; }

  sub "旧数据库连接（凭据取自旧站点 .env，不显示）"
  kv "主机" "$DBH:$DBP"
  kv "库名" "$DBN"
  kv "用户" "$DBU"

  VER="$(q 'SELECT VERSION();')"
  if echo "$VER" | grep -qiE 'error|denied'; then
    note "连接失败：$(echo "$VER" | redact)"
  else
    kv "MySQL 版本" "$VER"
    kv "服务端字符集" "$(q "SHOW VARIABLES LIKE 'character_set_server';" | awk '{print $2}')"
    kv "服务端排序规则" "$(q "SHOW VARIABLES LIKE 'collation_server';" | awk '{print $2}')"
    kv "时区" "$(q "SELECT @@global.time_zone, @@session.time_zone;" | tr '\t' ' ')"
    kv "sql_mode" "$(q 'SELECT @@sql_mode;')"
    kv "max_allowed_packet" "$(q "SHOW VARIABLES LIKE 'max_allowed_packet';" | awk '{print $2}')"
    kv "innodb_file_per_table" "$(q "SHOW VARIABLES LIKE 'innodb_file_per_table';" | awk '{print $2}')"

    sub "旧库权限（判断导出是否安全、是否具备写权限）"
    q "SHOW GRANTS;" | redact | sed 's/^/  /'

    sub "旧库：库级字符集与体积"
    q "SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$DBN';" | sed 's/^/  charset/collation: /'
    q "SELECT ROUND(SUM(data_length+index_length)/1048576,1) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DBN';" | sed 's/^/  体积(MB): /'
    q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DBN';" | sed 's/^/  表数量: /'

    sub "旧库：全部表（名称 | 引擎 | 排序规则 | 行数 | 自增值）"
    q "SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, TABLE_ROWS, IFNULL(AUTO_INCREMENT,'-')
       FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DBN' ORDER BY TABLE_NAME;" \
      | awk -F'\t' '{printf "  %-34s %-8s %-22s %10s %12s\n",$1,$2,$3,$4,$5}'

    sub "旧库：关键表精确行数（information_schema 的 TABLE_ROWS 是估算值）"
    for t in SF_user SF_admin SF_auth SF_app SF_version SF_order SF_pay SF_config SF_menu SF_plugin; do
      exists="$(q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DBN' AND TABLE_NAME='$t';")"
      if [ "$exists" = "1" ]; then
        printf "  %-24s %s\n" "$t" "$(q "SELECT COUNT(*) FROM \`$DBN\`.\`$t\`;")"
      else
        printf "  %-24s %s\n" "$t" "(表不存在)"
      fi
    done

    sub "旧库：是否已有 v2 迁移引入的表（判断生产版本进度）"
    for t in SF_auth_legacy SF_license SF_license_site SF_license_event SF_download_ticket SF_release SF_patch SF_trial SF_offline_activation; do
      printf "  %-28s %s\n" "$t" "$(q "SELECT IF(COUNT(*)=1,'已存在','不存在') FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DBN' AND TABLE_NAME='$t';")"
    done

    sub "旧库：SF_auth / SF_app 是否已有 v2 新增列"
    for pair in "SF_auth:authcode_hash" "SF_auth:authcode_last4" "SF_auth:must_rotate" "SF_app:auth_enforce"; do
      tb="${pair%%:*}"; col="${pair##*:}"
      printf "  %-16s.%-20s %s\n" "$tb" "$col" \
        "$(q "SELECT IF(COUNT(*)=1,'已存在','不存在') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DBN' AND TABLE_NAME='$tb' AND COLUMN_NAME='$col';")"
    done

    sub "旧库：非 utf8mb4 的表（迁移时需要特别处理）"
    q "SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES
       WHERE TABLE_SCHEMA='$DBN' AND TABLE_COLLATION NOT LIKE 'utf8mb4%';" | sed 's/^/  /'
    echo "  （无输出表示全部为 utf8mb4）"

    sub "旧库：外键约束"
    q "SELECT CONSTRAINT_NAME, TABLE_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
       WHERE TABLE_SCHEMA='$DBN' AND REFERENCED_TABLE_NAME IS NOT NULL;" | sed 's/^/  /'
    echo "  （无输出表示没有外键，导入顺序无约束）"

    sub "旧库：存储过程 / 触发器 / 事件"
    kv "存储过程" "$(q "SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='$DBN' AND ROUTINE_TYPE='PROCEDURE';")"
    kv "函数" "$(q "SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='$DBN' AND ROUTINE_TYPE='FUNCTION';")"
    kv "触发器" "$(q "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='$DBN';")"
    kv "事件" "$(q "SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA='$DBN';")"

    sub "旧库：数据库中存储的绝对 URL / 路径（迁移换域名需要改）"
    echo "  SF_config 中含 http 的配置项："
    q "SELECT name, LEFT(value,80) FROM \`$DBN\`.SF_config WHERE value LIKE '%http%';" 2>/dev/null | redact | sed 's/^/    /'
    echo
    echo "  SF_config 中含 $OLD_DOMAIN 的配置项："
    q "SELECT name, LEFT(value,80) FROM \`$DBN\`.SF_config WHERE value LIKE '%${OLD_DOMAIN}%';" 2>/dev/null | sed 's/^/    /'

    sub "旧库：密码哈希格式抽样（不显示实际哈希，只看长度与前缀特征）"
    q "SELECT LENGTH(password) AS len, COUNT(*) AS cnt FROM \`$DBN\`.SF_user GROUP BY LENGTH(password);" 2>/dev/null | sed 's/^/  用户表 password 长度分布: /'
    q "SELECT LENGTH(password) AS len, COUNT(*) AS cnt FROM \`$DBN\`.SF_admin GROUP BY LENGTH(password);" 2>/dev/null | sed 's/^/  管理员表 password 长度分布: /'
    echo "  （长度 32 = md5(md5(pwd))，与本地 get_password() 实现一致）"

    # ── 新库 ──
    sub "新数据库 $NEW_DB 状态"
    NEWEXIST="$(q "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$NEW_DB';")"
    if [ "$NEWEXIST" = "1" ]; then
      kv "是否存在" "是"
      kv "字符集/排序" "$(q "SELECT CONCAT(DEFAULT_CHARACTER_SET_NAME,' / ',DEFAULT_COLLATION_NAME) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$NEW_DB';")"
      kv "表数量" "$(q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$NEW_DB';")"
      echo "  已有表（应为空；非空需确认可否覆盖）："
      q "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$NEW_DB';" | head -20 | sed 's/^/    /'
      note "本次未使用新库账号连接。新库账号连通性请在建站后单独验证。"
    else
      kv "是否存在" "否（需在宝塔中创建）"
    fi
  fi
else
  note "未能定位旧站点 .env，跳过数据库检查。请确认旧站点项目根路径后重跑。"
fi

# ═══════════════════════════════════════════════════════
hd "7. 计划任务与常驻进程"
# ═══════════════════════════════════════════════════════
sub "root crontab"
crontab -l 2>/dev/null | grep -v '^#' | grep -v '^$' | redact | sed 's/^/  /' || echo "  (无)"

sub "www 用户 crontab"
crontab -u www -l 2>/dev/null | grep -v '^#' | grep -v '^$' | redact | sed 's/^/  /' || echo "  (无或用户不存在)"

sub "宝塔计划任务"
if [ -f /www/server/panel/data/default.db ] && command -v sqlite3 >/dev/null 2>&1; then
  sqlite3 -readonly /www/server/panel/data/default.db "SELECT name, type, where1, sType, sBody FROM crontab;" 2>/dev/null | redact | sed 's/|/ | /g; s/^/  /' || echo "  (查询失败)"
else
  echo "  (无法读取)"
fi

sub "/etc/cron.d"
ls -1 /etc/cron.d 2>/dev/null | sed 's/^/  /' || echo "  (无)"

sub "队列/常驻进程（think queue、supervisor、pm2）"
ps aux 2>/dev/null | grep -E 'think.*queue|supervisor|pm2' | grep -v grep | redact | sed 's/^/  /' || echo "  (无)"
command -v supervisorctl >/dev/null 2>&1 && supervisorctl status 2>/dev/null | sed 's/^/  /'

sub "Redis / Memcached"
ps aux 2>/dev/null | grep -E 'redis-server|memcached' | grep -v grep | awk '{print $11,$12,$13}' | sed 's/^/  /' || echo "  (未运行)"

# ═══════════════════════════════════════════════════════
hd "8. 新旧站点冲突检查"
# ═══════════════════════════════════════════════════════
sub "目录冲突"
if [ -n "${OLD_PROJ:-}" ] && [ -n "${NEW_PROJ:-}" ]; then
  if [ "$OLD_PROJ" = "$NEW_PROJ" ]; then
    echo "  ✗✗ 严重：新旧站点指向同一目录 $OLD_PROJ —— 绝对不能继续部署"
  else
    echo "  ✓ 目录独立"
    echo "    旧: $OLD_PROJ"
    echo "    新: $NEW_PROJ"
  fi
else
  echo "  (新站点尚未创建，部署时确保目录与旧站点不同)"
fi

sub "数据库冲突"
[ "$OLD_DB" = "$NEW_DB" ] && echo "  ✗ 新旧库同名" || echo "  ✓ 新旧库名不同：$OLD_DB / $NEW_DB"

sub "Cookie / Session 串站风险"
echo "  旧域名: $OLD_DOMAIN"
echo "  新域名: $NEW_DOMAIN"
OLD_ROOT_DOM="$(echo "$OLD_DOMAIN" | rev | cut -d. -f1,2 | rev)"
NEW_ROOT_DOM="$(echo "$NEW_DOMAIN" | rev | cut -d. -f1,2 | rev)"
if [ "$OLD_ROOT_DOM" = "$NEW_ROOT_DOM" ]; then
  echo "  ⚠ 共享父域 $OLD_ROOT_DOM —— 若 cookie domain 设为 .$OLD_ROOT_DOM 会串站"
else
  echo "  ✓ 父域不同（$OLD_ROOT_DOM / ${NEW_ROOT_DOM}），cookie 天然隔离"
fi

sub "PHP-FPM 连接池"
ls -1 /www/server/php/*/etc/php-fpm.conf 2>/dev/null | sed 's/^/  /'
echo "  （若新旧站点共用同一 PHP 版本，则共用同一 FPM 池；高负载时会相互影响，可考虑为新站点单独建池）"

sub "Session 存储位置（共用会导致串站）"
for p in /www/server/php/*/bin/php; do
  [ -x "$p" ] || continue
  v="$("$p" -r 'echo PHP_VERSION;' 2>/dev/null)"
  printf "  PHP %-8s session.save_path = %s\n" "$v" "$("$p" -r "echo ini_get('session.save_path');" 2>/dev/null)"
done
note "本项目 ThinkPHP 使用自己的 session 驱动（见 config/session.php），通常落在项目 runtime 内，天然隔离"

# ═══════════════════════════════════════════════════════
hd "9. 汇总"
# ═══════════════════════════════════════════════════════
echo "  本次巡检未对任何站点、配置、数据库执行写操作。"
echo "  旧生产环境 $OLD_DOMAIN 状态未被改变。"
echo
echo "  请把本报告完整贴回，我据此制定迁移方案。"
echo
echo "报告结束 — $(date '+%Y-%m-%d %H:%M:%S')"
