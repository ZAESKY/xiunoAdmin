# SF授权系统 - 上线部署说明

## 版本: 4.2.9 → 4.3.0

---

## 一、部署前准备

### 1.1 备份（必须执行）

```bash
# 1. 备份数据库
mysqldump -u用户名 -p 数据库名 > backup_$(date +%Y%m%d).sql

# 2. 备份配置文件
cp -r config/ config_backup/

# 3. 备份上传文件
cp -r public/upload/ upload_backup/
```

### 1.2 检查线上环境

- [ ] PHP >= 7.3（推荐 7.4）
- [ ] MySQL >= 5.5（推荐 5.7+）
- [ ] 确认 `runtime/` 目录可写
- [ ] 确认 `public/upload/` 目录可写
- [ ] 确认 `config/` 目录可写（首次部署时）

---

## 二、部署步骤

### 2.1 上传代码

```bash
# 方式1：Git 拉取
cd /path/to/project
git pull origin main

# 方式2：直接上传覆盖
# 使用 FTP/SFTP 上传所有文件，覆盖现有代码
```

### 2.2 配置 .env 文件

**重要：** 线上环境必须有 `.env` 文件。

```bash
# 复制模板文件
cp .env.example .env

# 编辑 .env 文件，修改为线上实际配置
vim .env
```

**必须修改的配置项：**

| 配置项 | 说明 | 示例 |
|--------|------|------|
| `app_debug` | **必须为 `false`** | `app_debug = false` |
| `database.hostname` | 数据库地址 | `localhost` |
| `database.database` | 数据库名 | `sf_auth` |
| `database.username` | 数据库用户 | `your_db_user` |
| `database.password` | 数据库密码 | `your_strong_password` |
| `domain.img_url` | 图片域名（完整URL） | `https://your-domain.com` |

### 2.3 执行数据库更新

```bash
# 方式1：命令行执行
mysql -u用户名 -p 数据库名 < update_online_test.sql

# 方式2：使用 phpMyAdmin 或其他工具导入 update_online_test.sql
```

### 2.4 清除缓存

```bash
# 删除文件缓存
rm -rf runtime/cache/*
rm -rf runtime/temp/*

# 如果使用 Redis
redis-cli FLUSHALL
```

### 2.5 设置目录权限

```bash
chmod -R 755 runtime/
chmod -R 755 public/upload/
chmod -R 755 public/template/assets/
chmod 644 config/*
```

---

## 三、验证更新成功

### 3.1 数据库验证

```sql
-- 检查新表是否存在
SHOW TABLES LIKE 'SF_balance_log';
SHOW TABLES LIKE 'SF_withdraw';

-- 检查模板菜单是否已删除
SELECT * FROM SF_menu WHERE url = 'Set/template';  -- 应返回空
```

### 3.2 功能验证清单

- [ ] 访问首页，确认 SF3.0 模板正常显示
- [ ] 点击"开始查询"→ 选择应用 → 查询授权，结果正常
- [ ] 点击"插件市场"→ 插件列表正常加载
- [ ] 点击插件 → 弹出"需要登录"提示
- [ ] 导航栏移动端汉堡菜单正常
- [ ] 登录后台 `/admin.php` → 侧边栏无"模板配置"菜单
- [ ] 用户端 `/user.php` → 功能正常
- [ ] 后台系统设置正常保存

### 3.3 错误检查

```bash
# 检查日志中是否有异常
tail -100 runtime/log/$(date +%Y%m)/$(date +%d).log
```

---

## 四、回滚方案

如果更新失败，按以下步骤回滚：

### 4.1 代码回滚

```bash
git revert <commit-hash>
# 或从备份恢复代码
```

### 4.2 数据库回滚

```bash
# 从备份恢复
mysql -u用户名 -p 数据库名 < backup_20260504.sql
```

### 4.3 缓存清理

```bash
rm -rf runtime/cache/*
```

---

## 五、手动配置项

以下配置需要根据线上环境手动调整：

### 5.1 支付配置（后台设置）

- 支付宝：AppID、商户私钥、支付宝公钥
- 微信支付：AppID、商户号、API密钥
- EPay 易支付：商户ID、商户密钥、网关地址

### 5.2 授权接口配置

文件 `config/sf.php` 中的 `api_url` 需要指向正确的授权服务器地址。

### 5.3 邮件配置（后台设置）

SMTP 服务器、端口、账号、密码

### 5.4 验证码配置（后台设置）

极验(GeeTest)验证码的 Captcha ID 和 Captcha Key

### 5.5 域名配置

后台系统设置中的 `域名` 配置项需改为线上实际域名。

### 5.6 HTTPS 配置

如果线上使用 HTTPS，需修改 `config/cookie.php`：
```php
'secure' => true,  // 仅 HTTPS 传输 cookie
```

---

## 六、注意事项

1. **首次部署必须创建 `.env` 文件**，否则系统会使用默认配置（风险）
2. **`app_debug` 必须设为 `false`**，否则会泄露敏感信息
3. 不要将 `.env` 文件提交到 Git 仓库
4. 定期检查 `runtime/log/` 日志大小，及时清理
5. 建议配置自动备份（crontab + mysqldump）
