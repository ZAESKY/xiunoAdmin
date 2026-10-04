# SF 授权系统 · 新环境部署手册

适用场景:把本地项目部署到新服务器的新域名,并把现有生产数据库迁移过去,
作为**平行验证环境**先跑通,确认无误后再切换。

> 前提已确认:暂无存量客户 → 换域名不会打断任何人的授权校验,
> 因此本手册不包含双域名并存与过渡期方案。

---

## 阶段一 · 新服务器环境自检

把 `deploy/preflight.sh` 传到新服务器任意目录:

```bash
bash preflight.sh
```

**必须全部通过(无 ✗)才继续。** 重点关注:

| 项 | 缺失后果 |
|---|---|
| **sodium** | Ed25519 验签不可用 → 激活与在线更新**完全无法工作** |
| curl / openssl | 无法与客户端通信、无法做域名归属证明 |
| zip | 无法打包/解包更新包与补丁 |
| **出网 HTTPS** | 域名归属证明需要服务端**反向请求**客户站点,出网被封则所有在线激活失败 |
| **系统时间同步** | 签名有 ±300 秒时间窗,时间漂移会让所有请求被判过期 |

宝塔装扩展:**软件商店 → PHP 对应版本 → 设置 → 安装扩展**。

脚本最后会打印本次使用的 PHP 路径(如 `/www/server/php/82/bin/php`),
**后续所有 `php think` 命令都用这个绝对路径**,别用 `php`(宝塔的 PATH 里往往是另一个版本)。

---

## 阶段二 · 构建并上传部署包

本地执行:

```bash
bash deploy/package.sh
```

会生成 `deploy/sf_admin_<时间戳>.tar.gz`,并打印 SHA256。

**包里已排除**:`.git`(104MB 历史)、`.env`(真实凭据)、`runtime/`、`tests/`、
`phpMyAdmin4.8.5/`(该版本线有已知高危漏洞,**绝不应部署到公网**)、根目录历史 `*.sql`。

上传后在服务器上核对哈希:

```bash
sha256sum sf_admin_*.tar.gz     # 与本地输出一致才继续
```

---

## 阶段三 · 宝塔建站(这一步最容易埋雷)

### 3.1 添加站点

宝塔 → **网站 → 添加站点**
- 域名:填新域名
- PHP 版本:选 preflight 通过的那个
- 数据库:**选"不创建"**(我们要导入现有生产库,不是建空库)

### 3.2 ⚠ 把运行目录设成 `public`

**这是整个部署最关键的一步。**

宝塔 → 站点 → **网站目录 → 运行目录** → 选 `/public` → 保存

**为什么必须这样**:项目里的 `.env`(数据库密码)、`app/`(源码)、`vendor/` 都在项目根下。
如果站点根指向项目根,这些全部可通过 HTTP 直接访问。

> 你现在的生产环境根 `.htaccess` 里有一堆
> `RedirectMatch 403 ^/(app|config|vendor|...)`——那正是"根目录指错了,只能靠黑名单补救"的痕迹。
> 黑名单一旦漏一项就是泄露。新环境把运行目录指向 `public/`,这些规则从此不再需要。

### 3.3 解压代码

```bash
cd /www/wwwroot/你的新域名
tar -xzf ~/sf_admin_*.tar.gz
ls public/index.php    # 应存在
```

### 3.4 伪静态

宝塔 → 站点 → **伪静态** → 选 **thinkphp**。若没有该模板,手动填:

```nginx
location / {
    if (!-e $request_filename){
        rewrite ^(.*)$ /index.php?s=$1 last;
    }
}

# 更新包与补丁目录:位于 public/ 之外，此处为二次兜底
location ~ ^/(app|config|vendor|addons|extend|database|deploy)/ {
    deny all;
}
location ~ /\.(env|git) {
    deny all;
}
```

### 3.5 SSL

宝塔 → 站点 → **SSL** → 部署证书 → **开启强制 HTTPS**。

授权接口**必须**走 HTTPS:更新包经明文 HTTP 传输时,任何中间人都能替换包内容,
而客户端会把它解压进论坛目录 —— 这是整套系统里影响最大的一条风险。

### 3.6 子域名(可选)

`config/app.php` 配了 `domain_bind`:`www`/`admin`/`api`/`user` 四个子域分别绑到不同应用。

- **不配子域也能用**:通过入口脚本访问即可 —— `/admin.php`、`/api.php`、`/user.php`
  (主题客户端调用的正是 `/api.php/v2/...`,不依赖子域)
