<?php /*a:3:{s:67:"D:\phpstudy_pro\WWW\www.admindev.com\app\admin\view\index\main.html";i:1659973351;s:70:"D:\phpstudy_pro\WWW\www.admindev.com\app\admin\view\public\header.html";i:1659781097;s:70:"D:\phpstudy_pro\WWW\www.admindev.com\app\admin\view\public\footer.html";i:1652719209;}*/ ?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
	<meta charset="utf-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1"/>
	<title><?php echo htmlentities($title); ?>|后台管理中心</title>
	<meta name="keywords" content="<?php echo htmlentities($keywords); ?>"/>
	<meta name="description" content="<?php echo htmlentities($description); ?>"/>
	<meta name="keywords" content=""/>
	<meta name="description" content=""/>
	<link rel="stylesheet" href="/Assets/libs/layui/css/layui.css"/>
	<link rel="stylesheet" href="/Assets/module/admin.css?v=318"/>
	<link rel="stylesheet" href="/Assets/css/sf-style.css"/>

	<!--[if lt IE 9]>
	<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
	<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
	<![endif]-->
	<style>
		.layui-card .layui-table-view {
			margin: 0;
		}
		.layui-table td, .layui-table th, .layui-table-col-set, .layui-table-fixed-r, .layui-table-grid-down, .layui-table-header, .layui-table-page, .layui-table-tips-main, .layui-table-tool, .layui-table-total, .layui-table-view, .layui-table[lay-skin=line], .layui-table[lay-skin=row] {
			color: #515a6e;
			border: 0;
			border-width: 1px;
			border-bottom-style: solid;
			border-color: #E8EAEC;
			white-space: nowrap;
		}
	</style>
</head>
<body>

