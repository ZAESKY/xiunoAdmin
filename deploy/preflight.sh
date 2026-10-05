#!/usr/bin/env bash
#
# QH 授权系统 —— 新服务器环境自检
#
# 用法：把本文件传到新服务器任意目录，执行
#     bash preflight.sh
#
# 不需要项目代码，不连数据库，不做任何修改，纯只读检查。
# 目的：在部署之前就把"装完才发现跑不起来"的问题全部暴露出来。
#
set -uo pipefail

C_RED=$'\033[31m'; C_GRN=$'\033[32m'; C_YEL=$'\033[33m'; C_BLU=$'\033[36m'; C_RST=$'\033[0m'
PASS=0; FAIL=0; WARN=0
ok()   { echo "  ${C_GRN}✓${C_RST} $*"; PASS=$((PASS+1)); }
bad()  { echo "  ${C_RED}✗${C_RST} $*"; FAIL=$((FAIL+1)); }
warn() { echo "  ${C_YEL}!${C_RST} $*"; WARN=$((WARN+1)); }
hd()   { echo; echo "${C_BLU}══ $* ══${C_RST}"; }

echo "${C_BLU}QH 授权系统 · 新服务器环境自检${C_RST}   $(date '+%Y-%m-%d %H:%M:%S')"
echo "  主机：$(hostname)   系统：$(uname -sr)"

# ---------------------------------------------------------------
hd "1  PHP"

# 宝塔的 PHP 通常不在 PATH 里，逐个候选路径找
PHP_BIN=""
for c in php /www/server/php/*/bin/php; do
  if command -v "$c" >/dev/null 2>&1 || [ -x "$c" ]; then PHP_BIN="$c"; fi
done
if [ -z "$PHP_BIN" ]; then
  bad "未找到 php 可执行文件。宝塔请确认已安装 PHP，或用完整路径如 /www/server/php/82/bin/php"
  echo; echo "${C_RED}无法继续检查。${C_RST}"; exit 1
fi

# 若有多个版本，全部列出来，避免选错
echo "  发现的 PHP："
for c in /www/server/php/*/bin/php; do
  [ -x "$c" ] && echo "      $c  ->  $("$c" -r 'echo PHP_VERSION;' 2>/dev/null)"
done
[ -x "$(command -v php 2>/dev/null)" ] && echo "      $(command -v php)  ->  $(php -r 'echo PHP_VERSION;' 2>/dev/null)  （PATH 中的默认）"

PHPV="$("$PHP_BIN" -r 'echo PHP_VERSION;')"
echo "  本次检查使用：$PHP_BIN  ($PHPV)"

if "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION,"7.4.0",">=")?0:1);'; then
  ok "PHP $PHPV （>= 7.4）"
else
  bad "PHP $PHPV 过低，ThinkPHP 6 要求 >= 7.1，本项目建议 >= 7.4"
fi
if "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION,"8.0.0",">=")?0:1);'; then
  ok "PHP 8.x —— 与主题的 PHP 8 兼容补丁匹配"
else
  warn "PHP 7.x：可以运行，但主题的 PHP 8 兼容补丁在此版本上不会触发（也不需要）"
fi

# ---------------------------------------------------------------
hd "2  PHP 扩展"

check_ext() {
  local ext="$1" level="$2" why="$3"
  if "$PHP_BIN" -r "exit(extension_loaded('$ext')?0:1);"; then
    ok "$ext"
  else
    if [ "$level" = "must" ]; then bad "$ext 缺失 —— $why"; else warn "$ext 缺失 —— $why"; fi
  fi
}

check_ext sodium    must "授权响应与更新包的 Ed25519 验签依赖它。缺失则激活与在线更新完全不可用"
check_ext openssl   must "HTTPS 与 RSA 签名依赖它"
check_ext curl      must "与客户端通信、域名归属证明依赖它"
check_ext pdo_mysql must "数据库连接"
check_ext mbstring  must "ThinkPHP 6 必需"
check_ext json      must "ThinkPHP 6 必需"
check_ext zip       must "更新包与补丁的打包解包"
check_ext fileinfo  must "文件上传类型判定"
check_ext gd        opt  "验证码与图片处理"
check_ext redis     opt  "建议用作缓存与 nonce 存储（可消除防重放的并发窗口）"
check_ext opcache   opt  "性能；升级后代码替换需要 opcache_reset"

# sodium 真实可用性 —— 装了扩展不等于函数可用
if "$PHP_BIN" -r 'exit(function_exists("sodium_crypto_sign_detached")?0:1);'; then
  ok "sodium_crypto_sign_detached 可调用（Ed25519 就绪）"
else
  bad "sodium 已加载但 Ed25519 函数不可用，请检查扩展完整性"
fi

# ---------------------------------------------------------------
hd "3  PHP 配置"

get_ini() { "$PHP_BIN" -r "echo ini_get('$1');"; }

