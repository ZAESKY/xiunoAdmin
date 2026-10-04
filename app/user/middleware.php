<?php
return [
    // 应用初始化
    app\common\middleware\SameOrigin::class,
    app\user\middleware\CheckLogin::class,
    app\common\middleware\InitApp::class,
];
