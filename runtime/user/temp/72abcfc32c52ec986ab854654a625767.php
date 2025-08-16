<?php /*a:1:{s:67:"D:\phpstudy_pro\WWW\www.admindev.com\app\user\view\login\index.html";i:1659707313;}*/ ?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo htmlentities($title); ?>|登录后台</title>
    <meta name="keywords" content="<?php echo htmlentities($keywords); ?>"/>
    <meta name="description" content="<?php echo htmlentities($description); ?>"/>
    <meta name="renderer" content="webkit">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <link rel="stylesheet" href="/Assets/libs/layui/css/layui.css"/>
    <link rel="stylesheet" href="/Assets/module/admin.css?v=318"/>
    .

</head>

<style>
    .login-wrapper {
        max-width: 420px;
        padding: 20px;
        margin: 0 auto;
        position: relative;
        box-sizing: border-box;
        z-index: 2;
    }

    .login-wrapper > .layui-form {
        padding: 25px 30px;
        background-color: #fff;
        box-shadow: 0 3px 6px -1px rgba(0, 0, 0, 0.19);
        box-sizing: border-box;
        border-radius: 4px;
    }

    .login-wrapper > .layui-form > h2 {
        color: #333;
        font-size: 18px;
        text-align: center;
        margin-bottom: 25px;
    }

    .login-wrapper > .layui-form > .layui-form-item {
        margin-bottom: 25px;
        position: relative;
    }

    .login-wrapper > .layui-form > .layui-form-item:last-child {
        margin-bottom: 0;
    }

    .login-wrapper > .layui-form > .layui-form-item > .layui-input {
        height: 40px;
        line-height: 1.5;
        border-radius: 4px !important;
    }

    .login-wrapper .layui-input-icon-group > .layui-input {
        padding-left: 40px;
    }

    .login-wrapper .layui-input-icon-group > .layui-icon {
        width: 40px;
        height: 40px;
        line-height: 40px;
        font-size: 18px;
        color: #909399;
        position: absolute;
        left: 0;
        top: 0;
        text-align: center;
    }

    .login-wrapper > .layui-form > .layui-form-item.login-captcha-group {
        padding-right: 135px;
    }

    .login-wrapper > .layui-form > .layui-form-item.login-captcha-group > .login-captcha {
        height: 46px;
        width: 120px;
        cursor: pointer;
        box-sizing: border-box;
        border: 1px solid #e6e6e6;
        border-radius: 4px !important;
        position: absolute;
        right: 0;
        top: 0;
    }

    .login-wrapper > .layui-form > .layui-form-item > .layui-form-checkbox {
        margin: 0 !important;
        padding-left: 25px;
    }

    .login-wrapper > .layui-form > .layui-form-item > .layui-form-checkbox > .layui-icon {
        width: 15px !important;
        height: 15px !important;
    }

    .login-wrapper > .layui-form .sf {
        display: inline-block;
        margin-bottom: 0;
        font-weight: 400;
        text-align: center;
        vertical-align: middle;
        touch-action: manipulation;
        cursor: pointer;
        background-image: none;
        border: 1px solid transparent;
        white-space: nowrap;
        user-select: none;
        height: 40px;
        padding: 0 15px;
        font-size: 16px;
        border-radius: 4px;
        transition: color .2s linear,background-color .2s linear,border .2s linear,box-shadow .2s linear;
        color: #fff;
        background-color: #fff;
        border-color: #dcdee2;
        line-height: 1.5;
        border-radius: 4px !important;
        background-color: #2d8cf0;
        border-color: #2d8cf0;
    }
    .login-wrapper > .layui-form .sf2 {
        display: inline-block;
        margin-bottom: 0;
        font-weight: 400;
        text-align: center;
        vertical-align: middle;
        touch-action: manipulation;
        cursor: pointer;
        background-image: none;
        border: 1px solid transparent;
        white-space: nowrap;
        user-select: none;
        height: 40px;
        padding: 0 15px;
        font-size: 16px;
        border-radius: 4px;
        transition: color .2s linear,background-color .2s linear,border .2s linear,box-shadow .2s linear;
        color: #000000;
        background-color: #fff;
        border-color: #dcdee2;
        line-height: 1.5;
        border-radius: 4px !important;
    }

    .login-wrapper > .layui-form > .layui-form-item.login-oauth-group > a > .layui-icon {
        font-size: 26px;
    }

    .login-copyright {
        color: #eee;
        padding-bottom: 20px;
        text-align: center;
        position: relative;
        z-index: 1;
    }

    @media screen and (min-height: 550px) {
        .login-wrapper {
            margin: -250px auto 0;
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            width: 100%;
        }

        .login-copyright {
            position: absolute;
            bottom: 0;
            right: 0;
            left: 0;
        }
    }

    .layui-btn {
        background-color: #5FB878;
        border-color: #5FB878;
    }

    .layui-link {
        color: #5FB878 !important;
    }

    .login-captcha-btn {
        line-height: 44px;
        text-align: center;
        background-color: transparent;
        outline: none;
        color: #666;
        padding: 0 !important;
    }

    /** 获取图形验证码弹窗 */
    .layer-get-code {
        padding: 25px 25px;
    }

    .layer-get-code > p {
        color: #666;
        font-size: 16px;
    }

    .layer-get-code > .lay-code-group {
        position: relative;
        padding-right: 135px;
        margin: 15px 0;
    }

    .layer-get-code > .lay-code-group > .layui-input {
        border-radius: 0;
        height: 46px;
        line-height: 46px;
        background-color: transparent;
        border-color: rgba(111, 121, 122, 0.3);
    }

    .layer-get-code > .lay-code-group > img {
        position: absolute;
        right: 0;
        top: 0;
        height: 46px;
        width: 120px;
        box-sizing: border-box;
        cursor: pointer;
    }

    .layer-get-code .layui-btn-fluid {
        line-height: 50px;
        height: 50px;
    }

    .logo {
        width: 60px !important;
        margin-top: 10px !important;
        margin-bottom: 10px !important;
        margin-left: 20px !important;
    }
    .title {
        font-size: 30px !important;
        font-weight: 550 !important;
        margin-left: 20px !important;
        color: #3492ed !important;
        display: inline-block !important;
        height: 60px !important;
        line-height: 60px !important;
        margin-top: 10px !important;
        position: absolute !important;
    }

    .desc {
        width: 100% !important;
        text-align: center !important;
        color: gray !important;
        height: 60px !important;
        line-height: 60px !important;
    }
    .page-account-other {
        margin: 24px 0;
        text-align: left;
    }
    .page-account-other img {
        width: 24px;
        margin-left: 16px;
        cursor: pointer;
        vertical-align: middle;
        opacity: .7;
        transition: all .2s ease-in-out;
    }
    .layui-layer-page .layui-layer-content{
        background-color: #f5f5f5;
    }
