DROP TABLE IF EXISTS `SF_config`;
CREATE TABLE `SF_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '变量名',
  `group` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '分组',
  `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '变量标题',
  `tip` text COLLATE utf8mb4_unicode_ci COMMENT '变量描述',
  `type` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '类型:string,text,int,bool,array,datetime,date,file',
  `value` text COLLATE utf8mb4_unicode_ci COMMENT '变量值',
  `content` text COLLATE utf8mb4_unicode_ci COMMENT '变量字典数据',
  `rule` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '验证规则',
  `extend` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '扩展属性',
  `tip_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '描述样式',
  PRIMARY KEY (`id`),KEY (`name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置';

INSERT INTO `SF_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`) VALUES
('title', 'site', '站点名称', '请填写站点名称', 'string', 'SF授权平台', '', 'required', '', NULL),
('keywords', 'site', '站点关键词', '请填写站点关键词', 'string', 'PHP网络验证系统,多应用授权系统,SF综合验证授权系统,SF授权系统,SF授权站,授权中心,授权系统,机器人授权,授权', '', '', '', NULL),
('description', 'site', '站点描述', '请填写站点描述', 'text', 'SF综合验证授权系统，专业帮助站长开发程序，帮您火速开发程序，我们提供最专业的售前指导，提供最优质的售后服务，给您一个放心的平台！', '', '', '', NULL),
('foot', 'site', '底部版权', '请填写底部版权', 'string', 'Copyright © SF-陌上花开', '', 'required', '', NULL),
('ICP', 'site', 'ICP备案', '请填写备案号', 'string', '', '', '', '', NULL),
('kfqq', 'site', '客服QQ', '可填写多个客服，回车来分割多个客服。<br>“|”来分割跳转联系方式和客服名称。', 'text', 'tencent://AddContact/?fromId=45&fromSubId=1&subcmd=all&uin=2129876388|客服一号', '', '', '', 'tag-orange'),
('qq_group', 'site', 'QQ群链接', '用于前台加入群聊时跳转', 'string', 'https://jq.qq.com/?_wv=1027&k=', '', '', '', NULL),
('site_background', 'site', '背景图片', '', 'radio', '0', '{"0":"关闭","10":"自定义壁纸","1":"Bing随机壁纸","2":"Bing每日壁纸","3":"随机美女壁纸","4":"随机二次元壁纸","5":"随机汽车壁纸","6":"随机动漫壁纸","7":"随机背景壁纸","8":"随机高清风景壁纸","9":"随机MC酱动漫壁纸"}', '', '', NULL),
('diy_background', 'site', '自定义背景', '请填写自定义背景图片链接地址', 'images', '', '', '', '', NULL),
('site_background_css', 'site', '背景样式', '', 'radio', '0', '{"0":"关闭","1":"自定义样式","2":"纵向和横向重复","3":"横向重复,纵向拉伸","4":"纵向重复,横向拉伸","5":"不重复,全屏拉伸"}', '', '', NULL),
('diy_site_background_css', 'site', '自定义背景样式', '直接写CSS代码即可，不带<style>标签', 'text', '', '', '', '', NULL),
('site_background_music', 'site', '背景音乐', '', 'radio', '0', '{"0":"关闭","1":"自定义音乐代码","2":"自定义音乐ID","3":"自定义背景语音","4":"网易云榜单"}', '', '', NULL),
('netease_music_toplist', 'site', '网易云榜单', '', 'radio', '0', '{"0":"云音乐飙升榜","1":"云音乐新歌榜","2":"网易原创歌曲榜","3":"云音乐热歌榜","4":"云音乐说唱榜","5":"云音乐电音榜","6":"抖音排行榜","7":"新声榜","8":"云音乐ACG音乐榜","9":"iTunes榜","10":"云音乐日语榜","11":"云音乐民谣榜","12":"云音乐摇滚榜"}', '', '', NULL),
('diy_background_music', 'site', '自定义音乐代码', '请填写自定义音乐代码', 'text', '', '', '', '', NULL),
('diy_background_music_id', 'site', '音乐ID', '一行一首<br><div class=layui-collapse lay-shrink=_all style=background-color:#fff><div class=layui-colla-item><h2 class=layui-colla-title>格式</h2><div class="layui-colla-content layui-show"><p>音乐ID|音乐平台|ID类型<br>（ID类型可选填）</p><br><li><span class="layui-badge-dot layui-bg-cyan"></span> 单首音乐例如：002ejEdb4KTwBw|tencent<br>（这首歌名字叫浮夸来自QQ音乐）</li><li><span class="layui-badge-dot layui-bg-cyan"></span> 歌单例如：6666124298|tencent|playlist<br>（这是我QQ音乐的歌单）</li></div></div><div class=layui-colla-item><h2 class=layui-colla-title>音乐ID怎么取？</h2><div class=layui-colla-content><p><b><font color=#c7254e>标红</font></b> 为 <strong>音乐 ID</strong>，<u>下划线</u> 表示 <strong>音乐地址</strong></p><ul><li><span class="layui-badge-dot layui-bg-cyan"></span> 蜻蜓 FM 的音乐 ID 需要使用 <code>| (管道符)</code> 组合，例如 <code>158696|5266259</code></li><li><span class="layui-badge-dot layui-bg-cyan"></span> 全民 K 歌的音乐名称请输入 <code>shareuid</code>，这是用户的 uid，搜索结果是该用户的所有公开作品</li><li><span class="layui-badge-dot layui-bg-cyan"></span> 全民 K 歌的音乐 ID 请输入 <code>shareid</code> 这是单曲分享 id，搜索结果是该单曲信息</li></ul><blockquote class="layui-elem-field layui-field-title"><p><span>网易：</span><u>http://music.163.com/#/song?id=<b><font color=#c7254e>25906124</font></b></u></p><p><span>ＱＱ：</span><u>http://y.qq.com/n/yqq/song/<b><font color=#c7254e>002B2EAA3brD5b</font></b>.html</u></p><p><span>酷狗：</span><u>http://www.kugou.com/song/#hash=<b><font color=#c7254e>08228af3cb404e8a4e7e9871bf543ff6</font></b></u></p><p><span>虾米：</span><u>http://www.xiami.com/song/<b><font color=#c7254e>2113248</font></b></u></p><p><span>百度：</span><u>http://music.baidu.com/song/<b><font color=#c7254e>266069</font></b></u></p></blockquote></div></div><div class=layui-colla-item><h2 class=layui-colla-title>音乐平台格式</h2><div class=layui-colla-content>例如网易云：25706282|netease<blockquote class="layui-elem-field layui-field-title"><p><span>网易：</span>netease</p><p><span>ＱＱ：</span>tencent</p><p><span>酷狗：</span>kugou</p><p><span>虾米：</span>xiami</p><p><span>百度：</span>baidu</p></blockquote></div></div><div class=layui-colla-item><h2 class=layui-colla-title>ID类型</h2><div class=layui-colla-content>playlist：代表歌单（部分歌单获取不到）</div></div></div>', 'text', '', '', '', '', 'tag-orange'),
('background_text', 'site', '自定义语音', '推荐多用英文逗号“,”分段，如：欢迎来到,授权中心！（这样说出来的语音就不会那么机械化）', 'text', '欢迎来到,授权中心！', '', '', '', NULL),
('background_text_per', 'site', '发音类型', '', 'radio', '0', '{"0":"小孩","1":"大叔","2":"萝莉","3":"御姐","4":"商务男","5":"商务女","6":"客服女"}', '', '', NULL),
('background_text_spd', 'site', '语速', '', 'radio', '0', '{"0":"快","1":"正常","2":"慢"}', '', '', NULL),
('captcha_open', 'site', '滑动验证码', '', 'bool', '1', '', '', '', NULL),
('captcha_id', 'site', '极验ID', '', 'string', '647f5ed2ed8acb4be36784e01556bb71', '', '', '', NULL),
('captcha_key', 'site', '极验KEY', '', 'string', 'b09a7aafbfd83f73b35a9b530d0337bf', '', '', '', NULL),
('maintain_switch', 'site', '维护状态', '后台不维护', 'bool', '0', '', 'required', '', NULL),
('notice_home', 'notice', '前台公告', '请填写前台公告', 'text', '', '', '', '', NULL),
('notice_user', 'notice', '用户后台公告', '请填写用户后台公告', 'text', '', '', '', '', NULL),
('reg_switch', 'function', '自助注册', '', 'bool', '0', '', 'required', '', NULL),
('replace_switch', 'function', '更换授权', '', 'bool', '0', '', 'required', '', NULL),
('cdkey_exchange_switch', 'function', '卡密兑换', '', 'bool', '0', '', '', '', NULL),
('auth_query_switch', 'function', '正版查询', '', 'bool', '0', '', 'required', '', NULL),
('pay_query_switch', 'function', '支付查询', '', 'bool', '0', '', '', '', NULL),
('user_query_switch', 'function', '代理查询', '', 'bool', '0', '', '', '', NULL),
('queue_query_switch', 'function', '队列查询', '', 'bool', '0', '', '', '', NULL),
('download', 'function', '下载方式', '', 'radio', '0', '{"0":"关闭","mail":"邮箱验证","qrcode":"QQ扫码","info":"信息验证"}', '', '', NULL),
('cdkey_head', 'function', '卡密前缀', '留空默认前缀为SF', 'string', 'SF', '', '', '', NULL),
('api_key', 'function', 'API密钥', '用于操作敏感API验证', 'string', 'sf-2129876388', '', '', '', NULL),
('create_cdkey_max_number', 'function', '卡密生成数量', '用户一次性最多生成卡密数量', 'string', '20', '', '', '', NULL),
('have_cdkey_max_number', 'function', '卡密拥有数量', '用户最多拥有未使用卡密数量', 'string', '999', '', '', '', NULL),
('login_switch', 'function', '登录方式', '', 'checkbox', '', '{"qq":"<img src=\'/Assets/img/qq.png\' width=\'15\'>","qrcode":"<img src=\'/Assets/img/pays.png\' width=\'15\'>"}', '', '', NULL),
('alipay_api', 'pay', '支付宝', '', 'radio', '0', '{"0":"关闭","1":"电脑+手机网站支付","2":"易支付免签约接口","3":"当面付扫码支付","5":"码支付免签约接口","7":"卡易信笔笔清接口"}', '', '', NULL),
('alipay_config', 'pay', '支付宝官方配置', '', 'array', '{"appid":"应用APPID","publickey":"支付宝公钥(RSA2)","privatekey":"应用私钥(RSA2)"}', '', '', '', NULL),
('alipay_epay_config', 'pay', '支付宝易支付配置', '', 'array', '{"url":"易支付接口网址","pid":"易支付商户ID","key":"易支付商户密钥"}', '', '', '', NULL),
('alipay_kyx_config', 'pay', '支付宝卡易信配置', '', 'array', '{"getway":"接口域名","pid":"商户号","key":"商户密钥"}', '', '', '', NULL),
('wxpay_api', 'pay', '微信', '', 'radio', '0', '{"0":"关闭","1":"官方扫码+公众号接口","2":"易支付免签约接口","3":"官方扫码+H5支付接口"}', '', '', NULL),
('wxpay_config', 'pay', '微信官方配置', '', 'array', '{"appid":"微信公众号APPID","mchid":"微信支付商户号","key":"微信支付商户密钥","appsecret":"微信公众号APPSECRET","domain":"微信支付指定域名"}', '', '', '', NULL),
('wxpay_epay_config', 'pay', '微信易支付配置', '', 'array', '{"url":"易支付接口网址","pid":"易支付商户ID","key":"易支付商户密钥"}', '', '', '', NULL),
('qqpay_api', 'pay', 'QQ', '', 'radio', '0', '{"0":"关闭","1":"QQ钱包官方支付接口","2":"易支付免签约接口"}', '', '', NULL),
('qqpay_config', 'pay', 'QQ官方配置', '', 'array', '{"mchid":"QQ钱包商户号","key":"QQ钱包API密钥"}', '', '', '', NULL),
('qqpay_epay_config', 'pay', 'QQ易支付配置', '', 'array', '{"url":"易支付接口网址","pid":"易支付商户ID","key":"易支付商户密钥"}', '', '', '', NULL),
('login_key', 'safe', '登录口令', '留空则进入后台不验证口令', 'string', '123456', '', '', '', NULL),
('user_login_message', 'message', '用户登录', '', 'config', '', 'notice', '', '', NULL),
('user_register_message', 'message', '用户注册', '', 'config', '', 'notice', '', '', NULL),
('change_binding_phone_message', 'message', '用户绑定手机', '', 'config', '', 'notice', '', '', NULL),
('change_binding_mail_message', 'message', '用户绑定邮箱', '', 'config', '', 'notice', '', '', NULL),
('download_mail_message', 'message', '源码下载', '', 'config', '', 'notice', '', '', NULL);

