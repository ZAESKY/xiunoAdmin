<?php /*a:1:{s:68:"D:\phpstudy_pro\WWW\www.admindev.com\app\admin\view\index\index.html";i:1659770587;}*/ ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link href="/favicon.ico" rel="icon">
    <title><?php echo htmlentities($title); ?>|后台管理中心</title>
    <meta name="keywords" content="<?php echo htmlentities($keywords); ?>"/>
    <meta name="description" content="<?php echo htmlentities($description); ?>"/>
    <link rel="stylesheet" href="/Assets/libs/layui/css/layui.css"/>
    <link rel="stylesheet" href="/Assets/module/admin.css?v=318"/>
    <link rel="stylesheet" href="/Assets/css/sf-style.css"/>
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>
<body class="page-no-scroll">

<div class="layui-layout layui-layout-admin">
    <!-- 头部 -->
    <div class="layui-header">
        <div class="layui-logo">
            <img src="/Assets/img/logo.png"/>
            <cite> <?php echo htmlentities($title); ?></cite>
        </div>
        <ul class="layui-nav layui-layout-left">
            <li class="layui-nav-item" lay-unselect>
                <a ew-event="flexible" title="侧边伸缩"><i class="layui-icon layui-icon-shrink-right"></i></a>
            </li>
            <li class="layui-nav-item" lay-unselect>
                <a ew-event="refresh" title="刷新"><i class="layui-icon layui-icon-refresh-3"></i></a>
            </li>
        </ul>
        <ul class="layui-nav layui-layout-right">
            <li class="layui-nav-item" lay-unselect>
                <a ew-event="note" title="便签"><i class="layui-icon layui-icon-note"></i></a>
            </li>
            <li class="layui-nav-item layui-hide-xs" lay-unselect>
                <a ew-event="fullScreen" title="全屏"><i class="layui-icon layui-icon-screen-full"></i></a>
            </li>
            <li class="layui-nav-item layui-hide-xs" lay-unselect>
                <a ew-event="lockScreen" title="锁屏"><i class="layui-icon layui-icon-password"></i></a>
            </li>


            <li class="layui-nav-item" lay-unselect>
                <a>
                    <img src="//q4.qlogo.cn/headimg_dl?dst_uin=<?php echo htmlentities($adminInfo['qq']); ?>&spec=100" class="layui-nav-img">
                    <cite>管理员</cite>
                </a>
                <dl class="layui-nav-child">
                    <dd lay-unselect>
                        <a ew-href="<?php echo url('/Index/editPassword'); ?>">修改密码</a>
                    </dd>
                    <hr>
                    <dd lay-unselect>
                        <a lay-filter="logout" lay-submit>退出登录</a>
                    </dd>
                </dl>
            </li>
            <li class="layui-nav-item" lay-unselect>
                <a ew-event="theme" title="主题"><i class="layui-icon layui-icon-more-vertical"></i></a>
            </li>

        </ul>
    </div>

    <!-- 侧边栏 -->
    <div class="layui-side">
        <div class="layui-side-scroll">
            <ul class="layui-nav layui-nav-tree arrow2" lay-filter="admin-side-nav" lay-accordion="true" lay-shrink="_all">
                <?php if(is_array($menuList) || $menuList instanceof \think\Collection || $menuList instanceof \think\Paginator): $key = 0; $__LIST__ = $menuList;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$val): $mod = ($key % 2 );++$key;?>
                <li class="layui-nav-item">
                    <?php if($val['url'] == '#'): ?>
                    <a><i class="layui-icon <?php echo htmlentities($val['icon']); ?>"></i>&emsp;<cite><?php echo htmlentities($val['name']); ?></cite></a>
                        <?php if(isset($val['children']) && !empty($val['children'])): ?>
                        <dl class="layui-nav-child">
                        <?php if(is_array($val['children']) || $val['children'] instanceof \think\Collection || $val['children'] instanceof \think\Paginator): $ko = 0; $__LIST__ = $val['children'];if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$vo): $mod = ($ko % 2 );++$ko;if(isset($vo['children']) && !empty($vo['children'])): ?>
                            <dd>
                                <a><?php echo htmlentities($vo['name']); ?></a>
                                <?php if(is_array($vo['children']) || $vo['children'] instanceof \think\Collection || $vo['children'] instanceof \think\Paginator): $k = 0; $__LIST__ = $vo['children'];if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$v): $mod = ($k % 2 );++$k;?>
                                    <dl class="layui-nav-child">
                                        <dd><a lay-href="<?php echo url('/'.$v['url']); ?>"><?php echo htmlentities($v['name']); ?></a></dd>
                                    </dl>

                                <?php endforeach; endif; else: echo "" ;endif; ?>
                            </dd>
                            <?php else: ?>
                            <dd><a lay-href="<?php echo url('/'.$vo['url']); ?>"><?php echo htmlentities($vo['name']); ?></a></dd>
                            <?php endif; ?>
                        <?php endforeach; endif; else: echo "" ;endif; ?>
                        </dl>
                        <?php endif; else: ?>
                    <a lay-href="<?php echo url('/'.$val['url']); ?>" ><i class="layui-icon <?php echo htmlentities($val['icon']); ?>"></i>&emsp;<cite><?php echo htmlentities($val['name']); ?></cite></a>
                    <?php endif; ?>
                </li>
                <?php endforeach; endif; else: echo "" ;endif; ?>
            </ul>
        </div>
    </div>

    <!-- 主体部分 -->
    <div class="layui-body"></div>
    <!-- 底部 -->
</div>

<!-- 加载动画 -->
<div class="page-loading">
    <div class="signal-loader">
        <span></span><span></span><span></span><span></span>
    </div>
</div>

<script type="text/javascript" src="/Assets/libs/layui/layui.js"></script>
<script type="text/javascript" src="/Assets/js/common.js?v=318"></script>
<script>
    layui.use(['index', 'form', 'admin', 'notice'], function () {
        var $ = layui.jquery;
        var form = layui.form;
        var admin = layui.admin;
        var notice = layui.notice;
        var index = layui.index;

        // 默认加载主页
        index.loadHome({
            menuPath: '<?php echo url("/Index/main"); ?>',
            menuName: '<i class="layui-icon layui-icon-home"></i>'
        });
        form.on('submit(logout)', function () {
            parent.layui.admin.confirm('确认退出登录吗？', function (index) {
                notice.msg('正在退出登录中..', {icon: 4, close: true});
                $.ajax({
                    type: "POST",
                    url: '<?php echo url("/Index/logOut"); ?>',
                    dataType: "json",
                    success: function(data) {
                        notice.destroy();
                        if (data.code == 0) {
                            notice.msg("退出登录成功！", {icon: 1, audio:'1'});
                            setTimeout(function (){location.href="<?php echo url('/Login/index'); ?>"},1250);
                        } else {
                            notice.msg(data.msg, {icon: 2, audio:'1'});
                        }
                    },
                    error: function() {
                        notice.destroy();
                        notice.msg("服务器错误！", {icon: 2, audio:'1'});
                    }
                });
                parent.layer.close(index);
            });
        });

    });
</script>
</body>
</html>