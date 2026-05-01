<?php
/*
* +----------------------------------------------------------------------
* | SF 综合验证授权系统
* +----------------------------------------------------------------------
* | Quotes [ 花开的再灿烂，也有凋谢的一天，致我们过去的青春 ]
* +----------------------------------------------------------------------
* | Author: 陌上花开 <2129876388@qq.com>
* +----------------------------------------------------------------------
* | Date: 2022年1月19日 18:48:32
* +----------------------------------------------------------------------
*/

/**
 * 接口常量定义 - i18n keys
 * @author 陌上花开
 * @since 2020/4/22
 */
define('MESSAGE_OK', 'operation.success');
define('MESSAGE_FAILED', 'operation.failed');
define('MESSAGE_SYSTEM_ERROR', 'common.system_busy');
define('MESSAGE_PARAMETER_MISSING', 'validation.params_missing');
define('MESSAGE_PARAMETER_ERROR', 'validation.param_error');
define('MESSAGE_PERMISSON_DENIED', 'common.no_permission');
define('MESSAGE_INTERNAL_ERROR', 'common.system_busy');
define('MESSAGE_NO_TOKEN', 'auth.token_expired');
define('MESSAGE_TOKEN_FAILED', 'auth.token_expired');

define('MESSAGE_NEEDLOGIN', 'common.need_login');
define('MESSAGE_USER_NO_INFO', 'user.info_error');
define('MESSAGE_USER_FIRBIDDEN', 'user.account_blocked');

define('MESSAGE_NO_DEVICEID', 'validation.missing_id');
define('MESSAGE_NO_DEVICE', 'validation.param_error');
define('MESSAGE_NO_APPVERSION', 'version.version_empty');

define('MESSAGE_NO_MOBILE', 'validation.phone');
define('MESSAGE_MOBILE_INVALID', 'validation.phone');
define('MESSAGE_NO_PASSWORD', 'login.password_empty');
define('MESSAGE_NO_USER_INFO', 'user.info_error');
define('MESSAGE_PASSWORD_ERROR', 'login.password_incorrect');
define('MESSAGE_ACCOUNT_FORBIDDEN', 'login.account_disabled');
define('MESSAGE_MOBILE_REGISTERED', 'app.username_exists');
define('MESSAGE_NO_VCODE', 'notify.enter_captcha');
