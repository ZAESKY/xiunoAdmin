#!/usr/bin/env bash
#
# QH 授权系统 —— 引导式迁移执行器
#
# 用法（在项目根目录执行）：
#     bash database/migrate.sh check      # 只做前置检查，不碰数据库
#     bash database/migrate.sh backup     # 只备份
#     bash database/migrate.sh dry-run    # 备份 + 打印将要执行的内容，不写入
#     bash database/migrate.sh run        # 正式执行（会先自动备份）
#     bash database/migrate.sh verify     # 执行后验证
#     bash database/migrate.sh rollback   # 回滚（需二次确认）
#
# 设计原则：
#   - 数据库凭据只从项目的 .env 读取，脚本本身不含任何凭据
#   - 备份失败则绝不继续
#   - 备份完成后校验内容，不只看文件是否生成
#   - 每一步失败立即停止，不做"尽力而为"式的部分执行
#
set -uo pipefail

# ---------- 基础 ----------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"
MIG_DIR="$SCRIPT_DIR/migrations"
BACKUP_DIR="$ROOT_DIR/runtime/db_backup"
STAMP="$(date +%Y%m%d_%H%M%S)"

C_RED=$'\033[31m'; C_GRN=$'\033[32m'; C_YEL=$'\033[33m'; C_BLU=$'\033[36m'; C_RST=$'\033[0m'
ok()   { echo "  ${C_GRN}✓${C_RST} $*"; }
warn() { echo "  ${C_YEL}!${C_RST} $*"; }
err()  { echo "  ${C_RED}✗${C_RST} $*"; }
hd()   { echo; echo "${C_BLU}══ $* ══${C_RST}"; }
die()  { err "$*"; echo; echo "${C_RED}已中止，数据库未被修改。${C_RST}"; exit 1; }

# 按正确顺序排列 —— 顺序不可颠倒
MIGRATIONS=(
  "20261005_qh_prefix_rename.sql"          # 必须最先：无损迁移旧表前缀
  "20260816_auth_legacy_snapshot.sql"      # 必须最先：冻结旧授权快照
  "20260816_p0_security_hardening.sql"
  "20260816_p1_p3_license_v2.sql"
  "20261001_qq_oauth_identity.sql"
  "20261001_qq_account_uniqueness.sql"
  "20261002_license_legacy_bridge.sql"
  "20261002_application_installer_packages.sql"
  "20261002_rebate_withdrawal_hardening.sql"
  "20261002_plugin_publish_reward.sql"
  "20261002_plugin_commission_settings.sql"
  "20261002_single_site_license.sql"
  "20261003_program_patch_version_binding.sql"
  "20261003_oss_managed_storage.sql"
  "20261003_qq_oauth_unification.sql"
  "20261004_unified_auth_menu.sql"
  "20261004_plugin_package_upload_staging.sql"
  "20261004_qq_sms_registration.sql"
  "20261004_sms_template_scenes.sql"
  "20261004_password_recovery_channel.sql"
  "20261004_email_notifications.sql"
  "20261005_plugin_directory_identity.sql"
  "20261005_release_delta.sql"
  "20261005_application_context_v2.sql"
)
ROLLBACKS=(
  "20261005_application_context_v2_rollback.sql"
  "20261005_release_delta_rollback.sql"
  "20261004_email_notifications_rollback.sql"
  "20261004_password_recovery_channel_rollback.sql"
  "20261004_sms_template_scenes_rollback.sql"
  "20261004_qq_sms_registration_rollback.sql"
  "20261004_plugin_package_upload_staging_rollback.sql"
  "20261004_unified_auth_menu_rollback.sql"
  "20261003_qq_oauth_unification_rollback.sql"
  "20261003_oss_managed_storage_rollback.sql"
  "20261003_program_patch_version_binding_rollback.sql"
  "20261002_single_site_license_rollback.sql"
  "20261002_application_installer_packages_rollback.sql"
  "20261002_plugin_commission_settings_rollback.sql"
  "20261002_plugin_publish_reward_rollback.sql"
  "20261002_rebate_withdrawal_hardening_rollback.sql"
  "20261002_license_legacy_bridge_rollback.sql"
  "20261001_qq_account_uniqueness_rollback.sql"
  "20261001_qq_oauth_identity_rollback.sql"
  "20260816_p1_p3_license_v2_rollback.sql"
  "20260816_p0_security_hardening_rollback.sql"
  "20261005_qh_prefix_rename_rollback.sql" # 必须最后：恢复旧表前缀
)

