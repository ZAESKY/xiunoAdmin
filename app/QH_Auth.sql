DROP TABLE IF EXISTS `QH_config`;
CREATE TABLE `QH_config` (
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

INSERT INTO `QH_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`) VALUES
('title', 'site', '站点名称', '请填写站点名称', 'string', 'QH授权平台', '', 'required', '', NULL),
('keywords', 'site', '站点关键词', '请填写站点关键词', 'string', 'PHP网络验证系统,多应用授权系统,QH综合验证授权系统,QH授权系统,QH授权站,授权中心,授权系统,机器人授权,授权', '', '', '', NULL),
('description', 'site', '站点描述', '请填写站点描述', 'text', 'QH综合验证授权系统，专业帮助站长开发程序，帮您火速开发程序，我们提供最专业的售前指导，提供最优质的售后服务，给您一个放心的平台！', '', '', '', NULL),
('foot', 'site', '底部版权', '请填写底部版权', 'string', 'Copyright © QH-陌上花开', '', 'required', '', NULL),
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
('notice_home', 'notice', '前台公告', '请填写前台公告', 'ueditor', '', '', '', '', NULL),
('notice_user', 'notice', '用户后台公告', '请填写用户后台公告', 'ueditor', '', '', '', '', NULL),
('reg_switch', 'function', '自助注册', '', 'bool', '0', '', 'required', '', NULL),
('replace_switch', 'function', '更换授权', '', 'bool', '0', '', 'required', '', NULL),
('cdkey_exchange_switch', 'function', '卡密兑换', '', 'bool', '0', '', '', '', NULL),
('auth_query_switch', 'function', '正版查询', '', 'bool', '0', '', 'required', '', NULL),
('user_query_switch', 'function', '代理查询', '', 'bool', '0', '', '', '', NULL),
('queue_query_switch', 'function', '队列查询', '', 'bool', '0', '', '', '', NULL),
('download', 'function', '下载方式', '', 'radio', '0', '{"0":"关闭","mail":"邮箱验证","qrcode":"QQ扫码","info":"信息验证"}', '', '', NULL),
('cdkey_head', 'function', '卡密前缀', '留空默认前缀为QH', 'string', 'QH', '', '', '', NULL),
('api_key', 'function', 'API密钥', '用于操作敏感API验证', 'string', 'qh-2129876388', '', '', '', NULL),
('create_cdkey_max_number', 'function', '卡密生成数量', '用户一次性最多生成卡密数量', 'string', '20', '', '', '', NULL),
('have_cdkey_max_number', 'function', '卡密拥有数量', '用户最多拥有未使用卡密数量', 'string', '999', '', '', '', NULL),
('login_switch', 'function', '登录方式', '', 'checkbox', '', '{"qq":"<img src=\'/Assets/img/qq.png\' width=\'15\'> QQ Login","qrcode":"<img src=\'/Assets/img/pays.png\' width=\'15\'> QQ QR Login","wechat_mp":"<img src=\'/Assets/img/wechat.svg\' width=\'15\'> WeChat MP Login"}', '', '', NULL),
('wechat_mp_appid', 'function', 'WeChat MP AppID', 'WeChat Official Account AppID', 'string', '', '', '', '', NULL),
('wechat_mp_appsecret', 'function', 'WeChat MP AppSecret', 'WeChat Official Account AppSecret', 'string', '', '', '', '', NULL),
('wechat_mp_token', 'function', 'WeChat MP Token', 'WeChat Official Account server Token. Callback URL: http://your-domain/api.php/WechatMp/callback', 'string', '', '', '', '', NULL),
('feature_point_exchange_enabled', 'feature_access', '积分兑换', '关闭后用户端积分兑换、管理员端积分商品/兑换记录均不可访问', 'bool', '1', '', '', '', NULL),
('feature_user_plugin_enabled', 'feature_access', '用户插件中心', '关闭后用户端插件市场、发布插件、我的插件、我的购买等页面均不可访问', 'bool', '1', '', '', '', NULL),
('feature_admin_plugin_enabled', 'feature_access', '管理员插件管理', '关闭后管理员端插件列表、插件订单、插件评论等页面均不可访问', 'bool', '1', '', '', '', NULL),
('feature_withdraw_enabled', 'feature_access', '提现模块入口', '控制用户端提现入口和管理员端提现管理入口；关闭后两个入口均不可访问', 'bool', '1', '', '', '', NULL),
('plugin_reward_enabled', 'plugin_reward', '启用发布奖励', '用户原创插件首次审核通过时触发；关闭期间通过的插件以后也不会补发', 'bool', '1', '', '', '', NULL),
('plugin_reward_points', 'plugin_reward', '首次通过奖励积分', '填0表示不奖励积分；每个插件只奖励一次', 'number', '100', '', '', 'min="0" max="1000000" step="1"', NULL),
('plugin_reward_balance', 'plugin_reward', '首次通过奖励金额', '作为平台余额发放，不进入可提现余额；填0表示不奖励金额', 'number', '0.00', '', '', 'min="0" max="1000000" step="0.01"', NULL),
('plugin_reward_original_only', 'plugin_reward', '仅奖励原创插件', '开启后转载插件审核通过但不会获得奖励', 'bool', '1', '', '', '', NULL),
('plugin_reward_monthly_limit', 'plugin_reward', '用户每月奖励上限', '单个用户每月最多获得奖励的插件数量；0表示不限制', 'number', '3', '', '', 'min="0" max="1000" step="1"', NULL),
('plugin_reward_min_account_days', 'plugin_reward', '账号最低注册天数', '账号注册达到该天数后才可获得插件发布奖励；0表示不限制', 'number', '7', '', '', 'min="0" max="3650" step="1"', NULL),
('plugin_reward_duplicate_hash', 'plugin_reward', '拦截重复插件包', '相同插件包哈希全站只允许获得一次首次发布奖励', 'bool', '1', '', '', '', NULL),
('oss_enabled', 'storage', '启用 OSS 上传', '开启后插件包、应用安装包、版本更新包、程序补丁和站内上传图片统一保存到阿里云 OSS；关闭后保存到本地', 'bool', '0', '', '', '', NULL),
('oss_access_key_id', 'storage', 'OSS AccessKey ID', '阿里云 OSS AccessKey ID，仅后端读取，不会暴露到前端', 'string', '', '', '', '', NULL),
('oss_access_key_secret', 'storage', 'OSS AccessKey Secret', '阿里云 OSS AccessKey Secret，仅后端读取；留空则使用 .env 中的同名配置', 'string', '', '', '', 'type="password" autocomplete="new-password"', NULL),
('oss_bucket', 'storage', 'OSS Bucket', '阿里云 OSS Bucket 名称', 'string', '', '', '', '', NULL),
('oss_endpoint', 'storage', 'OSS Endpoint', '例如 oss-cn-hangzhou.aliyuncs.com，支持填写完整 https:// 地址', 'string', '', '', '', '', NULL),
('oss_public_base_url', 'storage', 'OSS 公开访问域名', '可填写 CDN/自定义域名，例如 https://cdn.example.com；留空则使用 Bucket Endpoint 拼接', 'string', '', '', '', '', NULL),
('oss_use_private_bucket', 'storage', 'OSS 私有 Bucket', '必须开启；文件回显和授权下载均由后端生成短时签名 URL，新对象强制使用 private ACL', 'bool', '1', '', '', '', NULL),
('sms_enabled', 'sms', '启用阿里云短信', '开启后可绑定已验证手机号，并按下方开关保护敏感操作', 'bool', '0', '', '', '', NULL),
('sms_access_key_id', 'sms', 'AccessKey ID', '建议使用仅授予短信发送权限的RAM子账号，不要使用主账号AccessKey', 'string', '', '', '', 'autocomplete="off"', NULL),
('sms_access_key_secret', 'sms', 'AccessKey Secret', '密钥加密保存且永不回显；留空保持原值，填写新值才会替换', 'string', '', '', '', 'autocomplete="new-password"', NULL),
('sms_sign_name', 'sms', '短信签名', '填写阿里云短信控制台审核通过的签名名称，不包含【】', 'string', '', '', '', 'maxlength="100"', NULL),
('sms_template_code', 'sms', '备用/自定义验证码模板 CODE', '某个业务模板留空时使用此 CODE；可填写数字赠送模板或 SMS_ 开头的自定义模板。全部业务模板已配置时可留空', 'string', '', '', '', 'maxlength="32"', NULL),
('sms_template_login_register', 'sms', '登录/注册模板 CODE', '阿里云号码认证赠送模板默认使用 100001；用于短信登录或注册', 'string', '100001', '', '', 'maxlength="32"', NULL),
('sms_template_phone_change', 'sms', '修改手机号模板 CODE', '阿里云号码认证赠送模板默认使用 100002；用于发起修改绑定手机号', 'string', '100002', '', '', 'maxlength="32"', NULL),
('sms_template_password_reset', 'sms', '重置密码模板 CODE', '阿里云号码认证赠送模板默认使用 100003；用于手机验证码找回密码', 'string', '100003', '', '', 'maxlength="32"', NULL),
('sms_template_phone_bind', 'sms', '绑定新手机号模板 CODE', '阿里云号码认证赠送模板默认使用 100004；用于首次绑定或验证新手机号', 'string', '100004', '', '', 'maxlength="32"', NULL),
('sms_template_phone_verify', 'sms', '验证已绑定手机号模板 CODE', '阿里云号码认证赠送模板默认使用 100005；用于提现、返利等敏感操作验证', 'string', '100005', '', '', 'maxlength="32"', NULL),
('sms_code_ttl', 'sms', '验证码有效期（秒）', '建议300秒，可设置120至600秒', 'number', '300', '', '', 'min="120" max="600" step="1"', NULL),
('sms_daily_limit', 'sms', '单手机号每日上限', '包括绑定和敏感操作验证码，建议不超过10条', 'number', '10', '', '', 'min="1" max="30" step="1"', NULL),
('sms_require_withdraw', 'sms', '提现需要短信验证', '申请提现时必须使用已绑定手机号接收一次性验证码', 'bool', '1', '', '', '', NULL),
('sms_require_rebate', 'sms', '生成返利码需要短信验证', '首次生成专属折扣码时必须验证已绑定手机号', 'bool', '1', '', '', '', NULL),
('sms_require_plugin_reward', 'sms', '插件奖励需要已验证手机号', '未绑定已验证手机号的账号可以发布插件，但不会获得首次发布奖励', 'bool', '1', '', '', '', NULL),
('password_recovery_channel', 'safe', '找回密码验证码', '选择用户找回密码时接收验证码的渠道；短信模式仅允许已验证手机号', 'radio', 'email', '{"email":"邮箱验证码","sms":"手机短信验证码"}', '', '', NULL),
('withdraw_enable', 'withdraw', '允许提交提现', '关闭后保留提现记录访问，但用户不能提交新的提现申请', 'bool', '1', '', '', '', NULL),
('withdraw_min_amount', 'withdraw', '最低提现金额', '用户单次提现最低金额', 'number', '10', '', '', '', NULL),
('withdraw_interval', 'withdraw', '提现间隔(小时)', '同一用户两次提现申请之间的最小间隔，0表示不限制', 'number', '24', '', '', '', NULL),
('rebate_hold_days', 'rebate', '返利冻结天数', '返利到期并通过复核后进入可提现收益余额，建议不少于7天', 'number', '7', '', '', '', NULL),
('rebate_pair_daily_count', 'rebate', '同一邀请关系每日上限', '同一付款账号与同一码主每天最多产生的返利笔数', 'number', '3', '', '', '', NULL),
('rebate_daily_limit', 'rebate', '码主每日返利上限', '单个码主每天可进入冻结期的返利金额上限（元）', 'number', '50', '', '', '', NULL),
('rebate_monthly_limit', 'rebate', '码主每月返利上限', '单个码主每月可进入冻结期的返利金额上限（元）', 'number', '500', '', '', '', NULL),
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
INSERT INTO `QH_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`) VALUES
('email_notification_enabled', 'email_notification', '业务邮件通知', '总开关关闭时仅保留站内消息，不发送审核、交易等业务邮件', 'bool', '0', '', '', '', NULL);