<div class="layui-fluid ew-console-wrapper">
    <div class="layui-row layui-col-space15">
        <div class="layui-col-xs6 layui-col-sm6 layui-col-md3">
            <div class="console-app-group" ew-href="<?php echo url('/Auth/list'); ?>" ew-title="授权列表">
                <i class="sf-avatar sf-avatar-icon sf-avatar-green layui-icon layui-icon-auz"
                   style="margin-top: -5px;margin-right: 6px;margin-bottom: 10px"></i>
                <div class="dashboard-monitor-base-info-number">
                    <span class="ivu-numeral"><?php echo htmlentities($auth_count); ?> 个</span>
                </div>
                <div class="dashboard-monitor-base-info-desc">
                    授权总数
                </div>
                <?php if($auth_increase < '0'): ?>
                <div class="dashboard-monitor-base-info-extra dashboard-monitor-base-info-extra-down">
                    <i class="layui-icon layui-icon-down"></i>
                <?php else: ?>
                <div class="dashboard-monitor-base-info-extra dashboard-monitor-base-info-extra-up">
                    <i class="layui-icon layui-icon-up"></i>
                    <?php endif; ?>
                    <span><?php echo htmlentities(abs($auth_increase)); ?> 个</span>
                </div>
            </div>
        </div>
        <div class="layui-col-xs6 layui-col-sm6 layui-col-md3">
            <div class="console-app-group" ew-href="<?php echo url('/User/list'); ?>" ew-title="用户列表">
                <i class="sf-avatar sf-avatar-icon sf-avatar-blue layui-icon layui-icon-group"
                   style="margin-top: -5px;margin-right: 6px;margin-bottom: 10px"></i>
                <div class="dashboard-monitor-base-info-number"><span class="ivu-numeral"><?php echo htmlentities($user_count); ?> 个</span></div>
                <div class="dashboard-monitor-base-info-desc">
                    用户总数
                </div>
                <?php if($user_increase < '0'): ?>
                <div class="dashboard-monitor-base-info-extra dashboard-monitor-base-info-extra-down">
                    <i class="layui-icon layui-icon-down"></i>
                <?php else: ?>
                <div class="dashboard-monitor-base-info-extra dashboard-monitor-base-info-extra-up">
                    <i class="layui-icon layui-icon-up"></i>
                    <?php endif; ?>
                    <span><?php echo htmlentities(abs($user_increase)); ?> 个</span>
                </div>
            </div>
        </div>
        <div class="layui-col-xs6 layui-col-sm6 layui-col-md3">
            <div class="console-app-group" ew-href="<?php echo url('/Pirate/list'); ?>" ew-title="盗版列表">
                <i class="sf-avatar sf-avatar-icon sf-avatar-red layui-icon layui-icon-website"
                   style="margin-top: -5px;margin-right: 6px;margin-bottom: 10px"></i>
                <div class="dashboard-monitor-base-info-number"><span class="ivu-numeral"><?php echo htmlentities($pirate_count); ?> 个</span></div>
                <div class="dashboard-monitor-base-info-desc">
                    盗版总数
                </div>
                <?php if($pirate_increase < '0'): ?>
                <div class="dashboard-monitor-base-info-extra dashboard-monitor-base-info-extra-down">
                    <i class="layui-icon layui-icon-down"></i>
                <?php else: ?>
                <div class="dashboard-monitor-base-info-extra dashboard-monitor-base-info-extra-up">
                    <i class="layui-icon layui-icon-up"></i>
                    <?php endif; ?>
                    <span><?php echo htmlentities(abs($pirate_increase)); ?> 个</span>
                </div>
            </div>
        </div>
        <div class="layui-col-xs6 layui-col-sm6 layui-col-md3">
            <div class="console-app-group" ew-href="<?php echo url('/Payment/list'); ?>" ew-title="认证列表">
                <i class="sf-avatar sf-avatar-icon sf-avatar-purple layui-icon layui-icon-ok-circle"
                   style="margin-top: -5px;margin-right: 6px;margin-bottom: 10px"></i>
                <div class="dashboard-monitor-base-info-number"><span class="ivu-numeral"><?php echo htmlentities($payment_count); ?> 个</span></div>
                <div class="dashboard-monitor-base-info-desc">
                    认证总数
                </div>
                <?php if($payment_increase < '0'): ?>
                <div class="dashboard-monitor-base-info-extra dashboard-monitor-base-info-extra-down">
                    <i class="layui-icon layui-icon-down"></i>
                <?php else: ?>
                <div class="dashboard-monitor-base-info-extra dashboard-monitor-base-info-extra-up">
                    <i class="layui-icon layui-icon-up"></i>
                    <?php endif; ?>
                    <span><?php echo htmlentities(abs($payment_increase)); ?> 个</span>
                </div>
            </div>
        </div>
    </div>
    <div class="layui-row layui-col-space15">
        <div class="layui-col-sm4">
            <div class="layui-card">
                <div class="layui-card-header">今日最新日志
                    <span class="pull-right">
                        <i class="sf-avatar sf-avatar-icon sf-avatar-small sf-avatar-orange layui-icon layui-icon-log"></i>
                    </span>
                </div>
                <div class="layui-card-body dynamic-card-body mini-bar" style="height: 265px;">
                    <ul class="layui-timeline layui-timeline-dynamic">
                        <?php if(empty($log_list) || (($log_list instanceof \think\Collection || $log_list instanceof \think\Paginator ) && $log_list->isEmpty())): ?>
                        <li class="layui-timeline-item">
                            <i class="layui-icon layui-timeline-axis active"></i>
                            <div class="layui-timeline-content layui-text">
                                <div class="layui-timeline-title">暂未日志
                                    <span class="pull-right"><?php echo date('Y-m-d H:i:s'); ?></span>
                                </div>
                            </div>
                        </li>
                        <?php else: $i = 1; foreach($log_list as $res): ?>
                        <li class="layui-timeline-item">
                            <i class="layui-icon layui-timeline-axis <?php if($i % 2 == 0): ?>active<?php endif; ?>"></i>
                            <div class="layui-timeline-content layui-text">
                                <div class="layui-timeline-title"><?php echo htmlentities($res['title']); ?>
                                    <span class="pull-right"><?php echo htmlentities($res['create_time']); ?></span>
                                </div>
                            </div>
                        </li>
                        <?php $i++; ?>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="layui-col-sm6 layui-col-md4">
            <div class="layui-card">
                <div class="layui-card-header">
                    安全中心
                    <span class="pull-right">
                    <i class="sf-avatar sf-avatar-icon sf-avatar-small sf-avatar-green layui-icon layui-icon-ok"></i>
                </span>
                </div>
                <div class="layui-card-body text-center">
                    <div class="demo-cp-item" style="padding-top:15px;height:100px">
                        <div id="SF_Progress"></div>
                    </div>
                    <div id="loading"></div>
                    <div id="sfhtml"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript" src="/Assets/libs/layui/layui.js"></script>
