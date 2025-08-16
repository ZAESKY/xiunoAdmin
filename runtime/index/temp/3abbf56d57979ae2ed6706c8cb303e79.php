<?php /*a:3:{s:67:"D:\phpstudy_pro\WWW\www.admindev.com\app\index\view\public\403.html";i:1659255385;s:70:"D:\phpstudy_pro\WWW\www.admindev.com\app\index\view\public\header.html";i:1658029177;s:70:"D:\phpstudy_pro\WWW\www.admindev.com\app\index\view\public\footer.html";i:1658029177;}*/ ?>
<!DOCTYPE html>
<html>
<!-- 引入头部 -->
<!DOCTYPE html>
<html lang="zh-cn">
<head>
	<meta charset="utf-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1"/>
	<title><?php echo htmlentities($title); ?></title>
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
<style>
	.img{
		width: 96px;
		height: 96px;
		margin:auto;
		display: block;
		border-radius: 50%;
		border: 3px solid #fff;
		position: relative;
		margin-top: -50px;
	}
	.bgimg{
		background-image: url('/Assets/img/bg1.png');
		border-radius: 4px 4px 0px 0px;
		background-size:100% 100%;
		background-repeat:no-repeat;
		height: 96px;
		padding: 15px;
	}
	#footer {
		height: 40px;
		line-height: 40px;
		bottom: 0;
		width: 100%;
		text-align: center;
		color: #fff;
		font-family: Arial;
		font-size: 12px;
		letter-spacing: 1px;
	}
</style>
<body>
<body>
<!-- 正文开始 -->
<div class="error-page">
    <img class="error-page-img" src="/Assets/module/img/ic_403.png">
    <div class="error-page-info">
        <h1>403</h1>
        <p>抱歉，你无权访问此页面！</p>
        <div>
            <a ew-href="/" class="layui-btn">返回首页</a>
        </div>
    </div>
</div>
<style>
    .error-page {
        position: absolute;
        top: 50%;
        width: 100%;
        text-align: center;
        -o-transform: translateY(-50%);
        -ms-transform: translateY(-50%);
        -moz-transform: translateY(-50%);
        -webkit-transform: translateY(-50%);
        transform: translateY(-50%);
    }

    .error-page .error-page-img {
        display: inline-block;
        height: 260px;
        margin: 10px 15px;
    }

    .error-page .error-page-info {
        vertical-align: middle;
        display: inline-block;
        margin: 10px 15px;
    }

    .error-page .error-page-info > h1 {
        color: #434e59;
        font-size: 72px;
        font-weight: 600;
    }

    .error-page .error-page-info > p {
        color: #777;
        font-size: 20px;
        margin-top: 5px;
    }

    .error-page .error-page-info > div {
        margin-top: 30px;
    }
</style>

<!-- 引入脚部 -->
<script type="text/javascript" src="/Assets/libs/layui/layui.js"></script>
<script type="text/javascript" src="/Assets/js/common.js?v=318"></script>
<script type="text/javascript" src="/Assets/js/SF_home.js"></script>
</body>
</html>