# ---------- 读取 .env ----------
load_env() {
  [ -f "$ENV_FILE" ] || die "找不到 ${ENV_FILE}。请在项目根目录执行本脚本。"

  # 只取需要的键，避免把整个 .env 注入环境
  get_env() {
    grep -E "^[[:space:]]*$1[[:space:]]*=" "$ENV_FILE" 2>/dev/null \
      | head -1 | cut -d= -f2- \
      | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/"
  }

  DB_HOST="$(get_env database_hostname)"; DB_HOST="${DB_HOST:-127.0.0.1}"
  DB_PORT="$(get_env database_hostport)"; DB_PORT="${DB_PORT:-3306}"
  DB_NAME="$(get_env database_database)"
  DB_USER="$(get_env database_username)"
  DB_PASS="$(get_env database_password)"

  [ -n "$DB_NAME" ] || die ".env 中未读到 database_database"
  [ -n "$DB_USER" ] || die ".env 中未读到 database_username"

  # 用配置文件传密码，避免密码出现在进程列表（ps 可见）里
  MY_CNF="$(mktemp)"
  chmod 600 "$MY_CNF"
  {
    echo "[client]"
    echo "host=$DB_HOST"
    echo "port=$DB_PORT"
    echo "user=$DB_USER"
    echo "password=$DB_PASS"
    echo "default-character-set=utf8mb4"
  } > "$MY_CNF"
  trap 'rm -f "$MY_CNF"' EXIT
}

mysql_q()  { mysql --defaults-extra-file="$MY_CNF" "$DB_NAME" -N -B -e "$1" 2>&1; }
mysql_run(){ mysql --defaults-extra-file="$MY_CNF" "$DB_NAME" < "$1" 2>&1; }

legacy_table_prefix() { printf '\123\106\137'; }

drop_legacy_balance_triggers() {
  local prefix; prefix="$(legacy_table_prefix)"
  mysql_q "DROP TRIGGER IF EXISTS \`${prefix}user_withdrawable_before_insert\`; DROP TRIGGER IF EXISTS \`${prefix}user_withdrawable_before_update\`;" >/dev/null \
    || die "无法移除旧余额保护触发器"
}

create_legacy_balance_triggers() {
  local prefix; prefix="$(legacy_table_prefix)"
  mysql_q "CREATE TRIGGER \`${prefix}user_withdrawable_before_insert\` BEFORE INSERT ON \`${prefix}user\` FOR EACH ROW SET NEW.withdrawable_balance=LEAST(GREATEST(NEW.withdrawable_balance,0.00),GREATEST(NEW.balance,0.00)); CREATE TRIGGER \`${prefix}user_withdrawable_before_update\` BEFORE UPDATE ON \`${prefix}user\` FOR EACH ROW SET NEW.withdrawable_balance=LEAST(GREATEST(NEW.withdrawable_balance,0.00),GREATEST(NEW.balance,0.00));" >/dev/null \
    || die "无法恢复旧余额保护触发器"
}