- **想用子域**:在宝塔为 `admin.新域名`、`api.新域名`、`user.新域名` 各加一个站点,
  运行目录同样指向同一份代码的 `public/`

**建议先不配子域**,用入口脚本跑通,减少变量。

---

## 阶段四 · 迁移数据库

### 4.1 从旧生产导出

在**旧服务器**上:

```bash
mysqldump -u<用户> -p --single-transaction --quick \
  --routines --triggers --events --default-character-set=utf8mb4 \
  <旧库名> > sf_prod_$(date +%Y%m%d).sql

gzip sf_prod_*.sql
```

`--single-transaction` 保证导出期间不锁表,旧站点可继续正常服务。

### 4.2 在新服务器创建库并导入

宝塔 → **数据库 → 添加数据库**(记下用户名密码),然后:

```bash
gunzip -c sf_prod_*.sql.gz | mysql -u<新用户> -p <新库名>
```

### 4.3 核对导入完整性

```bash
mysql -u<新用户> -p <新库名> -e "
  SELECT COUNT(*) AS tables FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE();
  SELECT COUNT(*) AS auth_rows FROM SF_auth;
  SELECT COUNT(*) AS app_rows FROM SF_app;
  SELECT COUNT(*) AS user_rows FROM SF_user;"
```

**逐个与旧库的数字比对,完全一致才继续。**

---

## 阶段五 · 配置

### 5.1 生成 `.env`

```bash
cd /www/wwwroot/你的新域名
cp .env.example .env
chmod 600 .env        # 只有属主可读
vi .env
```

必填:

```ini
app_debug = false                  # 生产必须 false

database_hostname = 127.0.0.1
database_database = <新库名>
database_username = <新用户>
database_password = <新密码>
database_hostport = 3306

domain_img_url = https://你的新域名      # 不带末尾斜杠
```

### 5.2 生成密钥

```bash
PHP=/www/server/php/82/bin/php      # 用 preflight 打印的路径

$PHP think sf:keygen license        # 输出三行，贴进 .env
$PHP think sf:keygen release        # 输出三行，贴进 .env

$PHP -r 'echo "security_pepper = ".bin2hex(random_bytes(32))."\n";'
$PHP -r 'echo "license_secret_key = ".bin2hex(random_bytes(32))."\n";'
```

把上面四组输出全部写进 `.env`。

> **`security_pepper` 一经设置就不能再改** —— 它参与授权码哈希,
> 改动会让所有已回填的 `authcode_hash` 全部失效。

> **`release_sign_secret_key`**:严格来说不该放在授权服务器上。
> 更安全的做法是只在本地/离线机保留,发布时在本地执行 `sf:sign` 后把结果导入线上。
> 初期为了简便可以先放服务器,但要记在待办里。

### 5.3 目录权限

```bash
chown -R www:www /www/wwwroot/你的新域名
chmod -R 755 /www/wwwroot/你的新域名
chmod -R 775 runtime public/upload app/common/download

# 插件包使用发布目录之外的持久化目录，避免新版本发布后丢失或因 release 只读而上传失败
mkdir -p /www/wwwroot/你的新域名/shared/storage/plugins
chown -R www:www /www/wwwroot/你的新域名/shared/storage
chmod -R 750 /www/wwwroot/你的新域名/shared/storage
# 并在 .env 中设置：plugin_storage_path = /www/wwwroot/你的新域名/shared/storage/plugins
chmod 600 .env
```

### 5.4 保留安装锁

```bash
ls app/install/SF_Auth.Lock     # 必须存在
```

不存在的话系统会认为未安装并跳转到安装向导。部署包里已包含,正常不用管。

---

## 阶段六 · 执行 schema 迁移

```bash
bash database/migrate.sh check      # 只检查
bash database/migrate.sh dry-run    # 备份 + 预演
bash database/migrate.sh run        # 正式执行（自动先备份）
```

然后回填授权码哈希:

```bash
$PHP think sf:authcode-backfill --dry-run
$PHP think sf:authcode-backfill
```

### 更新数据库里的域名相关配置

```sql
UPDATE `SF_config` SET `value` = 'https://你的新域名' WHERE `name` = 'download_base_url';
UPDATE `SF_config` SET `value` = '1'                  WHERE `name` = 'force_https_download';
-- 若启用了 OSS
UPDATE `SF_config` SET `value` = 'https://你的CDN域名' WHERE `name` = 'oss_public_base_url';
```

