<?php
return [
    // 应用初始化
    app\common\middleware\InitApp::class,
    app\api\middleware\ApiMiddleware::class,
];