DROP TABLE IF EXISTS `SF_cdkey`;
CREATE TABLE IF NOT EXISTS `SF_cdkey` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cdkey` varchar(50) NOT NULL COMMENT '卡密',
  `cdkey_type` varchar(20) NOT NULL COMMENT '卡密类型',
  `use_content` varchar(255) DEFAULT NULL COMMENT '使用内容',
  `info` text COMMENT '卡密信息',
  `addtime` datetime NOT NULL COMMENT '添加时间',
  `usetime` datetime DEFAULT NULL COMMENT '使用时间',
  `appid` int(11) unsigned NOT NULL COMMENT '应用ID',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '卡密状态 0未使用，1已使用',
  `userid` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '所属用户ID',
  PRIMARY KEY (`id`),
  KEY (`cdkey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `SF_auth_template`;
CREATE TABLE `SF_auth_template`(
  `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL COMMENT '模板名称',
  `check_type` varchar(150) NOT NULL COMMENT '类型ID',
  `sort` int(11) DEFAULT NULL COMMENT '模板排序',
  `addtime` datetime NOT NULL COMMENT '添加时间',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '模板状态',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `SF_auth_template`(`id`, `check_type`, `name`, `sort`, `addtime`, `status`) VALUES
(1, 'domain', '授权域名模板', 1, NOW(), 1),
(2, 'qq',  '授权QQ模板', 1, NOW(), 1),
(3, 'machineCode', '授权机器码模板', 1, NOW(), 1);

DROP TABLE IF EXISTS `SF_auth_price`;
CREATE TABLE `SF_auth_price`(
  `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
  `tid` INT(11) unsigned NOT NULL COMMENT '模板ID',
  `name` varchar(150) NOT NULL COMMENT '价格名称',
  `sort` int(11) DEFAULT NULL COMMENT '价格排序',
  `day` int(11) unsigned NOT NULL COMMENT '授权天数',
  `permanent_switch` tinyint(1) NOT NULL DEFAULT 0 COMMENT '永久授权开关',
  `diy_switch` tinyint(1) NOT NULL DEFAULT 0 COMMENT '自定义时间开关',
  `money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '购买价格',
  `all_money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '泛域名购买价格',
  `addtime` datetime NOT NULL COMMENT '添加时间',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '价格状态',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `SF_auth_price`(`tid`, `name`, `sort`, `day`, `permanent_switch`, `diy_switch`, `money`, `all_money`, `addtime`, `status`) VALUES
(1,'自定义时间', 1, 1, 0, 1, 1, 1, NOW(), 1),
(1,'天卡', 2, 1, 0, 0, 1, 1, NOW(), 1),
(1, '周卡', 3, 7, 0, 0, 3, 3, NOW(), 1),
(1, '月卡', 4, 30, 0, 0, 6, 6, NOW(), 1),
(1, '年卡', 5, 1, 0, 0, 18, 18, NOW(), 1),
(1, '永久授权', 6, 0, 1, 0, 30, 30, NOW(), 1),
(2, '自定义时间', 1, 1, 0, 1, 1, 1, NOW(), 1),
(2, '天卡', 2, 1, 0, 0, 1, 1, NOW(), 1),
(2, '周卡', 3, 7, 0, 0, 3, 3, NOW(), 1),
(2, '月卡', 4, 30, 0, 0, 6, 6, NOW(), 1),
(2, '年卡', 5, 1, 0, 0, 18, 18, NOW(), 1),
(2, '永久授权', 6, 0, 1, 0, 30, 30, NOW(), 1),
(3, '自定义时间', 1, 1, 0, 1, 1, 1, NOW(), 1),
(3, '天卡', 2, 1, 0, 0, 1, 1, NOW(), 1),
(3, '周卡', 3, 7, 0, 0, 3, 3, NOW(), 1),
(3, '月卡', 4, 30, 0, 0, 6, 6, NOW(), 1),
(3, '年卡', 5, 1, 0, 0, 18, 18, NOW(), 1),
(3, '永久授权', 6, 0, 1, 0, 30, 30, NOW(), 1);

DROP TABLE IF EXISTS `SF_check_type`;
CREATE TABLE `SF_check_type`(
  `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL COMMENT '类型名称',
  `type` varchar(150) NOT NULL COMMENT '规则名称',
  `addtime` datetime NOT NULL COMMENT '添加时间',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类型状态',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `SF_power_template`;
CREATE TABLE `SF_power_template`(
  `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL COMMENT '模板名称',
  `sort` int(11) DEFAULT NULL COMMENT '模板排序',
  `addtime` datetime NOT NULL COMMENT '添加时间',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '模板状态',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `SF_power_template`(`id`, `name`, `sort`, `addtime`, `status`) VALUES
(1, '授权域名模板', 1, NOW(), 1),
(2, '授权QQ模板', 1, NOW(), 1);

DROP TABLE IF EXISTS `SF_power_price`;
CREATE TABLE `SF_power_price`(
   `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
   `name` varchar(150) NOT NULL COMMENT '权限名称',
   `tid` INT(11) unsigned NOT NULL COMMENT '模板ID',
   `addauth_power` tinyint(1) NOT NULL COMMENT '添加授权权限',
   `addpay_power` tinyint(1) NOT NULL COMMENT '添加认证易支付权限',
   `pirate_power` tinyint(1) NOT NULL COMMENT '查看盗版站点权限',
   `adduser_power` tinyint(1) NOT NULL COMMENT '添加下级权限',
   `parentid` INT(11) unsigned NOT NULL DEFAULT 0 COMMENT '上级权限ID',
   `addauth_discount` int(11) unsigned NOT NULL DEFAULT '0.00' COMMENT '购买授权折扣',
   `addpay_discount` int(11) unsigned NOT NULL DEFAULT '0.00' COMMENT '购买认证易支付折扣',
   `pirate_discount` int(11) unsigned NOT NULL DEFAULT '0.00' COMMENT '查看盗版站点折扣',
   `adduser_discount` int(11) unsigned NOT NULL DEFAULT '0.00' COMMENT '购买代理折扣',
   `introduce` text COMMENT '权限介绍',
   `money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '权限价格',
   `addtime` datetime NOT NULL COMMENT '添加时间',
   `default_power` tinyint(1) NOT NULL DEFAULT 0 COMMENT '默认权限',
   `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '权限状态',
   PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `SF_power_price`(`id`, `name`, `tid`, `addauth_power`, `addpay_power`, `pirate_power`, `adduser_power`, `parentid`, `addauth_discount`, `addpay_discount`, `pirate_discount`, `adduser_discount`, `introduce`, `money`, `addtime`, `default_power`, `status`) VALUES
(1, '普通用户', 1, 0, 0, 0, 0, 2, 100, 100, 100, 100, '注册默认权限', 0, NOW(), 1, 1),
(2, '青铜代理', 1, 1, 0, 0, 0, 3, 80, 100, 100, 100, '八折添加授权', 100, NOW(), 0, 1),
(3, '白银代理', 1, 1, 0, 0, 1, 4, 70, 100, 100, 80, '七折添加授权，八折添加下级（青铜代理）', 200, NOW(), 0, 1),
(4, '黄金代理', 1, 1, 1, 0, 1, 5, 60, 80, 100, 70, '六折添加授权，八折认证易支付，七折添加下级（白银代理商，青铜代理商）', 300, NOW(), 0, 1),
(5, '铂金代理', 1, 1, 1, 1, 1, 6, 50, 70, 80, 60, '五折添加授权，七折认证易支付，八折查看盗版站点，六折添加下级（黄金代理，白银代理，青铜代理）', 400, NOW(), 0, 1),
(6, '黑金代理', 1, 1, 1, 1, 1, 7, 40, 60, 70, 50, '四折添加授权，六折认证易支付，七折查看盗版站点，五折添加下级（白金代理，黄金代理，白银代理，青铜代理）', 500, NOW(), 0, 1),
(7, '水晶代理', 1, 1, 1, 1, 1, 8, 30, 50, 60, 40, '三折添加授权，五折认证易支付，六折查看盗版站点，四折添加下级（黑金代理，白金代理，黄金代理，白银代理，青铜代理）', 600, NOW(), 0, 1),
(8, '钻石代理', 1, 1, 1, 1, 1, 9, 20, 40, 50, 30, '二折无成本授权，四折认证易支付，五折查看盗版站点，三折添加下级（水晶代理，黑金代理，白金代理，黄金代理，白银代理，青铜代理）', 700, NOW(), 0, 1),
(9, '黑钻代理', 1, 1, 1, 1, 1, 10, 10, 30, 40, 20, '一折授权，三折认证易支付，四折查看盗版站点，二折添加下级（钻石代理，水晶代理，黑金代理，白金代理，黄金代理，白银代理，青铜代理）', 800, NOW(), 0, 1),
(10, '至臻代理', 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, '无成本授权，无成本认证易支付，无成本查看盗版站点，无成本添加下级代理（黑钻代理，钻石代理，水晶代理，黑金代理，白金代理，黄金代理，白银代理，青铜代理）', 1000, NOW(), 0, 1),
(11, '普通用户', 2, 0, 0, 0, 0, 12, 100, 100, 100, 100, '注册默认权限', 0, NOW(), 1, 1),
(12, '授权代理商', 2, 1, 0, 0, 0, 13, 80, 100, 100, 100, '低价授权', 100, NOW(), 0, 1),
(13, '超级代理商', 2, 1, 0, 0, 1, 14, 50, 100, 100, 50, '低价授权，低价添加下级代理（授权代理商）', 200, NOW(), 0, 1),
(14, '合作商', 2, 1, 0, 0, 1, 15, 0, 100, 100, 50, '无限授权，低价添加下级代理（超级代理商，授权代理商）', 400, NOW(), 0, 1),
(15, '副站长', 2, 1, 0, 0, 1, 0, 0, 100, 100, 0, '无限授权，无限添加下级代理（合作商，超级代理商，授权代理商）', 800, NOW(), 0, 1);

DROP TABLE IF EXISTS `SF_pay`;
CREATE TABLE `SF_pay` (
  `trade_no` varchar(64) NOT NULL,
  `api_trade_no` varchar(64) DEFAULT NULL,
  `buy_type` varchar(20) NULL,
  `type` varchar(20) NULL,
  `channel` varchar(10) NULL,
  `num` int(11) unsigned NOT NULL DEFAULT 1,
  `name` varchar(64) NULL,
  `money` varchar(32) NULL,
  `input` mediumtext NOT NULL,
  `addtime` datetime NULL,
  `endtime` datetime NULL,
  `ip` varchar(20) NULL,
  `userid` int(11) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`trade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `SF_order`;
CREATE TABLE `SF_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trade_no` varchar(255) DEFAULT NULL COMMENT '订单号',
  `api_trade_no` varchar(64) DEFAULT NULL COMMENT '对接订单号',
  `buy_type` varchar(20) NOT NULL COMMENT '订单类型',
  `type` varchar(20) NOT NULL COMMENT '支付方式',
  `channel` varchar(10) NULL COMMENT '支付接口',
  `name` varchar(255) DEFAULT NULL COMMENT '商品名称',
  `money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '订单金额',
  `input` mediumtext COMMENT '填写信息',
  `num` int(11) NOT NULL DEFAULT 0 COMMENT '购买数量',
  `ip` varchar(20) NULL COMMENT 'IP地址',
  `addtime` datetime DEFAULT NULL COMMENT '订单创建时间',
  `endtime` datetime DEFAULT NULL COMMENT '订单完成时间',
  `userid` int(11) NOT NULL DEFAULT 1 COMMENT '购买用户ID',
  `status` tinyint(2) NOT NULL DEFAULT 0 COMMENT '订单状态',
  `return` text COMMENT '订单返回信息',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `SF_admin`;
CREATE TABLE `SF_admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(150) NOT NULL,
  `password` varchar(150) NOT NULL,
  `qq` varchar(10) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(11) DEFAULT NULL COMMENT '手机号',
  `lasttime` datetime DEFAULT NULL,
  `ip` varchar(255) DEFAULT NULL,
  `citylist` varchar(255) DEFAULT NULL,
  `believe` text,
  `access_token` text,
  `status` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `SF_admin`(`username`, `password`, `qq`, `status`) VALUES
('admin', '123456', '2129876388', '1');

DROP TABLE IF EXISTS `SF_user`;
CREATE TABLE `SF_user` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(150) NOT NULL COMMENT '用户名',
  `password` varchar(150) NOT NULL COMMENT '密码',
  `qq` varchar(10) DEFAULT NULL COMMENT 'QQ',
  `email` varchar(255) NOT NULL COMMENT '邮箱',
  `phone` varchar(11) NOT NULL COMMENT '手机号',
  `balance` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '余额',
  `integral` int(11) NOT NULL DEFAULT 0 COMMENT '积分',
  `lasttime` datetime DEFAULT NULL COMMENT '最后一次登录时间',
  `ip` varchar(255) DEFAULT NULL COMMENT '用户IP',
  `believe` text COMMENT '信任设备',
  `power` int(11) unsigned NOT NULL DEFAULT '1' COMMENT '用户权限等级',
  `addtime` datetime DEFAULT NULL COMMENT '添加时间',
  `api_token` varchar(255) DEFAULT NULL COMMENT 'API TOKEN',
  `api_ip` text COMMENT '对接API白名单',
  `access_token` text COMMENT 'QQ快捷登录TOKEN',
  `config` text COMMENT '更多配置',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '用户状态',
  `userid` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '上级UID',
  `appid` int(11) unsigned NOT NULL COMMENT '所属应用ID',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `SF_menu`;
CREATE TABLE `SF_menu` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT '菜单名称',
  `url` varchar(255) NOT NULL COMMENT '菜单链接',
  `icon` varchar(255) NOT NULL COMMENT '菜单图标',
  `parentid` INT(11) unsigned NOT NULL DEFAULT 0 COMMENT '上级菜单ID',
  `addtime` datetime NOT NULL COMMENT '添加时间',
  `power` tinyint(1) NOT NULL DEFAULT 1 COMMENT '查看权限',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '菜单状态',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `SF_menu`(`id`,`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`) VALUES
(1, '后台首页', 'Index/main', 'layui-icon-home', 0, NOW(), 0, 1),
(2, '安全中心', 'Safe/index', 'layui-icon-auz', 1, NOW(), 0, 1),
(3, '订单列表', 'Order/list', 'layui-icon-list', 0, NOW(), 0, 1),
(4, '卡密管理', '#', 'layui-icon-cols', 0, NOW(), 0, 1),
(5, '卡密兑换', 'Cdkey/exchange', '', 4, NOW(), 2, 1),
(6, '卡密列表', 'Cdkey/list', '', 4, NOW(), 0, 1),
(7, '模板管理', '#', 'layui-icon-template', 0, NOW(), 1, 1),
(8, '价格管理', '#', '', 7, NOW(), 1, 1),
(9, '价格模板', 'AuthTemplate/list', '', 8, NOW(), 1, 1),
(10, '价格列表', 'AuthPrice/list', '', 8, NOW(), 1, 1),
(11, '权限管理', '#', '', 7, NOW(), 1, 1),
(12, '权限模板', 'PowerTemplate/list', '', 11, NOW(), 1, 1),
(13, '权限列表', 'PowerPrice/list', '', 11, NOW(), 1, 1),
(14, '应用管理', '#', 'layui-icon-app', 0, NOW(), 1, 1),
(15, '应用列表', 'App/list', '', 14, NOW(), 1, 1),
(16, '版本列表', 'Version/list', '', 14, NOW(), 1, 1),
(17, '模式列表', 'CheckType/list', '', 14, NOW(), 1, 1),
(18, '我的授权', '#', 'layui-icon-face-smile-b', 0, NOW(), 2, 1),
(19, '授权列表', 'MyList/auth', '', 18, NOW(), 2, 1),
(20, '认证列表', 'MyList/payment', '', 18, NOW(), 2, 1),
(21, '授权管理', '#', 'layui-icon-auz', 0, NOW(), 0, 1),
(22, '授权列表', 'Auth/list', '', 21, NOW(), 0, 1),
(23, '认证列表', 'Payment/list', '', 21, NOW(), 0, 1),
(24, '用户管理', '#', 'layui-icon-user', 0, NOW(), 0, 1),
(25, '用户列表', 'User/list', '', 24, NOW(), 0, 1),
(26, '盗版管理', 'Pirate/list', 'layui-icon-website', 0, NOW(), 0, 1),
(27, '系统设置', '#', 'layui-icon-set', 0, NOW(), 1, 1),
(28, '系统配置', 'Set/index', '', 27, NOW(), 1, 1),
(29, '模板配置', 'Set/template', '', 27, NOW(), 1, 1),
(30, '软件更新', 'Set/update', '', 27, NOW(), 1, 1),
(31, '插件管理', 'Addon/list', 'layui-icon-component', 0, NOW(), 1, 1),
(32, '系统日志', 'Log/list', 'layui-icon-log', 0, NOW(), 0, 1);

DROP TABLE IF EXISTS `SF_log`;
CREATE TABLE `SF_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '唯一性标识',
  `action_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '行为ID',
  `is_admin` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '是否后台操作：1是 2否',
  `username` varchar(60) CHARACTER SET utf8mb4 NOT NULL COMMENT '操作人用户名',
  `method` varchar(20) CHARACTER SET utf8mb4 NOT NULL COMMENT '请求类型',
  `result` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '请求结果：1成功 0失败',
  `module` varchar(30) NOT NULL COMMENT '模型',
  `action` varchar(255) NOT NULL COMMENT '操作方法',
  `url` text CHARACTER SET utf8mb4 COMMENT '操作页面',
  `param` text CHARACTER SET utf8mb4 NOT NULL COMMENT '请求参数(JSON格式)',
  `title` varchar(100) NOT NULL COMMENT '日志标题',
  `content` varchar(1000) NOT NULL DEFAULT '' COMMENT '内容',
  `ip` varchar(18) CHARACTER SET utf8mb4 NOT NULL COMMENT 'IP地址',
  `user_agent` varchar(360) CHARACTER SET utf8mb4 NOT NULL COMMENT 'User-Agent',
  `create_user` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '添加人',
  `create_time` datetime NOT NULL COMMENT '添加时间',
  `mark` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '有效标识：1正常 0删除',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8 COMMENT='系统行为日志表';

DROP TABLE IF EXISTS `SF_auth`;
CREATE TABLE `SF_auth` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `qq` varchar(20) NOT NULL COMMENT '授权者QQ',
  `auth_info` varchar(255) NOT NULL COMMENT '授权信息',
  `ip`  text COMMENT '授权IP',
  `authcode` varchar(100) NOT NULL COMMENT '授权码',
  `sign` varchar(20) NOT NULL COMMENT '特征码',
  `replace_number` int(11) NOT NULL DEFAULT 0 COMMENT '更换授权次数',
  `checktime` datetime DEFAULT NULL COMMENT '最后检测时间',
  `addtime` datetime NOT NULL COMMENT '授权添加时间',
  `endtime` datetime NOT NULL COMMENT '授权到期时间',
  `permanent_switch` tinyint(1) NOT NULL DEFAULT 0 COMMENT '永久授权开关',
  `beta` tinyint(1) NOT NULL DEFAULT 0 COMMENT '内测版资格',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '授权状态',
  `bindingid` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '绑定UID',
  `userid` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '上级UID',
  `appid` int(11) unsigned NOT NULL COMMENT '所属应用ID',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `SF_payment`;
CREATE TABLE `SF_payment` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `qq` varchar(20) NOT NULL COMMENT '持有者QQ',
  `url` varchar(255) NOT NULL COMMENT '易支付网址',
  `checktime` datetime DEFAULT NULL COMMENT '最后检测时间',
  `replace_number` int(11) NOT NULL DEFAULT 0 COMMENT '更换授权次数',
  `addtime` datetime NOT NULL COMMENT '授权添加时间',
  `endtime` datetime NOT NULL COMMENT '授权到期时间',
  `permanent_switch` tinyint(1) NOT NULL DEFAULT 0 COMMENT '永久授权开关',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '授权状态',
  `bindingid` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '绑定用户ID',
  `userid` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '上级UID',
  `appid` int(11) unsigned NOT NULL COMMENT '所属应用ID',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `SF_black`;
CREATE TABLE `SF_black` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `qq` varchar(20) NOT NULL COMMENT '联系QQ',
  `auth_info` varchar(255) NOT NULL COMMENT '黑名单信息',
  `ip`  text COMMENT '黑名单IP',
  `addtime` datetime NOT NULL COMMENT '授权添加时间',
  `level` tinyint(1) NOT NULL DEFAULT 0 COMMENT '黑名单等级 0为普通 1为中等 2为严重',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '黑名单状态',
  `userid` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '上级UID',
  `appid` int(11) unsigned NOT NULL COMMENT '所属应用ID',
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `SF_version`;
CREATE TABLE `SF_version` (
`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
`edition` varchar(255) NOT NULL COMMENT '版本',
`version` int(11) unsigned NOT NULL COMMENT '版本号',
`update_log` text COMMENT '更新内容',
`download_catalogue` varchar(255) NOT NULL COMMENT '下载目录',
`number` int(11) unsigned DEFAULT 0 COMMENT '下载次数',
`addtime` datetime NOT NULL COMMENT '授权添加时间',
`status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '版本状态',
`beta` tinyint(1) NOT NULL DEFAULT 0 COMMENT '内测版',
`type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0为安装包 1为更新包',
`appid` int(11) unsigned NOT NULL COMMENT '所属应用ID',
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `SF_pirate`;
CREATE TABLE `SF_pirate` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pirate_info` varchar(255) NOT NULL COMMENT '盗版内容',
  `param` text CHARACTER SET utf8mb4 COMMENT '请求参数(JSON格式)',
  `ip` varchar(18) CHARACTER SET utf8mb4 NOT NULL COMMENT 'IP地址',
  `addtime` datetime NOT NULL COMMENT '添加时间',
  `appid` int(11) unsigned NOT NULL COMMENT '所属应用ID',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `SF_check_type`;
CREATE TABLE `SF_check_type` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT '规则名称',
  `type` varchar(255) NOT NULL COMMENT '规则键值',
  `addtime` datetime NOT NULL COMMENT '添加时间',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '规则状态',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `SF_app`;
CREATE TABLE `SF_app` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL COMMENT '应用名称',
  `check_type` varchar(150) NOT NULL DEFAULT 'domain' COMMENT '判断授权内容规则',
  `introduce` text COMMENT '应用介绍',
  `logo` varchar(100) NOT NULL DEFAULT '/Assets/img/logo.png' COMMENT '应用LOGO',
  `authcode_file` varchar(150) NOT NULL COMMENT '授权码路径',
  `sql_file` varchar(150) NOT NULL COMMENT 'SQL路径',
  `download_file` varchar(150) NOT NULL COMMENT '下载路径（安装和更新）',
  `hacker_file` varchar(150) NOT NULL COMMENT '后门路径',
  `hacker_key` varchar(150) NOT NULL COMMENT '后门密钥',
  `app_notice` text COMMENT '应用公告',
  `cdkey_notice` text COMMENT '卡密兑换公告',
  `pay_notice` text COMMENT '余额充值公告',
  `register_notice` text COMMENT '用户在线注册公告',
  `replace_notice` text COMMENT '更换授权公告',
  `pirate_msg` text COMMENT '盗版提示内容',
  `endtime_msg` text COMMENT '授权到期提示内容',
  `status_msg` text COMMENT '授权封禁提示内容',
  `authcode_msg` text COMMENT '授权码不正确提示内容',
  `ip_msg` text COMMENT 'IP不正确提示内容',
  `pay_switch` tinyint(1) NOT NULL DEFAULT 0 COMMENT '在线支付开关',
  `authcode_switch` tinyint(1) NOT NULL DEFAULT 0 COMMENT '检测授权码开关',
  `pirate_msg_switch` tinyint(1) NOT NULL DEFAULT 1 COMMENT '盗版提示开关',
  `update_switch` tinyint(1) NOT NULL DEFAULT 0 COMMENT '在线更新开关',
  `auth_query_switch` tinyint(1) NOT NULL DEFAULT 0 COMMENT '正版查询开关',
  `pay_query_switch` tinyint(1) NOT NULL DEFAULT 0 COMMENT '认证支付查询开关',
  `user_query_switch` tinyint(1) NOT NULL DEFAULT 0 COMMENT '用户权限查询开关',
  `register_switch` tinyint(1) NOT NULL DEFAULT 0 COMMENT '用户在线注册开关',
  `replace_switch` tinyint(1) NOT NULL DEFAULT 0 COMMENT '更换授权开关',
  `pirate_switch` tinyint(1) NOT NULL DEFAULT 0 COMMENT '盗版入库开关',
  `binding_auth_switch` tinyint(1) NOT NULL DEFAULT 0 COMMENT '绑定授权开关',
  `binding_payment_switch` tinyint(1) NOT NULL DEFAULT 0 COMMENT '绑定认证开关',
  `api_switch` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'API开关',
  `ip_switch` tinyint(1) NOT NULL DEFAULT 0 COMMENT '检测IP开关',
  `cdkey_switch` tinyint(1) NOT NULL DEFAULT 0 COMMENT '卡密兑换开关',
  `online_buy_switch` tinyint(1) NOT NULL DEFAULT 0 COMMENT '在线购买开关',
  `register_blacklist` text COMMENT '注册黑名单IP',
  `auth_template` int(11) unsigned NOT NULL COMMENT '授权价格模板',
  `payment_template` int(11) unsigned NOT NULL COMMENT '认证价格模板',
  `power_template` int(11) unsigned NOT NULL COMMENT '权限价格模板',
  `pirate_money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '查看盗版站点价格',
  `give_money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '用户添加下级后系统赠送余额',
  `replace_money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '更换授权价格',
  `free_replace_number` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '免费更换授权次数',
  `check_auth_method` tinyint(1) NOT NULL DEFAULT 0 COMMENT '检测授权方式 0=GET,1=POST',
  `public_key` text COMMENT '授权公钥',
  `private_key` text COMMENT '授权私钥',
  `api_key` text COMMENT '操作API密钥',
  `addtime` datetime NOT NULL COMMENT '添加时间',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '程序状态 0=停止运营,1=维护中,2=正常运营',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `SF_download`;
CREATE TABLE `SF_download` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NULL,
  `img` varchar(100) NULL,
  `down` varchar(100) NULL,
  `qq` varchar(10) NULL,
  `money` decimal(10,2) NOT NULL DEFAULT '0.00',
  `content` varchar(1000) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `SF_check_type`(`id`, `name`, `type`, `addtime`, `status`) VALUES
(1, '域名规则', 'domain', NOW(), 1),
(2, 'QQ规则', 'qq', NOW(), 1),
(3, '机器码规则', 'machineCode', NOW(), 1);