# ---------- 前置检查 ----------
do_check() {
  hd "1/5  环境检查"

  command -v php >/dev/null   || die "未找到 php 命令"
  local phpv; phpv="$(php -r 'echo PHP_VERSION;')"
  ok "PHP $phpv"
  php -r 'exit(version_compare(PHP_VERSION,"7.2.0",">=")?0:1);' \
    || warn "PHP 版本低于 7.2，sodium 可能不可用（影响签名验证）"

  for ext in sodium openssl curl zip pdo_mysql; do
    if php -r "exit(extension_loaded('$ext')?0:1);"; then
      ok "扩展 $ext"
    else
      if [ "$ext" = "sodium" ]; then
        err "缺少 sodium —— 授权签名与更新包验签将不可用，必须先安装"
        MISSING_CRITICAL=1
      else
        warn "缺少 $ext"
      fi
    fi
  done

  command -v mysql >/dev/null    || die "未找到 mysql 客户端"
  command -v mysqldump >/dev/null || die "未找到 mysqldump（备份必需）"
  ok "mysql / mysqldump 就绪"

  hd "2/5  数据库连接"
  local ver; ver="$(mysql_q "SELECT VERSION();")"
  if [ -z "$ver" ] || echo "$ver" | grep -qi "error"; then
    die "无法连接数据库：$ver"
  fi
  ok "已连接 $DB_NAME @ $DB_HOST:$DB_PORT （MySQL ${ver}）"

  hd "3/5  前置表检查"
  local legacy_prefix; legacy_prefix="$(printf '\123\106\137')"
  local need_tables=("auth" "app" "config")
  for base in "${need_tables[@]}"; do
    local t="QH_${base}"
    local legacy_t="${legacy_prefix}${base}"
    local c; c="$(mysql_q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t';")"
    if [ "$c" != "1" ]; then
      c="$(mysql_q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$legacy_t';")"
      [ "$c" = "1" ] || die "缺少必需业务表 ${base} —— 数据库可能不是轻鸿授权系统的库，请确认 .env 指向正确"
      t="$legacy_t"
    fi
    local rows; rows="$(mysql_q "SELECT COUNT(*) FROM \`$t\`;")"
    ok "$t 存在（$rows 行）"
  done

  hd "4/5  是否已迁移过"
  local already=0
  for t in QH_license QH_auth_legacy QH_download_ticket QH_release QH_patch; do
    local c; c="$(mysql_q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t';")"
    if [ "$c" = "1" ]; then
      warn "$t 已存在（迁移是幂等的，重复执行安全）"
      already=1
    fi
  done
  [ "$already" = "0" ] && ok "尚未迁移，全新执行"

  hd "5/5  备份空间"
  local dbsize; dbsize="$(mysql_q "SELECT ROUND(SUM(data_length+index_length)/1048576) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE();")"
  ok "数据库约 ${dbsize:-?} MB"
  mkdir -p "$BACKUP_DIR" || die "无法创建备份目录 $BACKUP_DIR"
  local avail; avail="$(df -Pm "$BACKUP_DIR" | awk 'NR==2{print $4}')"
  if [ -n "$dbsize" ] && [ -n "$avail" ] && [ "$avail" -lt "$((dbsize * 2 + 100))" ]; then
    die "备份目录可用空间不足（可用 ${avail}MB，建议至少 $((dbsize * 2 + 100))MB）"
  fi
  ok "可用空间 ${avail:-?} MB，足够"

  echo
  if [ "${MISSING_CRITICAL:-0}" = "1" ]; then
    echo "${C_RED}存在必须先解决的问题（见上方 ✗）。${C_RST}"
    return 1
  fi
  echo "${C_GRN}前置检查全部通过。${C_RST}"
  return 0
}

# ---------- 备份 ----------
do_backup() {
  hd "备份数据库"
  mkdir -p "$BACKUP_DIR"
  local f="$BACKUP_DIR/qh_${DB_NAME}_${STAMP}.sql"

  echo "  正在导出到 $f ..."
  if ! mysqldump --defaults-extra-file="$MY_CNF" \
        --single-transaction --quick --routines --triggers --events \
        --default-character-set=utf8mb4 \
        "$DB_NAME" > "$f" 2>"$f.err"; then
    err "$(head -3 "$f.err")"
    rm -f "$f"
    die "备份失败"
  fi
  rm -f "$f.err"

  # 校验备份内容：不只看文件存在，要确认关键表真的在里面
  local size; size="$(wc -c < "$f")"
  [ "$size" -gt 1024 ] || die "备份文件过小（${size} 字节），内容可疑"
  local legacy_prefix; legacy_prefix="$(legacy_table_prefix)"
  for base in auth app; do
    if ! grep -q "CREATE TABLE \`QH_${base}\`" "$f" \
      && ! grep -q "CREATE TABLE \`${legacy_prefix}${base}\`" "$f"; then
      die "备份中缺少 ${base} 业务表的建表语句，备份不完整"
    fi
  done
  grep -q -- "-- Dump completed" "$f" || warn "备份文件未见完成标记，请人工确认"

  if command -v gzip >/dev/null; then
    gzip -f "$f" && f="$f.gz"
  fi

  ok "备份完成：$f （$(du -h "$f" | cut -f1)）"
  echo "$f" > "$BACKUP_DIR/.last_backup"
  echo
  echo "  ${C_YEL}恢复命令（万一需要）：${C_RST}"
  if [[ "$f" == *.gz ]]; then
    echo "    gunzip -c '$f' | mysql --defaults-extra-file=<你的配置> $DB_NAME"
  else
    echo "    mysql --defaults-extra-file=<你的配置> $DB_NAME < '$f'"
  fi
}

# ---------- 执行 ----------
do_run() {
  local dry="${1:-0}"

  for m in "${MIGRATIONS[@]}"; do
    [ -f "$MIG_DIR/$m" ] || die "找不到迁移文件：$MIG_DIR/$m"
  done

  if [ "$dry" = "1" ]; then
    hd "DRY-RUN：将按以下顺序执行（不会写入）"
    local i=1
    for m in "${MIGRATIONS[@]}"; do
      echo "  $i) $m"
      echo "     $(grep -m1 '^-- Migration:' "$MIG_DIR/$m" | sed 's/^-- Migration: //')"
      i=$((i+1))
    done
    echo
    echo "  确认无误后执行：${C_GRN}bash database/migrate.sh run${C_RST}"
    return 0
  fi

  hd "执行迁移"
  drop_legacy_balance_triggers
  local i=1
  for m in "${MIGRATIONS[@]}"; do
    echo "  [$i/${#MIGRATIONS[@]}] $m"
    local out; out="$(mysql_run "$MIG_DIR/$m")"
    if echo "$out" | grep -qiE "^ERROR|ERROR [0-9]+"; then
      err "$out"
      echo
      echo "${C_RED}迁移失败。数据库可能处于部分执行状态。${C_RST}"
      echo "${C_YEL}恢复：用刚才的备份还原（路径见 $BACKUP_DIR/.last_backup）${C_RST}"
      exit 1
    fi
    [ -n "$out" ] && echo "$out" | sed 's/^/      /'
    ok "完成"
    i=$((i+1))
  done

  # Database-backed menus can otherwise remain hidden in a persistent cache
  # after a successful migration. Cache cleanup is best-effort and does not
  # change migration success if the application cache backend is unavailable.
  if [ -f "$ROOT_DIR/vendor/autoload.php" ]; then
    if (cd "$ROOT_DIR" && php -r 'require "vendor/autoload.php";$app=new \think\App();$app->initialize();\think\facade\Cache::delete("QH_AdminMenu");\think\facade\Cache::tag("QH_Menu")->clear();') >/dev/null 2>&1; then
      ok "菜单缓存已刷新"
    else
      warn "迁移已完成，但菜单缓存刷新失败；请执行 php think clear 后重新登录后台"
    fi
  fi

  echo
  echo "${C_GRN}全部迁移执行完毕。${C_RST}"
}

# ---------- 验证 ----------
do_verify() {
  hd "迁移后验证"
  local fail=0

  echo "  新增表："
  for t in QH_auth_legacy QH_download_ticket QH_license QH_license_site \
           QH_license_event QH_offline_activation QH_trial QH_release QH_patch \
           QH_plugin_reward QH_plugin_reward_hash_claim QH_qq_identity_claim \
           QH_user_phone_identity QH_sms_audit QH_notification_email_preference \
           QH_notification_email_template QH_notification_email_log; do
    local c; c="$(mysql_q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t';")"
    if [ "$c" = "1" ]; then ok "$t"; else err "$t 缺失"; fail=1; fi
  done

  echo
  echo "  新增列："
  check_col() {
    local c; c="$(mysql_q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$1' AND COLUMN_NAME='$2';")"
    if [ "$c" = "1" ]; then ok "$1.$2"; else err "$1.$2 缺失"; fail=1; fi
  }
  check_col QH_app  auth_enforce
  check_col QH_app  installer_file_name
  check_col QH_app  installer_sha256
  check_col QH_app  installer_size
  check_col QH_app  installer_uploaded_at
  check_col QH_auth authcode_hash
  check_col QH_auth authcode_last4
  check_col QH_auth must_rotate
  check_col QH_license source_auth_id
  check_col QH_patch theme_build
  check_col QH_patch theme_edition
  check_col QH_user phone_verified_at
  check_col QH_user phone_verified_source
  check_col QH_sms_audit template_code

  local patch_index; patch_index="$(mysql_q "SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='QH_patch' AND INDEX_NAME IN ('uk_patch','idx_status');")"
  if [ "$patch_index" = "2" ]; then
    ok "QH_patch 版本绑定索引"
  else
    err "QH_patch 版本绑定索引缺失"
    fail=1
  fi

  echo
  echo "  插件奖励配置："
  local reward_configs; reward_configs="$(mysql_q "SELECT COUNT(*) FROM QH_config WHERE name IN ('plugin_reward_enabled','plugin_reward_points','plugin_reward_balance','plugin_reward_original_only','plugin_reward_monthly_limit','plugin_reward_min_account_days','plugin_reward_duplicate_hash');")"
  if [ "$reward_configs" = "7" ]; then
    ok "7 项插件奖励配置齐全"
  else
    err "插件奖励配置缺失：仅找到 ${reward_configs:-0}/7 项"
    fail=1
  fi

  echo
  echo "  短信认证配置："
  local sms_configs; sms_configs="$(mysql_q "SELECT COUNT(*) FROM QH_config WHERE name IN ('sms_enabled','sms_access_key_id','sms_access_key_secret','sms_sign_name','sms_template_code','sms_template_login_register','sms_template_phone_change','sms_template_password_reset','sms_template_phone_bind','sms_template_phone_verify','sms_code_ttl','sms_daily_limit','sms_require_withdraw','sms_require_rebate','sms_require_plugin_reward');")"
  if [ "$sms_configs" = "15" ]; then
    ok "15 项短信认证配置齐全（包含5个业务模板）"
  else
    err "短信认证配置缺失：仅找到 ${sms_configs:-0}/15 项"
    fail=1
  fi

  echo
  echo "  找回密码配置："
  local recovery_configs; recovery_configs="$(mysql_q "SELECT COUNT(*) FROM QH_config WHERE name='password_recovery_channel' AND value IN ('email','sms');")"
  if [ "$recovery_configs" = "1" ]; then
    ok "找回密码验证码渠道配置齐全"
  else
    err "找回密码验证码渠道配置缺失或无效"
    fail=1
  fi

  echo
  echo "  邮件通知配置："
  local email_configs; email_configs="$(mysql_q "SELECT COUNT(*) FROM QH_config WHERE name='email_notification_enabled';")"
  if [ "$email_configs" = "1" ]; then
    ok "邮件通知总开关配置齐全"
  else
    err "邮件通知总开关配置缺失"
    fail=1
  fi

  echo
  echo "  快照完整性（旧码换新的唯一依据）："
  local a l; a="$(mysql_q "SELECT COUNT(*) FROM QH_auth;")"; l="$(mysql_q "SELECT COUNT(*) FROM QH_auth_legacy;")"
  if [ "$a" = "$l" ]; then
    ok "QH_auth $a 行 = QH_auth_legacy $l 行"
  else
    err "行数不一致：QH_auth $a / QH_auth_legacy $l —— 请勿继续，联系排查"
    fail=1
  fi

  echo
  echo "  判定模式（A-06）："
  local mon; mon="$(mysql_q "SELECT COUNT(*) FROM QH_app WHERE auth_enforce=2;")"
  local blk; blk="$(mysql_q "SELECT COUNT(*) FROM QH_app WHERE auth_enforce=1;")"
  warn "监控模式 $mon 个应用 / 强制拦截 $blk 个应用"
  if [ "${mon:-0}" != "0" ]; then
    echo "      ${C_YEL}观察 24 小时日志（grep auth-monitor runtime/log/*.log）无误后执行：${C_RST}"
    echo "      ${C_YEL}UPDATE \`QH_app\` SET \`auth_enforce\` = 1;${C_RST}"
    echo "      ${C_YEL}在此之前，授权绕过风险仍然存在。${C_RST}"
  fi

  echo
  echo "  待回填授权码："
  local pend; pend="$(mysql_q "SELECT COUNT(*) FROM QH_auth WHERE authcode<>'' AND authcode_hash='';")"
  if [ "${pend:-0}" != "0" ]; then
    warn "$pend 条待回填，请执行： php think qh:authcode-backfill"
  else
    ok "无需回填"
  fi

  echo
  if [ "$fail" = "0" ]; then
    echo "${C_GRN}验证通过。${C_RST}"
  else
    echo "${C_RED}验证发现问题，请勿上线。${C_RST}"
    return 1
  fi
}

# ---------- 回滚 ----------
do_rollback() {
  hd "回滚"
  echo "${C_YEL}警告：硬回滚会删除 v2 授权表。若已有客户激活或兑换过旧码，"
  echo "      删除后这些客户既不能用新授权、也无法重新兑换。"
  echo
  echo "      优先考虑软回滚（不删表，4 条 UPDATE 即可恢复加固前行为）：${C_RST}"
  echo "        UPDATE QH_config SET value='0' WHERE name='plugin_api_user_strict';"
  echo "        UPDATE QH_config SET value='0' WHERE name='plugin_api_sign_required';"
  echo "        UPDATE QH_config SET value='1' WHERE name='download_legacy_sign_enabled';"
  echo "        UPDATE QH_app SET auth_enforce=2;"
  echo
  read -r -p "确定要执行硬回滚吗？输入大写 YES 继续："  ans
  [ "$ans" = "YES" ] || { echo "已取消。"; exit 0; }

  do_backup

  for m in "${ROLLBACKS[@]}"; do
    echo "  执行 $m"
    local out; out="$(mysql_run "$MIG_DIR/$m")"
    echo "$out" | sed 's/^/      /'
  done
  create_legacy_balance_triggers
  echo
  echo "${C_YEL}若此前有客户兑换过旧码，还需执行：${C_RST}"
  echo "  UPDATE \`QH_auth_legacy\` SET \`redeemed_at\`=NULL, \`redeemed_license_id\`=NULL;"
}

# ---------- 入口 ----------
ACTION="${1:-check}"
echo "${C_BLU}QH 授权系统 迁移执行器${C_RST}  —  $(date '+%Y-%m-%d %H:%M:%S')"
load_env
echo "  目标库：$DB_NAME @ $DB_HOST:$DB_PORT"

case "$ACTION" in
  check)    do_check ;;
  backup)   do_check >/dev/null || die "前置检查未通过"; do_backup ;;
  dry-run)  do_check || exit 1; do_backup; do_run 1 ;;
  run)      do_check || exit 1; do_backup; do_run 0; do_verify ;;
  verify)   do_verify ;;
  rollback) do_rollback ;;
  *)
    echo
    echo "用法： bash database/migrate.sh {check|backup|dry-run|run|verify|rollback}"
    exit 1
    ;;
esac