</style>
<body style="background-image: url('/Assets/img/background.svg');background-size: cover;">
<div class="login-wrapper layui-anim layui-anim-scale layui-hide">
    <form class="layui-form layui-card-body" id="divLoading"
    style="padding: 28px 15px;box-sizing: border-box;min-height: 126px;opacity: 0.95">
        <div class="layui-form-item">
            <img class="logo" src="/Assets/img/logo.png" />
            <div class="title">User Login</div>
            <div class="desc">后 台 管 理 中 心
            </div>
        </div>
        <div class="layui-form-item layui-input-icon-group">
            <i class="layui-icon layui-icon-username"></i>
            <input class="layui-input" name="user" placeholder="请输入登录账号" autocomplete="off"
                   lay-verType="tips" lay-verify="required" >
        </div>
        <div class="layui-form-item layui-input-icon-group">
            <i class="layui-icon layui-icon-password"></i>
            <input class="layui-input" name="pass" placeholder="请输入登录密码" type="password"
                   lay-verType="tips" lay-verify="required" >
        </div>
        <div class="layui-form-item">
            <input type="checkbox" name="remember" title="记住密码" lay-skin="primary" checked>
            <a href="reg.html" class="layui-link pull-right">注册用户</a>
        </div>
        <div class="layui-form-item">
            <button type="button" class="layui-btn layui-btn-fluid sf" id="loginBtn">登录</button>
        </div>
        <div class="layui-form-item page-account-other" style="font-size: 14px;">
            <span>其他登录方式</span>
            <a id="wechat_login"><img src="/Assets/img/wechat.svg" alt="微信"></a><a id="qq_login"><img src="/Assets/img/qq.svg" alt="qq"></a><a id="code"><img src="/Assets/img/code.svg" alt="扫码"></a>&emsp;
        </div>
    </form>