DF="$(get_ini disable_functions)"
for f in proc_open exec shell_exec putenv; do
  if echo "$DF" | grep -qw "$f"; then
    case "$f" in
      putenv)   warn "putenv 被禁用 —— ThinkPHP 读取 .env 可能受影响" ;;
      proc_open) warn "proc_open 被禁用 —— 部分 composer 操作会失败（运行时不影响）" ;;
      *)        warn "$f 被禁用（本项目不依赖，可忽略）" ;;
    esac
  fi
done
[ -z "$DF" ] && ok "disable_functions 为空"

for pair in "memory_limit:128M" "max_execution_time:300" "post_max_size:64M" "upload_max_filesize:64M"; do
  k="${pair%%:*}"; want="${pair##*:}"; got="$(get_ini "$k")"
  echo "      $k = ${got:-未设置}   （建议 >= ${want}）"
done
warn "更新包上传是分片的，但 max_execution_time 过小仍可能导致升级中断，建议 >= 300"

if [ "$(get_ini expose_php)" = "1" ]; then warn "expose_php=On，建议关闭（响应头会暴露 PHP 版本）"; else ok "expose_php 已关闭"; fi
if [ "$(get_ini display_errors)" = "1" ]; then bad "display_errors=On —— 生产环境必须关闭，否则报错会泄露路径与配置"; else ok "display_errors 已关闭"; fi

# ---------------------------------------------------------------
hd "4  命令行工具"
for c in mysql mysqldump unzip tar; do
  if command -v "$c" >/dev/null 2>&1; then ok "$c"; else bad "$c 缺失（迁移与备份需要）"; fi
done
command -v git >/dev/null 2>&1 && ok "git" || warn "git 缺失（用压缩包部署则不需要）"
command -v composer >/dev/null 2>&1 && ok "composer" || warn "composer 缺失（部署包已含 vendor 则不需要）"

# ---------------------------------------------------------------
hd "5  Web 服务与面板"
if [ -d /www/server/panel ]; then
  ok "检测到宝塔面板"
  [ -d /www/server/nginx ] && ok "Nginx" || { [ -d /www/server/apache ] && ok "Apache" || warn "未检测到 Nginx/Apache"; }
else
  warn "未检测到宝塔面板（不影响部署，后续步骤请按你的实际环境调整）"
fi

# ---------------------------------------------------------------
hd "6  磁盘与时间"
AVAIL="$(df -Pm /www 2>/dev/null | awk 'NR==2{print $4}')"
[ -z "$AVAIL" ] && AVAIL="$(df -Pm / | awk 'NR==2{print $4}')"
if [ "${AVAIL:-0}" -gt 2048 ]; then ok "可用空间 ${AVAIL} MB"; else bad "可用空间仅 ${AVAIL} MB，建议至少 2GB（代码 + vendor + 备份）"; fi

TZ_INI="$(get_ini date.timezone)"
echo "      PHP 时区：${TZ_INI:-未设置}   系统时间：$(date '+%Y-%m-%d %H:%M:%S %Z')"
# 授权签名有 ±300 秒时间窗，服务器时间偏差过大会导致全部请求被判过期
if command -v ntpstat >/dev/null 2>&1 || command -v chronyc >/dev/null 2>&1 || command -v timedatectl >/dev/null 2>&1; then
  ok "系统具备时间同步能力"
else
  warn "未检测到时间同步服务 —— 授权签名有 ±300 秒时间窗，时间漂移会导致请求被判为过期"
fi

# ---------------------------------------------------------------
hd "7  出网能力（域名归属证明需要）"
if command -v curl >/dev/null 2>&1; then
  if curl -sS -m 8 -o /dev/null -w '' https://www.baidu.com 2>/dev/null; then
    ok "可访问外网 HTTPS"
  else
    bad "无法访问外网 HTTPS —— 域名归属证明需要服务端反向请求客户站点，出网被封会导致所有在线激活失败"
  fi
  CAINFO="$(get_ini curl.cainfo)"
  if [ -n "$CAINFO" ] && [ -f "$CAINFO" ]; then ok "curl.cainfo = $CAINFO"
  elif [ -f /etc/ssl/certs/ca-certificates.crt ] || [ -f /etc/pki/tls/certs/ca-bundle.crt ]; then ok "系统 CA 证书就绪"
  else warn "未找到 CA 证书 —— 证书校验可能失败（我们绝不通过关闭校验来绕过）"; fi
fi

# ---------------------------------------------------------------
echo
echo "════════════════════════════════════════"
printf "通过 %d   警告 %d   ${C_RED}失败 %d${C_RST}\n" "$PASS" "$WARN" "$FAIL"
echo
if [ "$FAIL" -gt 0 ]; then
  echo "${C_RED}存在必须解决的问题（上方 ✗），解决后重跑本脚本。${C_RST}"
  echo "${C_YEL}宝塔装扩展：软件商店 → PHP 对应版本 → 设置 → 安装扩展${C_RST}"
  exit 1
fi
if [ "$WARN" -gt 0 ]; then
  echo "${C_YEL}有若干警告，多数不阻断部署，但请逐条确认是否可接受。${C_RST}"
fi
echo "${C_GRN}环境自检通过，可以开始部署。${C_RST}"
echo
echo "本次使用的 PHP 路径（后续执行 php think 命令时请用它）："
echo "    $PHP_BIN"
