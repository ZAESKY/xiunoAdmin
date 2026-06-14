-- OSS system configuration entries.
-- Safe to run multiple times on an existing database.

INSERT INTO `SF_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'oss_enabled', 'storage', '启用 OSS 上传', '开启后插件包、图标、封面上传到阿里云 OSS；关闭后全部保存到本地', 'bool', '0', '', '', '', NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name` = 'oss_enabled');

INSERT INTO `SF_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'oss_access_key_id', 'storage', 'OSS AccessKey ID', '阿里云 OSS AccessKey ID，仅后端读取，不会暴露到前端', 'string', '', '', '', '', NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name` = 'oss_access_key_id');

INSERT INTO `SF_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'oss_access_key_secret', 'storage', 'OSS AccessKey Secret', '阿里云 OSS AccessKey Secret，仅后端读取；留空则使用 .env 中的同名配置', 'string', '', '', '', 'type="password" autocomplete="new-password"', NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name` = 'oss_access_key_secret');

INSERT INTO `SF_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'oss_bucket', 'storage', 'OSS Bucket', '阿里云 OSS Bucket 名称', 'string', '', '', '', '', NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name` = 'oss_bucket');

INSERT INTO `SF_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'oss_endpoint', 'storage', 'OSS Endpoint', '例如 oss-cn-hangzhou.aliyuncs.com，支持填写完整 https:// 地址', 'string', '', '', '', '', NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name` = 'oss_endpoint');

INSERT INTO `SF_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'oss_public_base_url', 'storage', 'OSS 公开访问域名', '可填写 CDN/自定义域名，例如 https://cdn.example.com；留空则使用 Bucket Endpoint 拼接', 'string', '', '', '', '', NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name` = 'oss_public_base_url');

INSERT INTO `SF_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'oss_use_private_bucket', 'storage', 'OSS 私有 Bucket', '开启后历史版本下载由后端生成临时签名 URL', 'bool', '0', '', '', '', NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `SF_config` WHERE `name` = 'oss_use_private_bucket');
