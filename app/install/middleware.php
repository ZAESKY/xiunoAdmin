<?php
return [
    // 已安装站点必须在任何安装控制器执行前关闭整个安装入口。
    app\common\middleware\InstallLock::class,
    // 应用初始化
    app\common\middleware\InitApp::class,
];
