<?php
return [
    // 应用初始化
    app\common\middleware\InitApp::class,
    // 浏览器状态变更接口必须来自本站，阻断跨站注册与邮件轰炸。
    app\common\middleware\SameOrigin::class,
];