---

## 阶段七 · 上传附件

`public/upload/` 约 30MB,不在部署包里。用 rsync 单独同步:

```bash
rsync -avz --progress \
  旧服务器:/旧路径/public/upload/ \
  /www/wwwroot/你的新域名/public/upload/
chown -R www:www public/upload
```

---

## 阶段八 · 验证清单

逐条勾,任一条不过就别往下走。

**基础**
- [ ] `https://新域名/` 正常打开,无报错
- [ ] `https://新域名/admin.php` 后台可登录
- [ ] `https://新域名/.env` 返回 **403/404**(返回内容即为严重泄露,立刻回到 3.2)
- [ ] `https://新域名/app/common/download/` 返回 403/404
- [ ] `https://新域名/vendor/` 返回 403/404
- [ ] 浏览器地址栏显示 HTTPS 锁标,证书有效

**授权系统**
- [ ] 后台能看到用户、授权、应用列表,数据与旧环境一致
- [ ] `bash database/migrate.sh verify` 全绿
- [ ] `SF_auth` 与 `SF_auth_legacy` 行数一致
- [ ] 上传/下载附件正常

**接口**
```bash
# v2 路由存活（未带签名，预期返回缺少 License 标识的 JSON，而不是 404/500）
curl -s https://新域名/api.php/v2/license/status -X POST -d '{}' | head -c 200

# v1 路由仍然可用（向后兼容）
curl -s "https://新域名/api.php/Auth/checkAuth" | head -c 200
```

**签名管线**
```bash
$PHP think sf:sign release /tmp/test.zip --product=zaesky_theme_light \
  --build=999999 --edition=test --dry-run
```
应输出"清单构建并验签通过"。

---

## 阶段九 · 主题侧配套

新域名确定后,主题客户端必须同步更新
`/Applications/ServBay/www/xiuno/plugin/zaesky_theme_light/model/zaesky_license/Core.php`:

```php
const API_BASE = 'https://你的新域名';     // 当前是 https://www.noteweb.top

public static function publicKeys()
{
    return array(
        'license-2026xxxx-xxxxxxxx' => '刚才 sf:keygen license 输出的公钥',
        'release-2026xxxx-xxxxxxxx' => '刚才 sf:keygen release 输出的公钥',
    );
}
```

正式主题包的授权运行目录只能包含 `Bootstrap.php` 与 `Core.php`。发布前用同一
PHP 目标版本编码这两个文件；不要把拆分前的 `Config.php`、`License.php`、
`Updater.php` 等明文模块放进压缩包。授权系统会拒绝缺少 `Core.php` 或夹带旧
模块源码的主题完整包。

`publicKeys()` 必须同时包含当前线上使用的 license 与 release 公钥；公钥可以
随主题分发，私钥只能保留在授权服务器或离线签名机。若公钥表为空或 key_id
不匹配，客户端会按 fail-closed 拒绝激活和更新。

改完在本地跑一遍一致性测试再打包分发:

```bash
php tests/security/run_theme_tests.php
```

---

## 阶段十 · 切换与回退

**切换**:验证清单全绿后,把域名 DNS 指向新服务器。
旧环境**先别停**,保留至少 7 天。

**回退**:DNS 指回旧服务器即可。旧环境全程未被改动,回退是秒级的 ——
这正是选择"平行验证环境"而非"原地升级"的价值。

**旧环境退役前确认**:
- [ ] 新环境稳定运行 ≥ 7 天,无异常日志
- [ ] 旧库已做最终全量备份并异地留存
- [ ] `public/upload/` 已完整同步(做一次 `rsync --dry-run` 确认无差异)

---

## 附:遇到问题时

| 现象 | 排查方向 |
|---|---|
| 白屏 / 500 | `runtime/log/` 下的日志;`.env` 的 `app_debug` 临时开 true 看报错,**看完立刻改回 false** |
| 路由 404 | 伪静态未配,或运行目录没指向 `public/` |
| 数据库连不上 | `.env` 的 `database_hostname` 用 `127.0.0.1` 而非 `localhost`(socket 路径问题) |
| 激活提示验签失败 | `publicKeys()` 没填,或与服务器 `.env` 里的密钥不是同一对 |
| 激活提示站点未绑定 | 两端 `site_id` 算法不一致 —— 跑 `run_theme_tests.php` 应能立刻定位 |
| 所有请求提示已过期 | 服务器时间漂移超过 300 秒,校准 NTP |