<script type="text/javascript" src="/Assets/js/common.js?v=318"></script>
<!--<script type="text/javascript" src="/Assets/js/template.js"></script>-->
<script>
    layui.use(['layer', 'form', 'admin', 'element', 'notice', 'CircleProgress'], function () {
        var $ = layui.jquery;
        var layer = layui.layer;
        var form = layui.form;
        var admin = layui.admin;
        var notice = layui.notice;
        var CircleProgress = layui.CircleProgress;

        $('#loading').html('<h2 style="padding-top:20px"><b>正在检测中<span id="loginload">.</span></b></h2>');
        var loadnum = 1;

        setInterval(function (){
            let load = '.';
            if (loadnum == 1) {
                load = '.';
                loadnum += 1;
            } else if (loadnum == 2){
                load = '..';
                loadnum += 1;
            }else{
                load = '...';
                loadnum = 1;
            }
            $('#loginload').html(load);
        },300);
        $.ajax({
            type: "POST",
            url: "<?php echo url('/Safe/check'); ?>",
            dataType: "json",
            success: function(data) {
                if (data.code == 0) {
                    var ins10 = new CircleProgress('#SF_Progress', {
                        max: 100,
                        value: 0,
                        textFormat: function (value, max) {
                            if (value == 100) {
                                return '安全';
                            } else {
                                return value + '分';
                            }
                        }
                    });
                    var htmlarr = [];
                    htmlarr.push('<div style="overflow-x: scroll;overflow-y: hidden;"><table class="layui-table sf-table" style="white-space: nowrap;" lay-even lay-skin="nob"><thead><tr><th><b>重要度</b></th><th><b>详情</b></th></tr></thead>');
                    $.each(data.data.check_msg.danger, function(){
                        htmlarr.push('<tr><td><span class="layui-btn layui-btn-sm layui-btn-danger">重要</span></td><td>'+ this +'</td></tr>');
                    });
                    $.each(data.data.check_msg.warning, function(){
                        htmlarr.push('<tr><td><span class="layui-btn layui-btn-sm layui-btn-warm">重要</span></td><td>'+ this +'</td></tr>');
                    });
                    $.each(data.data.check_msg.info, function(){
                        htmlarr.push('<tr><td><span class="layui-btn layui-btn-sm layui-btn-info">一般</span></td><td>'+ this +'</td></tr>');
                    });
                    htmlarr.push('</table></div>');
                    var html = htmlarr.join("");
                    var timer10 = setInterval(function () {
                        if(data.data.safe > ins10.value){
                            ins10.value += 1;
                            if (ins10.value == ins10.max) {
                                $('#SF_Progress').addClass('circle-progress-success');
                                $('#loading').html('<h2 style="padding-top:20px">当前安全系数：<b style="color: #00CC00">极高</b></h2>');
                                $('#sfhtml').html(html);
                                clearInterval(timer10);
                            }
                        } else {
                            if(data.data.safe>=80){
                                $('#SF_Progress').addClass('circle-progress-info');
                                $('#loading').html('<h2 style="padding-top:20px">当前安全系数：<b style="color: #43A3FB">高</b></h2>');
                                $('#sfhtml').html(html);
                                clearInterval(timer10);
                            } else if(data.data.safe>=60 && data.safe<80){
                                $('#SF_Progress').addClass('circle-progress-warning');
                                $('#loading').html('<h2 style="padding-top:20px">当前安全系数：<b style="color: #ffb800">中</b></h2>');
                                $('#sfhtml').html(html);
                                clearInterval(timer10);
                            } else {
                                $('#SF_Progress').addClass('circle-progress-danger');
                                $('#loading').html('<h2 style="padding-top:20px">当前安全系数：<b style="color: #ff5722">危</b></h2>');
                                $('#sfhtml').html(html);
                                clearInterval(timer10);
                            }
                        }
                    }, 30);
                    //clearInterval(interval1);
                }else{
                    notice.msg('服务器错误！', {icon: 2});
                }
            }
        });
    });
</script>