DROP TABLE IF EXISTS `QH_cdkey`;
CREATE TABLE IF NOT EXISTS `QH_cdkey` (
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

DROP TABLE IF EXISTS `QH_auth_template`;
CREATE TABLE `QH_auth_template`(
  `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL COMMENT '模板名称',
  `check_type` varchar(150) NOT NULL COMMENT '类型ID',
  `sort` int(11) DEFAULT NULL COMMENT '模板排序',
  `addtime` datetime NOT NULL COMMENT '添加时间',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '模板状态',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `QH_auth_template`(`id`, `check_type`, `name`, `sort`, `addtime`, `status`) VALUES
(1, 'domain', '授权域名模板', 1, NOW(), 1),
(2, 'qq',  '授权QQ模板', 1, NOW(), 1),
(3, 'machineCode', '授权机器码模板', 1, NOW(), 1);

DROP TABLE IF EXISTS `QH_auth_price`;
CREATE TABLE `QH_auth_price`(
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

INSERT INTO `QH_auth_price`(`tid`, `name`, `sort`, `day`, `permanent_switch`, `diy_switch`, `money`, `all_money`, `addtime`, `status`) VALUES
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

DROP TABLE IF EXISTS `QH_check_type`;
CREATE TABLE `QH_check_type`(
  `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL COMMENT '类型名称',
  `type` varchar(150) NOT NULL COMMENT '规则名称',
  `addtime` datetime NOT NULL COMMENT '添加时间',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类型状态',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `QH_power_template`;
CREATE TABLE `QH_power_template`(
  `id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL COMMENT '模板名称',
  `sort` int(11) DEFAULT NULL COMMENT '模板排序',
  `addtime` datetime NOT NULL COMMENT '添加时间',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '模板状态',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `QH_power_template`(`id`, `name`, `sort`, `addtime`, `status`) VALUES
(1, '授权域名模板', 1, NOW(), 1),
(2, '授权QQ模板', 1, NOW(), 1);

DROP TABLE IF EXISTS `QH_power_price`;
CREATE TABLE `QH_power_price`(
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
   `rebate_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '启用返利 0=否 1=是',
   `rebate_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '返利比例(%)',
   `discount_code_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '启用折扣码功能 0=否 1=是',
   PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `QH_power_price`(`id`, `name`, `tid`, `addauth_power`, `addpay_power`, `pirate_power`, `adduser_power`, `parentid`, `addauth_discount`, `addpay_discount`, `pirate_discount`, `adduser_discount`, `introduce`, `money`, `addtime`, `default_power`, `status`) VALUES
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

DROP TABLE IF EXISTS `QH_pay`;
CREATE TABLE `QH_pay` (
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
  `discount_code` varchar(32) DEFAULT NULL COMMENT '使用的折扣码',
  PRIMARY KEY (`trade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `QH_order`;
CREATE TABLE `QH_order` (
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
  `discount_code` varchar(32) DEFAULT NULL COMMENT '使用的折扣码',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `QH_admin`;
CREATE TABLE `QH_admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(150) NOT NULL,
  `password` varchar(150) NOT NULL,
  `qq` varchar(20) DEFAULT NULL,
  `wechat_openid` varchar(64) NOT NULL DEFAULT '' COMMENT '微信公众号openid',
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(11) DEFAULT NULL COMMENT '手机号',
  `lasttime` datetime DEFAULT NULL,
  `ip` varchar(255) DEFAULT NULL,
  `citylist` varchar(255) DEFAULT NULL,
  `believe` text,
  `access_token` varchar(128) DEFAULT NULL,
  `status` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_qq` (`qq`),
  UNIQUE KEY `uk_admin_access_token` (`access_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `QH_admin`(`username`, `password`, `qq`, `status`) VALUES
('admin', '123456', '2129876388', '1');

DROP TABLE IF EXISTS `QH_user`;
CREATE TABLE `QH_user` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(150) NOT NULL COMMENT '用户名',
  `password` varchar(150) NOT NULL COMMENT '密码',
  `qq` varchar(20) DEFAULT NULL COMMENT 'QQ',
  `wechat_openid` varchar(64) NOT NULL DEFAULT '' COMMENT '微信公众号openid',
  `email` varchar(255) NOT NULL COMMENT '邮箱',
  `phone` varchar(11) NOT NULL COMMENT '手机号',
  `phone_verified_at` datetime DEFAULT NULL COMMENT '手机号通过短信验证的时间',
  `phone_verified_source` varchar(20) NOT NULL DEFAULT '' COMMENT '手机号验证来源',
  `balance` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '余额',
  `withdrawable_balance` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '可提现收益余额，为总余额的子集',
  `integral` int(11) NOT NULL DEFAULT 0 COMMENT '积分',
  `lasttime` datetime DEFAULT NULL COMMENT '最后一次登录时间',
  `ip` varchar(255) DEFAULT NULL COMMENT '用户IP',
  `believe` text COMMENT '信任设备',
  `power` int(11) unsigned NOT NULL DEFAULT '1' COMMENT '用户权限等级',
  `addtime` datetime DEFAULT NULL COMMENT '添加时间',
  `created_at` datetime DEFAULT NULL COMMENT '注册时间',
  `api_token` varchar(255) DEFAULT NULL COMMENT 'API TOKEN',
  `api_ip` text COMMENT '对接API白名单',
  `access_token` text COMMENT 'QQ快捷登录TOKEN',
  `config` text COMMENT '更多配置',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '用户状态',
  `userid` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '上级UID',
  `appid` int(11) unsigned NOT NULL COMMENT '所属应用ID',
  `is_developer` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否为开发者',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_username` (`username`),
  UNIQUE KEY `uk_user_qq` (`qq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TRIGGER `QH_user_withdrawable_before_insert`
BEFORE INSERT ON `QH_user`
FOR EACH ROW SET NEW.`withdrawable_balance` = LEAST(GREATEST(NEW.`withdrawable_balance`, 0.00), GREATEST(NEW.`balance`, 0.00));

CREATE TRIGGER `QH_user_withdrawable_before_update`
BEFORE UPDATE ON `QH_user`
FOR EACH ROW SET NEW.`withdrawable_balance` = LEAST(GREATEST(NEW.`withdrawable_balance`, 0.00), GREATEST(NEW.`balance`, 0.00));

DROP TABLE IF EXISTS `QH_social_identity`;
CREATE TABLE `QH_social_identity` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(32) NOT NULL COMMENT 'qq/wechat/etc',
  `provider_appid` varchar(64) NOT NULL COMMENT '第三方平台应用ID',
  `provider_uid` varchar(128) NOT NULL COMMENT '第三方稳定用户标识，如QQ OpenID',
  `unionid` varchar(128) NOT NULL DEFAULT '' COMMENT '跨应用标识（平台支持时）',
  `nickname` varchar(255) NOT NULL DEFAULT '',
  `avatar` varchar(500) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_provider_subject` (`provider`,`provider_appid`,`provider_uid`),
  KEY `idx_unionid` (`provider`,`unionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='第三方登录身份';

DROP TABLE IF EXISTS `QH_user_social_identity`;
CREATE TABLE `QH_user_social_identity` (
  `identity_id` int(11) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `app_id` int(11) unsigned NOT NULL COMMENT '关联账号所属应用ID',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`identity_id`,`user_id`),
  UNIQUE KEY `uk_social_identity_app` (`identity_id`,`app_id`),
  UNIQUE KEY `uk_social_user_once` (`user_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_social_app` (`app_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户与第三方身份关联';

DROP TABLE IF EXISTS `QH_user_phone_identity`;
CREATE TABLE `QH_user_phone_identity` (
  `user_id` int(11) unsigned NOT NULL,
  `phone_hash` char(64) NOT NULL COMMENT '带服务端pepper的手机号HMAC',
  `phone_last4` char(4) NOT NULL DEFAULT '',
  `verified_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uk_phone_hash` (`phone_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='已验证手机号唯一身份';

DROP TABLE IF EXISTS `QH_sms_audit`;
CREATE TABLE `QH_sms_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL DEFAULT 0,
  `scene` varchar(32) NOT NULL DEFAULT '',
  `phone_hash` char(64) NOT NULL DEFAULT '',
  `phone_masked` varchar(20) NOT NULL DEFAULT '',
  `status` varchar(20) NOT NULL DEFAULT '',
  `provider_request_id` varchar(128) NOT NULL DEFAULT '',
  `error_code` varchar(64) NOT NULL DEFAULT '',
  `template_code` varchar(32) NOT NULL DEFAULT '' COMMENT '本次发送实际使用的模板CODE',
  `ip_hash` char(64) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_scene_time` (`user_id`,`scene`,`created_at`),
  KEY `idx_phone_time` (`phone_hash`,`created_at`),
  KEY `idx_status_time` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='短信发送与验证审计（不保存验证码）';

DROP TABLE IF EXISTS `QH_qq_identity_claim`;
CREATE TABLE `QH_qq_identity_claim` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `identity_id` int(11) unsigned NOT NULL COMMENT 'QH_social_identity.id',
  `user_id` int(11) unsigned NOT NULL COMMENT 'QH_user.id',
  `legacy_qq` varchar(20) NOT NULL COMMENT '旧扫码一次性验证的数字QQ',
  `proof_method` varchar(32) NOT NULL DEFAULT 'legacy_qr',
  `verified_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `status` tinyint(1) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_qq_claim_identity` (`identity_id`),
  UNIQUE KEY `uk_qq_claim_user` (`user_id`),
  UNIQUE KEY `uk_qq_claim_number` (`legacy_qq`),
  KEY `idx_qq_claim_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='正规QQ身份与已验证历史QQ关系';

DROP TABLE IF EXISTS `QH_menu`;
CREATE TABLE `QH_menu` (
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

INSERT INTO `QH_menu`(`id`,`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`) VALUES
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
(18, '我的授权', 'MyList/auth', 'layui-icon-face-smile-b', 0, NOW(), 2, 0),
(21, '授权管理', 'Auth/list', 'layui-icon-auz', 0, NOW(), 0, 1),
(24, '用户管理', 'User/list', 'layui-icon-user', 0, NOW(), 0, 1),
(26, '盗版管理', 'Pirate/list', 'layui-icon-website', 0, NOW(), 1, 1),
(27, '系统设置', '#', 'layui-icon-set', 0, NOW(), 1, 1),
(28, '系统配置', 'Set/index', '', 27, NOW(), 1, 1),
(29, '软件更新', 'Set/update', '', 27, NOW(), 1, 1),
(33, '邮件消息通知', 'Set/emailNotification', '', 27, NOW(), 1, 1),
(31, '插件管理', 'Addon/list', 'layui-icon-component', 0, NOW(), 1, 1),
(32, '系统日志', 'Log/list', 'layui-icon-log', 0, NOW(), 1, 1);

DROP TABLE IF EXISTS `QH_log`;
CREATE TABLE `QH_log` (
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

DROP TABLE IF EXISTS `QH_auth`;
CREATE TABLE `QH_auth` (
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

DROP TABLE IF EXISTS `QH_payment`;
CREATE TABLE `QH_payment` (
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

DROP TABLE IF EXISTS `QH_black`;
CREATE TABLE `QH_black` (
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

DROP TABLE IF EXISTS `QH_version`;
CREATE TABLE `QH_version` (
`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
`edition` varchar(255) NOT NULL COMMENT '版本',
`version` int(11) unsigned NOT NULL COMMENT '版本号',
`update_log` text COMMENT '更新内容',
`download_catalogue` varchar(255) NOT NULL COMMENT '下载目录',
`storage_driver` varchar(20) NOT NULL DEFAULT 'local' COMMENT '版本包存储驱动 local/oss',
`package_object_key` varchar(500) NOT NULL DEFAULT '' COMMENT '版本包 OSS 对象 Key',
`package_sha256` char(64) NOT NULL DEFAULT '' COMMENT '版本包 SHA-256',
`package_size` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '版本包字节数',
`number` int(11) unsigned DEFAULT 0 COMMENT '下载次数',
`addtime` datetime NOT NULL COMMENT '授权添加时间',
`status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '版本状态',
`beta` tinyint(1) NOT NULL DEFAULT 0 COMMENT '内测版',
`type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '保留字段，发布流程固定为完整包',
`appid` int(11) unsigned NOT NULL COMMENT '所属应用ID',
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `QH_pirate`;
CREATE TABLE `QH_pirate` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pirate_info` varchar(255) NOT NULL COMMENT '盗版内容',
  `param` text CHARACTER SET utf8mb4 COMMENT '请求参数(JSON格式)',
  `ip` varchar(18) CHARACTER SET utf8mb4 NOT NULL COMMENT 'IP地址',
  `addtime` datetime NOT NULL COMMENT '添加时间',
  `appid` int(11) unsigned NOT NULL COMMENT '所属应用ID',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `QH_check_type`;
CREATE TABLE `QH_check_type` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT '规则名称',
  `type` varchar(255) NOT NULL COMMENT '规则键值',
  `addtime` datetime NOT NULL COMMENT '添加时间',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '规则状态',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `QH_app`;
CREATE TABLE `QH_app` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL COMMENT '应用名称',
  `product_id` varchar(64) DEFAULT NULL COMMENT '对外稳定产品标识',
  `package_profile` varchar(32) NOT NULL DEFAULT 'generic' COMMENT '发布包校验规则 generic/xiuno_theme',
  `check_type` varchar(150) NOT NULL DEFAULT 'domain' COMMENT '判断授权内容规则',
  `introduce` text COMMENT '应用介绍',
  `logo` varchar(500) NOT NULL DEFAULT '/Assets/img/logo.png' COMMENT '应用LOGO或私有OSS媒体网关URL',
  `authcode_file` varchar(150) NOT NULL COMMENT '授权码路径',
  `sql_file` varchar(150) NOT NULL COMMENT 'SQL路径',
  `download_file` varchar(150) NOT NULL COMMENT '下载路径（安装和更新）',
  `installer_storage_driver` varchar(20) NOT NULL DEFAULT 'local' COMMENT '安装包存储驱动 local/oss',
  `installer_object_key` varchar(500) NOT NULL DEFAULT '' COMMENT '安装包 OSS 对象 Key',
  `installer_file_name` varchar(255) NOT NULL DEFAULT '' COMMENT '公开引导安装包原始文件名',
  `installer_sha256` char(64) NOT NULL DEFAULT '' COMMENT '公开引导安装包 SHA-256',
  `installer_size` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '公开引导安装包字节数',
  `installer_uploaded_at` datetime DEFAULT NULL COMMENT '公开引导安装包上传时间',
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_app_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `QH_download`;
CREATE TABLE `QH_download` (
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

INSERT INTO `QH_check_type`(`id`, `name`, `type`, `addtime`, `status`) VALUES
(1, '域名规则', 'domain', NOW(), 1),
(2, 'QQ规则', 'qq', NOW(), 1),
(3, '机器码规则', 'machineCode', NOW(), 1);

-- ==================== 功能反馈 / feedback ====================
DROP TABLE IF EXISTS `QH_feedback`;
CREATE TABLE `QH_feedback` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `title` varchar(255) NOT NULL COMMENT '反馈标题',
  `content` text NOT NULL COMMENT '反馈内容',
  `type` varchar(20) NOT NULL DEFAULT 'other' COMMENT '反馈类型：bug/feature/other',
  `reply` text COMMENT '历史回复字段，新增回复请写入QH_feedback_reply',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0待处理 1已受理 2已驳回 3已解决',
  `created_at` datetime NOT NULL COMMENT '提交时间',
  `updated_at` datetime DEFAULT NULL COMMENT '处理时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_status` (`user_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `QH_feedback_reply`;
CREATE TABLE `QH_feedback_reply` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `feedback_id` int(11) unsigned NOT NULL COMMENT '反馈ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '回复用户ID，0=管理员',
  `is_admin` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否管理员回复',
  `author` varchar(150) NOT NULL DEFAULT '' COMMENT '回复人名称',
  `content` text NOT NULL COMMENT '回复内容',
  `created_at` datetime NOT NULL COMMENT '回复时间',
  PRIMARY KEY (`id`),
  KEY `idx_feedback_id` (`feedback_id`),
  KEY `idx_feedback_admin` (`feedback_id`, `is_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `QH_notification`;
CREATE TABLE `QH_notification` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '接收用户ID，0=管理员',
  `title` varchar(255) NOT NULL COMMENT '通知标题',
  `content` text COMMENT '通知内容',
  `type` varchar(50) DEFAULT 'feedback' COMMENT '通知类型',
  `link` varchar(255) DEFAULT '' COMMENT '跳转链接',
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0未读 1已读',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `QH_notification_email_preference`;
CREATE TABLE `QH_notification_email_preference` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `owner_type` varchar(16) NOT NULL COMMENT 'user/admin',
  `owner_id` int(11) unsigned NOT NULL,
  `event_code` varchar(64) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_owner_event` (`owner_type`,`owner_id`,`event_code`),
  KEY `idx_event_enabled` (`event_code`,`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邮件通知接收偏好';

DROP TABLE IF EXISTS `QH_notification_email_template`;
CREATE TABLE `QH_notification_email_template` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `event_code` varchar(64) NOT NULL,
  `audience` varchar(16) NOT NULL COMMENT 'user/admin',
  `subject` varchar(255) NOT NULL,
  `html_body` mediumtext NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` int(11) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_event_code` (`event_code`),
  KEY `idx_audience_enabled` (`audience`,`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='业务邮件HTML模板覆盖';

DROP TABLE IF EXISTS `QH_notification_email_log`;
CREATE TABLE `QH_notification_email_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_code` varchar(64) NOT NULL,
  `recipient_type` varchar(16) NOT NULL,
  `recipient_id` int(11) unsigned NOT NULL DEFAULT 0,
  `email_hash` char(64) NOT NULL,
  `email_masked` varchar(255) NOT NULL DEFAULT '',
  `status` varchar(16) NOT NULL,
  `error_code` varchar(64) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_event_time` (`event_code`,`created_at`),
  KEY `idx_recipient_time` (`recipient_type`,`recipient_id`,`created_at`),
  KEY `idx_status_time` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='业务邮件发送审计（不保存正文和完整邮箱）';

DROP TABLE IF EXISTS `QH_point_log`;
CREATE TABLE `QH_point_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `type` varchar(30) NOT NULL COMMENT 'consume/recharge/exchange/refund',
  `amount` int(11) NOT NULL DEFAULT 0 COMMENT '积分变化，正数增加，负数扣除',
  `integral_after` int(11) NOT NULL DEFAULT 0 COMMENT '变动后积分',
  `description` varchar(255) DEFAULT NULL COMMENT '描述',
  `source_type` varchar(50) DEFAULT '' COMMENT '来源类型',
  `source_no` varchar(64) DEFAULT '' COMMENT '来源编号',
  `related_id` int(11) unsigned DEFAULT NULL COMMENT '关联ID',
  `status` varchar(20) NOT NULL DEFAULT 'valid' COMMENT 'valid/invalid',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_source` (`source_type`,`source_no`),
  KEY `idx_user_time` (`user_id`,`created_at`),
  KEY `idx_related` (`related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `QH_wechat_mp_login`;
CREATE TABLE `QH_wechat_mp_login` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(64) NOT NULL COMMENT '本地登录token',
  `scene` varchar(80) NOT NULL DEFAULT '' COMMENT '微信二维码场景值',
  `type` varchar(20) NOT NULL DEFAULT '' COMMENT 'userLogin/adminLogin/bindUser/bindAdmin',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/scanned/confirmed',
  `openid` varchar(64) NOT NULL DEFAULT '' COMMENT '微信openid',
  `user_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '绑定用户ID',
  `admin_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '绑定管理员ID',
  `expires_at` datetime NOT NULL COMMENT '过期时间',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token`),
  KEY `idx_scene` (`scene`),
  KEY `idx_status_expire` (`status`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信公众号扫码登录状态表';

DROP TABLE IF EXISTS `QH_point_product`;
CREATE TABLE `QH_point_product` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL COMMENT '商品名称',
  `image` varchar(255) DEFAULT '' COMMENT '商品图片',
  `description` text COMMENT '商品描述',
  `type` varchar(30) NOT NULL DEFAULT 'virtual_goods' COMMENT 'auth_code/virtual_goods',
  `stock` int(11) NOT NULL DEFAULT 0 COMMENT '库存',
  `required_points` int(11) NOT NULL DEFAULT 0 COMMENT '兑换所需积分',
  `exchange_limit` int(11) NOT NULL DEFAULT 0 COMMENT '每个用户兑换上限，0不限制',
  `reward_info` text COMMENT '虚拟商品奖品信息',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1上架 0下架',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `QH_point_exchange_record`;
CREATE TABLE `QH_point_exchange_record` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `product_id` int(11) unsigned NOT NULL COMMENT '商品ID',
  `product_name` varchar(150) NOT NULL COMMENT '商品名称快照',
  `cost_points` int(11) NOT NULL DEFAULT 0 COMMENT '消耗积分',
  `reward_info` text COMMENT '奖品信息',
  `status` varchar(20) NOT NULL DEFAULT 'success' COMMENT 'success/canceled',
  `created_at` datetime NOT NULL COMMENT '兑换时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_time` (`user_id`,`created_at`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `QH_point_product_reward`;
CREATE TABLE `QH_point_product_reward` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) unsigned NOT NULL COMMENT '商品ID',
  `reward_content` text NOT NULL COMMENT '奖品内容',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/issued',
  `user_id` int(11) unsigned DEFAULT NULL COMMENT '领取用户ID',
  `record_id` int(11) unsigned DEFAULT NULL COMMENT '兑换记录ID',
  `issued_at` datetime DEFAULT NULL COMMENT '发放时间',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_product_status` (`product_id`,`status`),
  KEY `idx_user` (`user_id`),
  KEY `idx_record` (`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `QH_menu`(`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`) VALUES
('功能反馈', 'Feedback/index', 'layui-icon-dialogue', 0, NOW(), 2, 1);

INSERT INTO `QH_menu`(`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`) VALUES
('反馈管理', 'Feedback/list', 'layui-icon-dialogue', 0, NOW(), 1, 1);

INSERT INTO `QH_menu`(`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`) VALUES
('积分兑换', 'PointExchange/list', 'layui-icon-gift', 0, NOW(), 2, 1),
('积分商品', 'PointProduct/list', 'layui-icon-gift', 0, NOW(), 1, 1),
('兑换记录', 'PointProduct/records', 'layui-icon-list', 0, NOW(), 1, 1);

-- ==================== balance_log ====================
DROP TABLE IF EXISTS `QH_balance_log`;
CREATE TABLE `QH_balance_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `type` varchar(30) DEFAULT 'recharge' COMMENT '类型: recharge/consume/refund/adjust',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '变动金额（正=增加，负=减少）',
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '变动后余额',
  `description` varchar(255) DEFAULT '' COMMENT '描述',
  `source_type` varchar(50) DEFAULT '' COMMENT '来源类型',
  `source_no` varchar(64) DEFAULT '' COMMENT '来源单号',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='余额变动日志';

-- ==================== withdraw ====================
DROP TABLE IF EXISTS `QH_withdraw`;
CREATE TABLE `QH_withdraw` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '申请人用户ID',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '提现金额',
  `phone` varchar(20) DEFAULT '' COMMENT '手机号',
  `real_name` varchar(100) DEFAULT '' COMMENT '真实姓名',
  `pay_method` varchar(20) DEFAULT 'alipay' COMMENT '收款方式:alipay/wechat/bank',
  `qr_image` varchar(500) DEFAULT '' COMMENT '收款码图片',
  `user_remark` varchar(500) DEFAULT '' COMMENT '用户备注',
  `admin_remark` varchar(500) DEFAULT '' COMMENT '管理员处理备注',
  `transfer_image` varchar(500) DEFAULT '' COMMENT '管理员转账凭证',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '状态:pending/approved/rejected/withdrawn',
  `applied_at` datetime DEFAULT NULL COMMENT '申请时间',
  `handled_at` datetime DEFAULT NULL COMMENT '管理员处理时间',
  `withdrawn_at` datetime DEFAULT NULL COMMENT '用户撤回时间',
  `created_at` datetime DEFAULT NULL COMMENT '申请时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='提现记录';

-- ==================== checkin_record ====================
DROP TABLE IF EXISTS `QH_checkin_record`;
CREATE TABLE `QH_checkin_record` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `checkin_date` date NOT NULL COMMENT '打卡日期',
  `consecutive_days` int(11) NOT NULL DEFAULT 1 COMMENT '连续打卡天数',
  `points_earned` int(11) NOT NULL DEFAULT 0 COMMENT '获得积分',
  `ip` varchar(45) NOT NULL DEFAULT '' COMMENT '打卡IP',
  `created_at` datetime NOT NULL COMMENT '打卡时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_date` (`user_id`, `checkin_date`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_checkin_date` (`checkin_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='打卡记录表';

-- ==================== carousel ====================
DROP TABLE IF EXISTS `QH_carousel`;
CREATE TABLE `QH_carousel` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '标题',
  `image` varchar(255) NOT NULL DEFAULT '' COMMENT '图片URL',
  `url` varchar(500) NOT NULL DEFAULT '' COMMENT '跳转地址',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '状态:0=隐藏,1=显示',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='轮播图管理';

-- ==================== user_notice ====================
DROP TABLE IF EXISTS `QH_user_notice`;
CREATE TABLE `QH_user_notice` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL DEFAULT '' COMMENT '公告标题',
  `content` text COMMENT '公告内容',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '状态:0=隐藏,1=显示',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户公告';

-- ==================== discount_code ====================
DROP TABLE IF EXISTS `QH_discount_code`;
CREATE TABLE `QH_discount_code` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '所属用户ID',
  `code` varchar(32) NOT NULL COMMENT '唯一折扣码',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
  `created_at` datetime NOT NULL COMMENT '生成时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='折扣码表';

-- ==================== rebate_record ====================
DROP TABLE IF EXISTS `QH_rebate_record`;
CREATE TABLE `QH_rebate_record` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(11) unsigned NOT NULL COMMENT 'QH_order.id',
  `pay_trade_no` varchar(255) DEFAULT NULL COMMENT 'QH_pay.trade_no',
  `payer_user_id` int(11) unsigned NOT NULL COMMENT '付款用户ID',
  `referrer_user_id` int(11) unsigned NOT NULL COMMENT '返利归属用户ID（折扣码所有者）',
  `discount_code` varchar(32) NOT NULL COMMENT '使用的折扣码',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '支付金额',
  `rebate_base_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '返利计算基数（充值面额）',
  `rebate_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '结算时的返利比例(%)',
  `rebate_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '返利金额',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/settled/rejected/canceled',
  `settle_at` datetime DEFAULT NULL COMMENT '预计结算时间',
  `settled_at` datetime DEFAULT NULL COMMENT '实际结算时间',
  `risk_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '内部风控原因',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_pay_trade_no` (`pay_trade_no`),
  KEY `idx_referrer` (`referrer_user_id`),
  KEY `idx_code` (`discount_code`),
  KEY `idx_status_settle_at` (`status`,`settle_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='返利记录表';

-- ==================== plugin tables ====================
DROP TABLE IF EXISTS `QH_plugin`;
CREATE TABLE `QH_plugin` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '插件ID',
  `app_id` int(11) unsigned NOT NULL DEFAULT 1 COMMENT '所属应用ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '发布者用户ID',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '插件名称',
  `slug` varchar(100) NOT NULL DEFAULT '' COMMENT '插件标识(唯一)',
  `plugin_dir` varchar(64) NOT NULL DEFAULT '' COMMENT 'Xiuno插件安装目录',
  `category` varchar(30) DEFAULT '' COMMENT '分类',
  `version` varchar(50) NOT NULL DEFAULT '1.0.0' COMMENT '插件版本',
  `author` varchar(100) NOT NULL DEFAULT '' COMMENT '作者',
  `author_url` varchar(255) DEFAULT '' COMMENT '作者网址',
  `description` text COMMENT '插件简介',
  `content` longtext COMMENT '插件详细介绍(富文本)',
  `icon` varchar(255) DEFAULT '' COMMENT '插件图标URL',
  `images` text COMMENT '插件图片(JSON数组,兼容旧数据)',
  `cover` varchar(255) DEFAULT '' COMMENT '插件封面图URL',
  `origin_type` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '来源:1=原创,2=转载',
  `origin_url` varchar(255) DEFAULT '' COMMENT '转载来源地址',
  `origin_author` varchar(100) DEFAULT '' COMMENT '转载原作者',
  `origin_note` varchar(500) DEFAULT '' COMMENT '转载声明/备注',
  `related_plugin_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '关联插件ID，可选',
  `storage_driver` varchar(20) NOT NULL DEFAULT 'local' COMMENT '存储驱动:local/oss',
  `file_path` varchar(255) NOT NULL DEFAULT '' COMMENT '插件文件路径(私有存储)',
  `package_object_key` varchar(500) DEFAULT '' COMMENT '插件包OSS对象Key',
  `package_file_name` varchar(255) DEFAULT '' COMMENT '插件包原始文件名',
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '文件大小(字节)',
  `package_mime_type` varchar(100) DEFAULT '' COMMENT '插件包MIME类型',
  `file_hash` varchar(64) DEFAULT '' COMMENT '文件SHA-256哈希',
  `icon_object_key` varchar(500) DEFAULT '' COMMENT '图标OSS对象Key',
  `cover_object_key` varchar(500) DEFAULT '' COMMENT '封面OSS对象Key',
  `update_description` text COMMENT '最新版本更新说明',
  `price` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '插件价格(0为免费)',
  `pay_type` varchar(10) DEFAULT 'balance' COMMENT '支付方式:balance=余额,points=积分',
  `download_count` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '下载次数',
  `rating_count` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '评分人数',
  `rating_avg` decimal(3,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '平均评分(0-5)',
  `comment_count` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '评论数量',
  `status` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '状态:0=待审核,1=已上架,2=已下架,3=审核拒绝',
  `audit_note` varchar(500) DEFAULT '' COMMENT '审核备注',
  `sort` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '排序(数字越大越靠前)',
  `is_hot` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '是否热门:0=否,1=是',
  `is_recommend` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '是否推荐:0=否,1=是',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `published_at` datetime DEFAULT NULL COMMENT '上架时间',
  `publish_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '发布类型:0=立即发布,1=定时发布',
  `publish_time` datetime DEFAULT NULL COMMENT '定时发布时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plugin_app_slug` (`app_id`,`slug`),
  KEY `idx_plugin_app_status` (`app_id`,`status`,`sort`),
  KEY `status` (`status`),
  KEY `price` (`price`),
  KEY `download_count` (`download_count`),
  KEY `rating_avg` (`rating_avg`),
  KEY `sort` (`sort`),
  KEY `related_plugin_id` (`related_plugin_id`),
  KEY `is_hot` (`is_hot`),
  KEY `is_recommend` (`is_recommend`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件表';

DROP TABLE IF EXISTS `QH_plugin_reward`;
CREATE TABLE `QH_plugin_reward` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '奖励ID',
  `plugin_id` int(11) unsigned NOT NULL COMMENT '插件ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '发布者用户ID',
  `plugin_name` varchar(255) NOT NULL DEFAULT '' COMMENT '插件名称快照',
  `scene` varchar(32) NOT NULL DEFAULT 'first_approval' COMMENT '奖励场景',
  `status` varchar(20) NOT NULL DEFAULT 'issued' COMMENT 'issued/skipped/revoked',
  `points` int(11) NOT NULL DEFAULT 0 COMMENT '奖励积分',
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '奖励平台余额（不可提现）',
  `file_hash` varchar(64) NOT NULL DEFAULT '' COMMENT '插件包哈希快照',
  `reason` varchar(255) NOT NULL DEFAULT '' COMMENT '跳过或撤销原因',
  `config_snapshot` text COMMENT '发放时配置快照',
  `approved_by` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '审核管理员ID',
  `approved_at` datetime NOT NULL COMMENT '审核通过时间',
  `issued_at` datetime DEFAULT NULL COMMENT '奖励到账时间',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plugin_scene` (`plugin_id`,`scene`),
  KEY `idx_user_status_time` (`user_id`,`status`,`issued_at`),
  KEY `idx_hash_scene_status` (`file_hash`,`scene`,`status`),
  KEY `idx_approved_by` (`approved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件发布奖励记录';

DROP TABLE IF EXISTS `QH_plugin_reward_hash_claim`;
CREATE TABLE `QH_plugin_reward_hash_claim` (
  `file_hash` varchar(64) NOT NULL COMMENT '已占用的插件包哈希',
  `plugin_id` int(11) unsigned NOT NULL COMMENT '首次获奖插件ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '首次获奖用户ID',
  `claimed_at` datetime NOT NULL COMMENT '占用时间',
  PRIMARY KEY (`file_hash`),
  UNIQUE KEY `uk_plugin_id` (`plugin_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件奖励包哈希原子占用';

DROP TABLE IF EXISTS `QH_plugin_versions`;
CREATE TABLE `QH_plugin_versions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '版本记录ID',
  `plugin_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '插件ID',
  `version` varchar(50) NOT NULL DEFAULT '' COMMENT '版本号',
  `storage_driver` varchar(20) NOT NULL DEFAULT 'local' COMMENT '存储驱动:local/oss',
  `package_path` varchar(500) DEFAULT '' COMMENT '本地插件包路径或URL',
  `package_object_key` varchar(500) DEFAULT '' COMMENT '插件包OSS对象Key',
  `package_file_name` varchar(255) DEFAULT '' COMMENT '插件包原始文件名',
  `package_file_size` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '插件包大小',
  `package_mime_type` varchar(100) DEFAULT '' COMMENT '插件包MIME类型',
  `package_hash` varchar(64) DEFAULT '' COMMENT '插件包SHA-256哈希',
  `update_description` text COMMENT '更新说明',
  `created_by` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '创建人用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plugin_version` (`plugin_id`,`version`),
  KEY `idx_plugin_id` (`plugin_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件版本历史表';

DROP TABLE IF EXISTS `QH_plugin_package_upload`;
CREATE TABLE `QH_plugin_package_upload` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '上传记录ID',
  `token_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '一次性上传凭证SHA-256',
  `actor_type` varchar(10) NOT NULL COMMENT 'user/admin',
  `actor_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '上传者ID',
  `storage_driver` varchar(20) NOT NULL DEFAULT 'local' COMMENT 'local/oss',
  `file_path` varchar(500) NOT NULL DEFAULT '' COMMENT '本地私有文件路径',
  `package_object_key` varchar(500) NOT NULL DEFAULT '' COMMENT 'OSS对象Key',
  `package_file_name` varchar(255) NOT NULL DEFAULT '' COMMENT '原始文件名',
  `package_file_size` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '文件字节数',
  `package_mime_type` varchar(100) NOT NULL DEFAULT '' COMMENT 'MIME类型',
  `package_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '' COMMENT 'SHA-256',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/cleaning/cleaned/consumed',
  `consumed_plugin_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '最终关联插件ID',
  `expires_at` datetime NOT NULL COMMENT '凭证过期时间',
  `consumed_at` datetime DEFAULT NULL COMMENT '提交发布时间',
  `cleaned_at` datetime DEFAULT NULL COMMENT '孤儿文件清理时间',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token_hash` (`token_hash`),
  KEY `idx_actor_status` (`actor_type`,`actor_id`,`status`),
  KEY `idx_status_expires` (`status`,`expires_at`),
  KEY `idx_consumed_plugin` (`consumed_plugin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件包待提交上传记录';

DROP TABLE IF EXISTS `QH_plugin_resources`;
CREATE TABLE `QH_plugin_resources` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '资源ID',
  `plugin_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '插件ID',
  `version_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '插件版本ID',
  `resource_type` varchar(50) NOT NULL DEFAULT '' COMMENT '资源类型:icon/cover/package/attachment',
  `storage_driver` varchar(20) NOT NULL DEFAULT 'local' COMMENT '存储驱动:local/oss',
  `url` varchar(500) DEFAULT '' COMMENT '资源访问URL或本地路径',
  `object_key` varchar(500) DEFAULT '' COMMENT 'OSS对象Key',
  `file_name` varchar(255) DEFAULT '' COMMENT '原始文件名',
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '文件大小',
  `mime_type` varchar(100) DEFAULT '' COMMENT 'MIME类型',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `created_by` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '创建人用户ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_plugin_id` (`plugin_id`),
  KEY `idx_version_id` (`version_id`),
  KEY `idx_resource_type` (`resource_type`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件资源表';

DROP TABLE IF EXISTS `QH_plugin_order`;
CREATE TABLE `QH_plugin_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '订单ID',
  `order_no` varchar(64) NOT NULL DEFAULT '' COMMENT '订单号',
  `plugin_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '插件ID',
  `plugin_name` varchar(255) NOT NULL DEFAULT '' COMMENT '插件名称',
  `plugin_version` varchar(50) NOT NULL DEFAULT '' COMMENT '插件版本',
  `user_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
  `app_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '应用ID',
  `price` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '订单金额',
  `commission_rate` decimal(5,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '平台抽成比例(%)',
  `commission_amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '平台抽成金额',
  `developer_income` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '开发者收入',
  `pay_type` varchar(20) DEFAULT '' COMMENT '支付方式:alipay,wxpay,qqpay,balance',
  `pay_trade_no` varchar(100) DEFAULT '' COMMENT '支付平台订单号',
  `status` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '状态:0=待支付,1=已支付,2=已取消,3=已退款',
  `paid_at` datetime DEFAULT NULL COMMENT '支付时间',
  `ip` varchar(50) DEFAULT '' COMMENT '下单IP',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`),
  KEY `plugin_id` (`plugin_id`),
  KEY `user_id` (`user_id`),
  KEY `app_id` (`app_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件订单表';

DROP TABLE IF EXISTS `QH_plugin_comment`;
CREATE TABLE `QH_plugin_comment` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '评论ID',
  `plugin_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '插件ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
  `app_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '应用ID',
  `content` text NOT NULL COMMENT '评论内容',
  `rating` tinyint(1) unsigned NOT NULL DEFAULT 5 COMMENT '评分:1-5星',
  `status` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '状态:0=待审核,1=已通过,2=已拒绝',
  `reply_content` text COMMENT '开发者回复内容',
  `reply_at` datetime DEFAULT NULL COMMENT '开发者回复时间',
  `ip` varchar(50) DEFAULT '' COMMENT '评论IP',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `plugin_id` (`plugin_id`),
  KEY `user_id` (`user_id`),
  KEY `app_id` (`app_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件评论表';

DROP TABLE IF EXISTS `QH_plugin_rating`;
CREATE TABLE `QH_plugin_rating` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '评分ID',
  `plugin_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '插件ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
  `app_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '应用ID',
  `rating` tinyint(1) unsigned NOT NULL DEFAULT 5 COMMENT '评分:1-5星',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `plugin_user_app` (`plugin_id`,`user_id`,`app_id`),
  KEY `plugin_id` (`plugin_id`),
  KEY `user_id` (`user_id`),
  KEY `app_id` (`app_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件评分表';

DROP TABLE IF EXISTS `QH_plugin_download`;
CREATE TABLE `QH_plugin_download` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '下载记录ID',
  `plugin_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '插件ID',
  `plugin_version` varchar(50) NOT NULL DEFAULT '' COMMENT '插件版本',
  `user_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
  `app_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '应用ID',
  `order_id` int(11) unsigned DEFAULT 0 COMMENT '订单ID(付费插件)',
  `ip` varchar(50) DEFAULT '' COMMENT '下载IP',
  `created_at` datetime DEFAULT NULL COMMENT '下载时间',
  PRIMARY KEY (`id`),
  KEY `plugin_id` (`plugin_id`),
  KEY `user_id` (`user_id`),
  KEY `app_id` (`app_id`),
  KEY `order_id` (`order_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件下载记录表';

DROP TABLE IF EXISTS `QH_plugin_download_token`;
CREATE TABLE `QH_plugin_download_token` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Token ID',
  `token` varchar(64) NOT NULL DEFAULT '' COMMENT '下载凭证',
  `plugin_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '插件ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
  `app_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '应用ID',
  `order_id` int(11) unsigned DEFAULT 0 COMMENT '订单ID(付费插件)',
  `ip` varchar(50) DEFAULT '' COMMENT '请求IP',
  `used` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '是否已使用:0=未使用,1=已使用',
  `used_at` datetime DEFAULT NULL COMMENT '使用时间',
  `expires_at` datetime NOT NULL COMMENT '过期时间',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `plugin_id` (`plugin_id`),
  KEY `user_id` (`user_id`),
  KEY `app_id` (`app_id`),
  KEY `used` (`used`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='临时下载凭证表';

DROP TABLE IF EXISTS `QH_plugin_purchase`;
CREATE TABLE `QH_plugin_purchase` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '购买记录ID',
  `plugin_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '插件ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
  `app_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '应用ID',
  `order_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '订单ID',
  `price` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '购买价格',
  `created_at` datetime DEFAULT NULL COMMENT '购买时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `plugin_user_app` (`plugin_id`,`user_id`,`app_id`),
  KEY `plugin_id` (`plugin_id`),
  KEY `user_id` (`user_id`),
  KEY `app_id` (`app_id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件购买记录表';

-- 登录日志表(新环境需要,从初始化 SQL 直接创建,避免线上缺表)
CREATE TABLE IF NOT EXISTS `QH_loginlog` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `username` varchar(100) NOT NULL DEFAULT '' COMMENT '登录名',
  `power` varchar(20) NOT NULL DEFAULT '' COMMENT '权限类型:admin/user',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=成功 0=失败',
  `ip` varchar(64) NOT NULL DEFAULT '' COMMENT '登录IP',
  `create_time` datetime DEFAULT NULL COMMENT '登录时间',
  PRIMARY KEY (`id`),
  KEY `idx_uid_power_status` (`uid`,`power`,`status`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录日志表';

-- ==================== config entries ====================
INSERT INTO `QH_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`) VALUES
('checkin_enabled', 'checkin', '启用打卡功能', '开启后用户可在面板首页进行每日打卡获取积分', 'bool', '1', '', '', '', ''),
('checkin_base_points', 'checkin', '单次打卡积分', '用户每次打卡获得的基础积分', 'number', '5', '', 'required', '', ''),
('checkin_consecutive_days', 'checkin', '连续打卡天数阈值', '使用英文逗号分隔，并与奖励积分逐项对应，例如：3,7,15,30', 'string', '3,7,15,30', '', 'required', '', ''),
('checkin_consecutive_bonus', 'checkin', '连续打卡奖励积分', '使用英文逗号分隔，并与天数阈值逐项对应，例如：3,7,15,30', 'string', '3,7,15,30', '', 'required', '', '');

INSERT INTO `QH_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'plugin_commission_enabled', 'plugin_market', '启用插件销售平台抽成', '开启后，余额及在线支付的插件订单按设置比例抽成；关闭后发布者获得全部销售收入。积分支付始终免抽成。', 'bool', '1', '', '', '', ''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name` = 'plugin_commission_enabled');

INSERT INTO `QH_config` (`name`, `group`, `title`, `tip`, `type`, `value`, `content`, `rule`, `extend`, `tip_type`)
SELECT 'plugin_commission_rate', 'plugin_market', '插件销售平台抽成比例（%）', '仅在抽成开关开启时生效，范围 0～100，最多保留两位小数；新比例仅影响后续支付成功的订单。', 'number', '10.00', '', 'required', 'min="0" max="100" step="0.01"', ''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_config` WHERE `name` = 'plugin_commission_rate');

-- ==================== corrected menus ====================
-- Remove any malformed menu entries that may have been created above
DELETE FROM `QH_menu`
WHERE `url` IN ('/Plugin/list', '/PluginOrder/list', '/PluginComment/list',
                '/UserPlugin/market', '/UserPlugin/list', '/UserPlugin/comments', '/UserPlugin/purchases');

-- Admin-only feature menus: power=1
UPDATE `QH_menu`
SET `power` = 1, `status` = 1
WHERE `url` IN ('Plugin/list', 'PluginOrder/list', 'PluginComment/list',
                'Feedback/list', 'Checkin/records', 'Checkin/config', 'PointProduct/list');

-- Admin plugin center
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '插件中心', '#', 'layui-icon-component', 0, NOW(), 1, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `QH_menu`
  WHERE `name` = '插件中心' AND `parentid` = 0 AND `url` = '#' AND `power` = 1
);

SET @admin_plugin_id = (
  SELECT `id` FROM `QH_menu`
  WHERE `name` = '插件中心' AND `parentid` = 0 AND `url` = '#' AND `power` = 1
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '插件列表', 'Plugin/list', '', @admin_plugin_id, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Plugin/list' AND `power` = 1);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '插件订单', 'PluginOrder/list', '', @admin_plugin_id, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'PluginOrder/list' AND `power` = 1);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '插件评论', 'PluginComment/list', '', @admin_plugin_id, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'PluginComment/list' AND `power` = 1);

UPDATE `QH_menu`
SET `parentid` = @admin_plugin_id, `power` = 1, `status` = 1
WHERE `url` IN ('Plugin/list', 'PluginOrder/list', 'PluginComment/list');

-- Admin feedback management
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '反馈管理', 'Feedback/list', 'layui-icon-email', 0, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Feedback/list' AND `power` = 1);

UPDATE `QH_menu`
SET `name` = '反馈管理', `icon` = 'layui-icon-email', `parentid` = 0, `power` = 1, `status` = 1
WHERE `url` = 'Feedback/list';

-- Admin system settings children
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '系统设置', '#', 'layui-icon-set', 0, NOW(), 1, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `QH_menu`
  WHERE `name` = '系统设置' AND `parentid` = 0 AND `url` = '#' AND `power` = 1
);

SET @admin_set_id = (
  SELECT `id` FROM `QH_menu`
  WHERE `name` = '系统设置' AND `parentid` = 0 AND `url` = '#' AND `power` = 1
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '轮播图管理', 'Set/carousel', '', @admin_set_id, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Set/carousel' AND `power` = 1);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '用户通知', 'Set/userNotice', '', @admin_set_id, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Set/userNotice' AND `power` = 1);

UPDATE `QH_menu`
SET `parentid` = @admin_set_id, `power` = 1, `status` = 1
WHERE `url` IN ('Set/index', 'Set/carousel', 'Set/userNotice') AND `power` = 1;

-- Admin checkin management
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '打卡管理', '#', 'layui-icon-date', 0, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `name` = '打卡管理' AND `url` = '#' AND `power` = 1);

SET @admin_checkin_id = (
  SELECT `id` FROM `QH_menu`
  WHERE `name` = '打卡管理' AND `parentid` = 0 AND `url` = '#' AND `power` = 1
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '打卡配置', 'Checkin/config', '', @admin_checkin_id, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Checkin/config' AND `power` = 1);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '打卡记录', 'Checkin/records', '', @admin_checkin_id, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Checkin/records' AND `power` = 1);

UPDATE `QH_menu`
SET `name` = '打卡记录', `icon` = '', `parentid` = @admin_checkin_id, `power` = 1, `status` = 1
WHERE `url` = 'Checkin/records' AND `power` = 1;

UPDATE `QH_menu`
SET `name` = '打卡配置', `icon` = '', `parentid` = @admin_checkin_id, `power` = 1, `status` = 1
WHERE `url` = 'Checkin/config' AND `power` = 1;

-- Admin point management (parent)
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '积分管理', '#', 'layui-icon-cart-simple', 0, NOW(), 1, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `QH_menu`
  WHERE `name` = '积分管理' AND `parentid` = 0 AND `url` = '#' AND `power` = 1
);

SET @admin_point_id = (
  SELECT `id` FROM `QH_menu`
  WHERE `name` = '积分管理' AND `parentid` = 0 AND `url` = '#' AND `power` = 1
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '积分商品', 'PointProduct/list', '', @admin_point_id, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'PointProduct/list' AND `power` = 1);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '兑换记录', 'PointProduct/records', '', @admin_point_id, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'PointProduct/records' AND `power` = 1);

UPDATE `QH_menu`
SET `parentid` = @admin_point_id, `power` = 1, `status` = 1
WHERE `url` IN ('PointProduct/list', 'PointProduct/records');

-- User plugin center
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '插件中心', '#', 'layui-icon-util', 0, NOW(), 2, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `QH_menu`
  WHERE `name` = '插件中心' AND `parentid` = 0 AND `url` = '#' AND `power` = 2
);

SET @user_plugin_id = (
  SELECT `id` FROM `QH_menu`
  WHERE `name` = '插件中心' AND `parentid` = 0 AND `url` = '#' AND `power` = 2
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '插件市场', 'UserPlugin/market', '', @user_plugin_id, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'UserPlugin/market' AND `power` = 2);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '我的插件', 'UserPlugin/list', '', @user_plugin_id, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'UserPlugin/list' AND `power` = 2);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '发布插件', 'UserPlugin/create', '', @user_plugin_id, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'UserPlugin/create' AND `power` = 2);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '评论管理', 'UserPlugin/comments', '', @user_plugin_id, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'UserPlugin/comments' AND `power` = 2);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '我的购买', 'UserPlugin/purchases', '', @user_plugin_id, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'UserPlugin/purchases' AND `power` = 2);

UPDATE `QH_menu`
SET `parentid` = @user_plugin_id, `power` = 2, `status` = 1
WHERE `url` IN ('UserPlugin/market', 'UserPlugin/list', 'UserPlugin/create', 'UserPlugin/comments', 'UserPlugin/purchases');

-- User feature menus (top-level, power=2)
-- 每日打卡已合并到个人中心打卡记录tab，不再作为独立菜单项

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '意见反馈', 'Feedback/index', 'layui-icon-email', 0, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Feedback/index' AND `power` = 2);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '积分兑换', 'PointExchange/list', 'layui-icon-cart-simple', 0, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'PointExchange/list' AND `power` = 2);

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '积分日志', 'PointLog/list', 'layui-icon-list', 0, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'PointLog/list' AND `power` = 2);

-- 余额日志已合并到个人中心余额明细tab，移除独立菜单项（彻底删除，避免历史菜单残留）
DELETE FROM `QH_menu`
WHERE `url` IN ('BalanceLog/list', 'BalanceLog/index') AND `power` = 2;

INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '余额提现', 'Withdraw/index', 'layui-icon-rmb', 0, NOW(), 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Withdraw/index' AND `power` = 2);

UPDATE `QH_menu`
SET `parentid` = 0, `power` = 2, `status` = 1
WHERE `url` IN ('Feedback/index', 'PointExchange/list',
                'PointLog/list', 'Withdraw/index')
  AND `power` = 2;

-- Normalize routes that existed in older install scripts.
UPDATE `QH_menu`
SET `name` = '积分兑换', `url` = 'PointExchange/list', `icon` = 'layui-icon-cart-simple', `parentid` = 0, `power` = 2, `status` = 1
WHERE `url` = 'PointExchange/index' AND `power` = 2;

UPDATE `QH_menu`
SET `name` = '积分兑换', `icon` = 'layui-icon-cart-simple', `parentid` = 0, `power` = 2, `status` = 1
WHERE `url` = 'PointExchange/list' AND `power` = 2;

-- 每日打卡已合并到个人中心打卡记录tab，移除独立菜单项
UPDATE `QH_menu`
SET `status` = 0
WHERE `url` IN ('Checkin/index', 'Checkin/list', 'Checkin/records') AND `power` = 2;

-- User rebate center
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '返利中心', 'Rebate/index', 'layui-icon-rmb', 0, NOW(), 2, 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `QH_menu`
  WHERE `name` = '返利中心' AND `parentid` = 0 AND `url` = 'Rebate/index' AND `power` = 2
);

SET @user_rebate_id = (
  SELECT `id` FROM `QH_menu`
  WHERE `name` = '返利中心' AND `parentid` = 0 AND `url` = 'Rebate/index' AND `power` = 2
  ORDER BY `id` ASC LIMIT 1
);

UPDATE `QH_menu`
SET `parentid` = 0, `url` = 'Rebate/index', `icon` = 'layui-icon-rmb', `power` = 2, `status` = 1
WHERE `id` = @user_rebate_id;

UPDATE `QH_menu`
SET `status` = 0
WHERE `parentid` = @user_rebate_id AND `url` = 'Rebate/index';

UPDATE `QH_menu`
SET `parentid` = @user_rebate_id, `power` = 2, `status` = 1
WHERE `url` = 'Rebate/myRebateList' AND `power` = 2;

-- Admin withdraw management
INSERT INTO `QH_menu` (`name`, `url`, `icon`, `parentid`, `addtime`, `power`, `status`)
SELECT '提现管理', 'Order/withdraw', 'layui-icon-rmb', 0, NOW(), 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `QH_menu` WHERE `url` = 'Order/withdraw' AND `power` = 1);

-- Deduplicate menus: keep only one row per unique url+power combination
DELETE m1 FROM `QH_menu` m1
JOIN `QH_menu` m2
  ON m1.`id` > m2.`id`
 AND m1.`url` = m2.`url`
 AND m1.`power` = m2.`power`
WHERE m1.`url` NOT IN ('', '#');

-- Deduplicate placeholder parents: keep only one per name+url+power+parentid
DELETE m1 FROM `QH_menu` m1
JOIN `QH_menu` m2
  ON m1.`id` > m2.`id`
 AND m1.`name` = m2.`name`
 AND m1.`url` = m2.`url`
 AND m1.`power` = m2.`power`
 AND m1.`parentid` = m2.`parentid`
WHERE m1.`url` IN ('', '#');