</div>

<script src="//lib.baomitu.com/jquery/1.12.4/jquery.min.js"></script>
<script type="text/javascript" src="/Assets/libs/layui/layui.js"></script>
<script type="text/javascript" src="/Assets/js/common.js?v=318"></script>
<script src="/Assets/js/gt.js"></script>
<script>
    layui.use(['layer', 'form', 'admin', 'notice'], function () {
        var $ = layui.jquery;
        var layer = layui.layer;
        var form = layui.form;
        var admin = layui.admin;
        var notice = layui.notice;
        $('.login-wrapper').removeClass('layui-hide');

        $("#wechat_login").click(function(){
            notice.error({
                title: 'SF提示您',
                message: '暂不支持',
                animateInside:true,
                position:'topCenter',
                transitionOut:'flipOutX',
                transitionOutMobile: 'flipOutX',
                displayMode:'2',
                audio:'1'
            });
        });

        window.qrcodeLogin = function(username, appid){
            notice.msg('正在执行中..', {icon: 4, close: true});
            $.ajax({
                type: "POST",
                url: '/api.php/Qrlogin/userLogin',
                data: {username:username,appid:appid},
                dataType: "json",
                success: function(data) {
                    notice.destroy();
                    if(data.code == 0){
                        notice.success({
                            title: '登录通知',
                            message: data.msg,
                            animateInside:true,
                            position:'topCenter',
                            transitionOut:'flipOutX',
                            transitionOutMobile: 'flipOutX',
                            displayMode:'2',
                            audio:'1'
                        });
                        setTimeout(function (){location.href = '<?php echo url("/Index/index"); ?>';},1500);

                    }else{
                        notice.error({
                            title: '登录通知',
                            message: data.msg,
                            animateInside:true,
                            position:'topCenter',
                            transitionOut:'flipOutX',
                            transitionOutMobile: 'flipOutX',
                            displayMode:'2',
                            audio:'1'
                        });
                    }
                },
                error: function() {
                    notice.destroy();
                    notice.error({
                        title: '登录通知',
                        message: '服务器错误',
                        animateInside:true,
                        position:'topCenter',
                        transitionOut:'flipOutX',
                        transitionOutMobile: 'flipOutX',
                        displayMode:'2',
                        audio:'1'
                    });
                }
            });
        }
        $("#code").click(function(){
            admin.open({
                type: 2,
                title: '扫码登录',
                content: '/qrlogin/qrlogin.html',
                area: ['400px', '300px'],
                data: {type:'userLogin'}
            });
        });
        <?php if(!(empty($code) || (($code instanceof \think\Collection || $code instanceof \think\Paginator ) && $code->isEmpty()))): ?>
        admin.btnLoading(' #loginBtn');
        admin.showLoading('#divLoading', 2, '.8');
        $.ajax({
            type: "post",
            url: "/api.php/Social/login",
            data: {
                "code": "<?php echo htmlentities($code); ?>",
                "state": "<?php echo htmlentities($state); ?>",
                "userType": "admin",
            },
            dataType: "json",
            success: function(data) {
                if (data.code == 0) {
                    notice.msg('正在为您跳转QQ登录页面...', {icon: 4, close: true, audio:'1'});
                    setTimeout(function (){
                        notice.destroy();
                        location.href=data.data.url;
                    },1000)
                } else {
                    notice.error({
                        title: 'SF提示您',
                        message: data.msg,
                        animateInside:true,
                        position:'topCenter',
                        transitionOut:'flipOutX',
                        transitionOutMobile: 'flipOutX',
                        displayMode:'2',
                        audio:'1'
                    });
                }
                admin.removeLoading('#divLoading', true, true);
                admin.btnLoading(' #loginBtn', false);
            }, error: function () {
                notice.error({
                    title: 'SF提示您',
                    message: '服务器错误，请稍后重试~',
                    animateInside:true,
                    position:'topCenter',
                    transitionOut:'flipOutX',
                    transitionOutMobile: 'flipOutX',
                    timeout:false,
                    displayMode:'2',
                    audio:'1'
                });
                admin.removeLoading('#divLoading', true, true);
                admin.btnLoading(' #loginBtn', false);
                return false;
            }
        });
        <?php endif; ?>
        $("#qq_login").click(function(){
            admin.btnLoading(' #loginBtn');
            admin.showLoading('#divLoading', 2, '.8');
            $.ajax({
                type: "post",
                url: "/api.php/Social/login",
                data: {
                    "userType": "admin",
                },
                dataType: "json",
                success: function(data) {
                    if (data.code == 0) {
                        notice.msg('正在为您跳转QQ登录页面...', {icon: 4, close: true, audio:'1'});
                        setTimeout(function (){
                            notice.destroy();
                            location.href=data.data.url;
                        },1000)
                    } else {
                        notice.error({
                            title: 'SF提示您',
                            message: data.msg,
                            animateInside:true,
                            position:'topCenter',
                            transitionOut:'flipOutX',
                            transitionOutMobile: 'flipOutX',
                            displayMode:'2',
                            audio:'1'
                        });
                    }
                    admin.removeLoading('#divLoading', true, true);
                    admin.btnLoading(' #loginBtn', false);
                }, error: function () {
                    notice.error({
                        title: 'SF提示您',
                        message: '服务器错误，请稍后重试~',
                        animateInside:true,
                        position:'topCenter',
                        transitionOut:'flipOutX',
                        transitionOutMobile: 'flipOutX',
                        timeout:false,
                        displayMode:'2',
                        audio:'1'
                    });
                    admin.removeLoading('#divLoading', true, true);
                    admin.btnLoading(' #loginBtn', false);
                    return false;
                }
            });
        });

        <?php if($captcha_open == '1'): ?>
            initGeetest4({
                captchaId: '<?php echo htmlentities($captcha_id); ?>',
                product: 'bind',
            }, function (gt) {
                window.gt = gt
                gt
                    .appendTo("#captcha")
                    .onSuccess(function (e) {
                        var username = $("input[name='user']").val();
                        var password = $("input[name='pass']").val();
                        var result = gt.getValidate();
                        result.username = username;
                        result.password = password;
                        admin.btnLoading('#loginBtn');
                        admin.showLoading('#divLoading', 2, '.8');
                        $.ajax({
                            type: "post",
                            url: "<?php echo url('/Login/login'); ?>",
                            data: result,
                            dataType: "json",
                            success: function(data) {
                                if (data.code == 0) {
                                    notice.success({
                                        title: 'SF提示您',
                                        message: data.msg,
                                        animateInside:true,
                                        position:'topCenter',
                                        transitionOut:'flipOutX',
                                        transitionOutMobile: 'flipOutX',
                                        displayMode:'2',
                                        audio:'1'
                                    });
                                    setTimeout(function (){
                                        location.href="<?php echo url('/Index/index'); ?>";
                                    },2000)
                                } else {
                                    notice.error({
                                        title: 'SF提示您',
                                        message: data.msg,
                                        animateInside:true,
                                        position:'topCenter',
                                        transitionOut:'flipOutX',
                                        transitionOutMobile: 'flipOutX',
                                        displayMode:'2',
                                        audio:'1'
                                    });
                                }
                                admin.removeLoading('#divLoading', true, true);
                                admin.btnLoading('#loginBtn', false);
                            }, error: function () {
                                notice.error({
                                    title: 'SF提示您',
                                    message: '服务器错误，请稍后重试~',
                                    animateInside:true,
                                    position:'topCenter',
                                    transitionOut:'flipOutX',
                                    transitionOutMobile: 'flipOutX',
                                    timeout:false,
                                    displayMode:'2',
                                    audio:'1'
                                });
                                admin.removeLoading('#divLoading', true, true);
                                admin.btnLoading('#loginBtn', false);
                                return false;
                            }
                        });
                    });
            });

            $('#loginBtn').click(function () {
                var user = $("input[name='user']").val();
                var pass = $("input[name='pass']").val();
                if(!user){
                    notice.warning({
                        title: 'SF提示您',
                        message: '请填写用户名！',
                        animateInside:true,
                        position:'topCenter',
                        transitionOut:'flipOutX',
                        transitionOutMobile: 'flipOutX',
                        displayMode:'2',
                        audio:'1'
                    });
                    return false;
                }
                if(!pass){
                    notice.warning({
                        title: 'SF提示您',
                        message: '请填写密码！',
                        animateInside:true,
                        position:'topCenter',
                        transitionOut:'flipOutX',
                        transitionOutMobile: 'flipOutX',
                        displayMode:'2',
                        audio:'1'
                    });
                    return false;
                }
                gt.showBox();
            });
        <?php else: ?>
            $(' #loginBtn').click(function() {
                var user = $("input[name='user']").val();
                var pass = $("input[name='pass']").val();
                if(!user){
                    notice.warning({
                        title: 'SF提示您',
                        message: '请填写用户名！',
                        animateInside:true,
                        position:'topCenter',
                        transitionOut:'flipOutX',
                        transitionOutMobile: 'flipOutX',
                        displayMode:'2',
                        audio:'1'
                    });
                    return false;
                }
                if(!pass){
                    notice.warning({
                        title: 'SF提示您',
                        message: '请填写密码！',
                        animateInside:true,
                        position:'topCenter',
                        transitionOut:'flipOutX',
                        transitionOutMobile: 'flipOutX',
                        displayMode:'2',
                        audio:'1'
                    });
                    return false;
                }
                admin.btnLoading(' #loginBtn');
                admin.showLoading('#divLoading', 2, '.8');
                $.ajax({
                    type: "post",
                    url: "<?php echo url('/Login/login'); ?>",
                    data: {
                        "username": user,
                        "password": pass,
                    },
                    dataType: "json",
                    success: function(data) {
                        if (data.code == 0) {
                            notice.success({
                                title: 'SF提示您',
                                message: data.msg,
                                animateInside:true,
                                position:'topCenter',
                                transitionOut:'flipOutX',
                                transitionOutMobile: 'flipOutX',
                                displayMode:'2',
                                audio:'1'
                            });
                            setTimeout(function (){
                                location.href="<?php echo url('/Index/index'); ?>";
                            },2000)
                        } else {
                            notice.error({
                                title: 'SF提示您',
                                message: data.msg,
                                animateInside:true,
                                position:'topCenter',
                                transitionOut:'flipOutX',
                                transitionOutMobile: 'flipOutX',
                                displayMode:'2',
                                audio:'1'
                            });
                        }
                        admin.removeLoading('#divLoading', true, true);
                        admin.btnLoading(' #loginBtn', false);
                    }, error: function () {
                        notice.error({
                            title: 'SF提示您',
                            message: '服务器错误，请稍后重试~',
                            animateInside:true,
                            position:'topCenter',
                            transitionOut:'flipOutX',
                            transitionOutMobile: 'flipOutX',
                            timeout:false,
                            displayMode:'2',
                            audio:'1'
                        });
                        admin.removeLoading('#divLoading', true, true);
                        admin.btnLoading(' #loginBtn', false);
                        return false;
                    }
                });
            });
        <?php endif; ?>
    });
</script>