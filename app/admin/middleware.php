<?php
return [
    // 应用初始化
    app\common\middleware\SameOrigin::class,
    app\admin\middleware\CheckLogin::class,
    app\common\middleware\InitApp::class,
];
