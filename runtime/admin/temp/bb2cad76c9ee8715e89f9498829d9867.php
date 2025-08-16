<?php /*a:3:{s:67:"D:\phpstudy_pro\WWW\www.admindev.com\app\admin\view\cdkey\list.html";i:1659575408;s:70:"D:\phpstudy_pro\WWW\www.admindev.com\app\admin\view\public\header.html";i:1659781097;s:70:"D:\phpstudy_pro\WWW\www.admindev.com\app\admin\view\public\footer.html";i:1652719209;}*/ ?>
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
<!-- 正文开始 -->
<div class="layui-fluid">
    <div class="layui-card">
        <div class="layui-card-body">
            <!-- 表格工具栏 -->
            <form class="layui-form toolbar">
                <div class="layui-form-item">
                    <div class="layui-inline">
                        <label class="layui-form-label">内&emsp;容</label>
                        <div class="layui-input-inline">
                            <input name="text" class="layui-input" placeholder="输入ID或授权内容或QQ"/>
                        </div>
                    </div>
                    <div class="layui-inline">
                        <label class="layui-form-label">状&emsp;态</label>
                        <div class="layui-input-inline">
                            <select name="status" class="ew-select-fixed">
                                <option value="">选择状态</option>
                                <option value="1">显示</option>
                                <option value="0">隐藏</option>
                            </select>
                        </div>
                    </div>
                    <div class="layui-inline layui-btn-container">&emsp;&emsp;
                        <button class="layui-btn icon-btn" lay-filter="SF_TbSearch" lay-submit>
                            <i class="layui-icon">&#xe615;</i>搜索
                        </button>
                    </div>
                </div>
                <div class="layui-form-item">
                    <div class="layui-inline">
                        <label class="layui-form-label">应&emsp;用</label>
                        <div class="layui-input-inline">
                            <select class="layui-input ew-select-fixed" lay-filter="app" lay-search>
                                <option value="">全部应用</option>
                                <?php if(is_array($app_list['data']['data']) || $app_list['data']['data'] instanceof \think\Collection || $app_list['data']['data'] instanceof \think\Paginator): $i = 0; $__LIST__ = $app_list['data']['data'];if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$res): $mod = ($i % 2 );++$i;?>
                                <option value="<?php echo htmlentities($res['id']); ?>"><?php echo htmlentities($res['name']); ?></option>
                                <?php endforeach; endif; else: echo "" ;endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
            <!-- 数据表格 -->

            <table id="SF_Table" lay-filter="SF_Table"></table>
        </div>
    </div>
</div>
<!-- 表格操作列 -->
<script type="text/html" id="SF_TbBar">
    <a class="layui-btn layui-btn-primary layui-btn-sm" lay-event="edit"><i class="layui-icon layui-icon-edit"></i>修改</a>
    <a class="layui-btn layui-btn-danger layui-btn-sm" lay-event="del"><i class="layui-icon layui-icon-delete"></i>删除</a>
</script>
<!-- 表格状态列 -->
<script type="text/html" id="SF_TbState">
    <input type="checkbox" lay-filter="SF_TbStateCk" value="{{d.id}}" lay-skin="switch"
           lay-text="已使用|未使用" {{d.status==1?'checked':''}} style="display: none;"/>
</script>
<script type="text/html" id="SF_TbPermanent_Switch">
    <input type="checkbox" lay-filter="SF_TbPermanent_SwitchCk" value="{{d.id}}" lay-skin="switch"
           lay-text="正常|封禁" {{d.permanent_switch==1?'checked':''}} style="display: none;"/>
</script>

<script type="text/html" id="SF_Tbid">
    <span id="SF_span_{{d.id}}">{{d.status==0?'<span class="layui-badge-dot layui-bg-sf-indigo" style="height: 6px;width: 6px;position: relative;top: -2px"></span></span> '+d.id:'<span class="layui-badge-dot layui-bg-red" style="height: 6px;width: 6px;position: relative;top: -2px"></span></span> '+d.id}}
</script>
<!-- 表单弹窗 -->
<script type="text/html" id="SF_EditDialog">
    <form id="SF_EditForm" lay-filter="SF_EditForm" class="layui-form model-form">
        <input name="id" type="hidden"/>
        <div class="layui-form-item">
            <label class="layui-form-label layui-form-required">所属应用:</label>
            <div class="layui-input-block">
                <select name="appid" class="layui-input ew-select-fixed" lay-filter="appid" lay-search>
                    <option value="">请选择所属的应用</option>
                    <?php if(is_array($app_list['data']['data']) || $app_list['data']['data'] instanceof \think\Collection || $app_list['data']['data'] instanceof \think\Paginator): $i = 0; $__LIST__ = $app_list['data']['data'];if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$res): $mod = ($i % 2 );++$i;?>
                    <option value="<?php echo htmlentities($res['id']); ?>"><?php echo htmlentities($res['name']); ?></option>
                    <?php endforeach; endif; else: echo "" ;endif; ?>
                </select>
            </div>
        </div>
        <div id="form_data" style="display: none">
            <div class="layui-form-item">
                <label class="layui-form-label layui-form-required">卡密类型:</label>
                <div class="layui-input-block">
                    <input type="radio" name="cdkey_type" value="auth" title="授权" lay-filter="cdkey_type">
                    <input type="radio" name="cdkey_type" value="user" title="权限" lay-filter="cdkey_type">
                    <input type="radio" name="cdkey_type" value="balance" title="余额" lay-filter="cdkey_type">
                    <input type="radio" name="cdkey_type" value="integral" title="积分" lay-filter="cdkey_type">
                </div>
            </div>
            <div id="auth_data" style="display: none">
                <div id="type" style="display: none">
                    <div class="layui-form-item">
                        <label class="layui-form-label layui-form-required">授权时间:</label>
                        <div class="layui-input-block">
                            <select id="auth_price_list" name="type" class="layui-input ew-select-fixed" lay-filter="type">
                                <option value="">请选择授权时间</option>
                            </select>
                        </div>
                    </div>
                    <div id="diy" style="display: none">
                        <div class="layui-form-item">
                            <label class="layui-form-label layui-form-required">到期时间:</label>
                            <div class="layui-input-block">
                                <input type="text" id="test" name="endtime" placeholder="请选择到期时间" class="icon-date layui-input"
                                       lay-verType="tips"/>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label layui-form-required">永久授权:</label>
                    <div class="layui-input-block">
                        <input type="checkbox" name="permanent_switch" value="1" lay-skin="switch" lay-filter="permanent_switch" checked/>
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label layui-form-required">状态:</label>
                    <div class="layui-input-block">
                        <input type="checkbox" name="auth_status" value="1" lay-skin="switch" checked/>
                    </div>
                </div>
            </div>
            <div id="user_data" style="display: none">
                <div class="layui-form-item">
                    <label class="layui-form-label layui-form-required">权限:</label>
                    <div class="layui-input-block">
                        <select id="power" name="power" class="layui-input ew-select-fixed" lay-search>
                            <option value="">请选择权限</option>
                        </select>
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label layui-form-required">用户状态:</label>
                    <div class="layui-input-block">
                        <input type="checkbox" name="user_status" lay-text="正常|封禁" value="1" lay-skin="switch" checked/>
                    </div>
                </div>
            </div>
            <div id="balance_data" style="display: none">
                <div class="layui-form-item">
                    <label class="layui-form-label layui-form-required">余额:</label>
                    <div class="layui-input-block">
                        <input type="number" name="balance" placeholder="请输入该卡密增加的余额" class="layui-input"
                               lay-verType="tips" />
                    </div>
                </div>
            </div>
            <div id="integral_data" style="display: none">
                <div class="layui-form-item">
                    <label class="layui-form-label layui-form-required">积分:</label>
                    <div class="layui-input-block">
                        <input type="number" name="integral" placeholder="请输入该卡密增加的积分" class="layui-input"
                               lay-verType="tips" />
                    </div>
                </div>
            </div>
            <div id="number" style="display: none">
                <div class="layui-form-item">
                    <label class="layui-form-label layui-form-required">生成数量:</label>
                    <div class="layui-input-block">
                        <input type="number" name="number" placeholder="请输入生成该类型卡密的数量" class="layui-input"
                               lay-verType="tips"/>
                    </div>
                </div>
            </div>
            <div id="cdkey" style="display: none">
                <div class="layui-form-item">
                    <label class="layui-form-label layui-form-required">卡密:</label>
                    <div class="layui-input-block">
                        <input type="text" name="cdkey" placeholder="卡密" class="layui-input"
                               lay-verType="tips"/>
                    </div>
                </div>
            </div>
        </div>
        <div class="layui-form-item text-right">
            <button class="layui-btn" lay-filter="SF_EditSubmit" lay-submit>保存</button>
            <button class="layui-btn layui-btn-primary" type="button" ew-event="closeDialog">取消</button>
        </div>
    </form>
</script>

<script type="text/javascript" src="/Assets/libs/layui/layui.js"></script>
<script type="text/javascript" src="/Assets/js/common.js?v=318"></script>
<!--<script type="text/javascript" src="/Assets/js/template.js"></script>-->
<script>
    layui.use(['layer', 'form', 'table', 'admin', 'notice', 'laydate'], function () {
        var $ = layui.jquery;
        var layer = layui.layer;
        var form = layui.form;
        var table = layui.table;
        var admin = layui.admin;
        var notice = layui.notice;
        var laydate = layui.laydate;

        /* 渲染表格 */
        var insTb = table.render({
            elem: '#SF_Table',
            url: '<?php echo url("/Cdkey/list"); ?>',
            method: 'post',
            page: true,
            skin: 'line',
            size: 'lg',
            toolbar: ['<p>',
                '<button lay-event="add" class="layui-btn layui-btn-sm icon-btn"><i class="layui-icon">&#xe654;</i>添加</button>&nbsp;',
                '<button lay-event="del" class="layui-btn layui-btn-sm layui-btn-danger icon-btn"><i class="layui-icon">&#xe640;</i>删除</button>',
                '</p>'].join(''),
            cellMinWidth: 100,
            request: {
                pageName: 'current_page' //页码的参数名称，默认：page
                ,limitName: 'limit' //每页数据量的参数名，默认：limit
            },
            parseData: function(res){ //res 即为原始返回的数据
                return {
                    "code": res.code, //解析接口状态
                    "msg": res.msg, //解析提示文本
                    "count": res.data.data.total, //解析数据长度
                    "data": res.data.data.data //解析数据列表
                };
            },
            cols: [[
                {type: 'checkbox', fixed: true},
                {field: 'id', title: 'ID', templet: '#SF_Tbid', sort: true, width: 80, fixed: true},
                {field: 'appName', title: '所属应用', sort: true, minWidth: 120, templet: function (d) {
                        return '<span class="layui-badge layui-badge-orange">'+d.appName+'</span>';
                    }},
                {field: 'cdkey_type', title: '卡密类型', sort: true, minWidth: 80, templet: function (d) {
                        if(d.cdkey_type == 'auth'){
                            return '<span class="layui-badge layui-badge-red">授权</span>';
                        }else if(d.cdkey_type == 'user'){
                            return '<span class="layui-badge layui-badge-blue">权限</span>';
                        }else if(d.cdkey_type == 'balance'){
                            return '<span class="layui-badge layui-badge-green">余额</span>';
                        }else if(d.cdkey_type == 'integral'){
                            return '<span class="layui-badge layui-badge-yellow">积分</span>';
                        }
                    },
                },
                {field: 'cdkey', title: '卡密', minWidth: 310},
                {field: 'use_content', title: '使用内容', sort: true, minWidth: 120},
                {field: 'userid', title: '上级ID', sort: true, minWidth: 100, templet: function (d) {
                        if(d.userid == 0){
                            return '<span class="layui-badge layui-badge-green">站长</span>';
                        }else{
                            return '<span class="layui-badge layui-badge-blue">'+d.userid+'</span>';
                        }
                    },
                },
                {field: 'addtime', title: '添加时间/使用时间', sort: true, minWidth: 290, templet: function (d) {
                        if(d.usetime == null){
                            return '<span class="layui-badge layui-badge-blue">'+d.addtime+'</span> <span class="layui-badge layui-badge-green">未使用</span>';
                        }else{
                            return '<span class="layui-badge layui-badge-blue">'+d.addtime+'</span> <span class="layui-badge layui-badge-red">'+d.usetime+'</span>';
                        }
                    }
                },
                {field: 'status', title: '状态', templet: '#SF_TbState', minWidth: 100},
                {title: '操作', toolbar: '#SF_TbBar', align: 'center', minWidth: 180}
            ]]
        });


        form.on('select(type)', function (data) {
            let diy = $("#auth_price_list option:selected").attr('diy');
            if (diy == '1') {
                $("#diy").css("display", "inherit");
            }else{
                $("#diy").css("display", "none");
            }
        });
        form.on('switch(permanent_switch)', function (data) {
            if (data.elem.checked) {
                $("#type").css("display", "none");
            }else{
                $("#type").css("display", "inherit");
            }
        });
        /* 表格搜索 */
        form.on('submit(SF_TbSearch)', function (data) {
            insTb.reload({where: data.field, page: {curr: 1}});
            return false;
        });

        form.on('select(app)', function (data) {
            if(data.value == ''){
                insTb.reload({where: {appid:data.value}, page: {curr: 1}, cols: [[{type: 'checkbox', fixed: true}, {field: 'id', title: 'ID', templet: '#SF_Tbid', sort: true, width: 80, fixed: true}]]});
            }else{
                insTb.reload({where: {appid:data.value}, page: {curr: 1}, cols: [[{type: 'checkbox', fixed: true},{field: 'id', title: 'ID', templet: '#SF_Tbid', sort: true, width: 80, fixed: true}]]});
            }
            return false;
        });
        <?php if($appid != ''): ?>
        insTb.reload({where: {appid:<?php echo htmlentities($appid); ?>}, page: {curr: 1}, cols: [[{type: 'checkbox', fixed: true},{field: 'id', title: 'ID', templet: '#SF_Tbid', sort: true, width: 80, fixed: true}]]});
        <?php endif; ?>
            /* 表格工具条点击事件 */
            table.on('tool(SF_Table)', function (obj) {
                if (obj.event === 'edit') { // 修改
                    showEditModel(obj.data);
                } else if (obj.event === 'del') { // 删除
                    doDel(obj);
                }
                laydate.render({
                    elem: '#test', //指定元素
                    mark: {
                        '0-3-15': 'SF'
                    },
                    type: 'datetime',
                    trigger: 'click'
                });
            });

            /* 表格头工具栏点击事件 */
            table.on('toolbar(SF_Table)', function (obj) {
                if (obj.event === 'add') { // 添加
                    showEditModel();
                } else if (obj.event === 'del') { // 删除
                    var checkRows = table.checkStatus('SF_Table');
                    if (checkRows.data.length === 0) {
                        notice.msg('请选择要删除的数据！', {icon: 3, audio:'1'});
                        return;
                    }
                    var ids = checkRows.data.map(function (d) {
                        return d.id;
                    });
                    console.log(ids)
                    doDel({ids: ids});
                }
                laydate.render({
                    elem: '#test', //指定元素
                    mark: {
                        '0-3-15': 'SF'
                    },
                    type: 'datetime',
                    trigger: 'click'
                });
            });

            /* 显示表单弹窗 */
            function showEditModel(mData) {
                admin.popupRight({
                    type: 1,
                    title: (mData ? '修改' : '添加') + '卡密',
                    content: $('#SF_EditDialog').html(),
                    success: function (layero, dIndex) {
                        // 回显表单数据
                        var empty = [{"cdkey_type":0,info: {"1":"","balance":"","power":"","integral":"","user_status":"","auth_status":""}}]
                        if(mData){
                            $("#form_data").css("display", "inherit");
                            $("#cdkey").css("display", "inherit");
                            $("#number").css("display", "none");
                            if (mData.cdkey_type == 'auth') {
                                notice.msg('正在获取列表中..', {icon: 4, close: true});
                                $.ajax({
                                    type: "POST",
                                    url: '<?php echo url("/Auth/getAuthPriceList"); ?>',
                                    data: {appid:mData.appid},
                                    dataType: "json",
                                    success: function(data) {
                                        notice.destroy();
                                        if (data.code == 0) {
                                            notice.msg("获取列表成功！", {icon: 1, audio:'1'});
                                            var content = '<option value="">请选择授权时间</option>';
                                            $.each(data.data,function(index, value){
                                                $.each(value,function(index, value){
                                                    if(value.diy_switch == 1){
                                                        content += '<option value="'+value.id+'" diy="1">'+value.name+'</option>';
                                                    }else{
                                                        content += '<option value="'+value.id+'" diy="0">'+value.name+'</option>';
                                                    }
                                                });
                                            });
                                            $("#auth_price_list").html(content);
                                        } else {
                                            notice.msg(data.msg, {icon: 2, audio:'1'});
                                        }
                                        form.render();
                                        if (mData.info.permanent_switch != 1) {
                                            $("#type").css("display", "inherit");
                                        }
                                        form.val('SF_EditForm', mData);
                                        form.val('SF_EditForm', mData.info);
                                    },
                                    error: function() {
                                        notice.destroy();
                                        notice.msg("服务器错误！", {icon: 2, audio:'1'});
                                    }
                                });
                                $("#auth_data").css("display", "inherit");
                                $("#user_data").css("display", "none");
                                $("#balance_data").css("display", "none");
                                $("#integral_data").css("display", "none");
                            }else if (mData.cdkey_type == 'user'){
                                notice.msg('正在获取列表中..', {icon: 4, close: true});
                                $.ajax({
                                    type: "POST",
                                    url: '<?php echo url("/Cdkey/getPowerList"); ?>',
                                    data: {"appid":mData.appid},
                                    dataType: "json",
                                    success: function(data) {
                                        notice.destroy();
                                        if (data.code == 0) {
                                            notice.msg(data.msg, {icon: 1, audio:'1'});
                                            var content = '<option value="">请选择权限</option>';
                                            $.each(data.data.data, function(index, value) {
                                                content +='<option value="'+value.id+'">'+value.name+'</option>';
                                            });
                                            $("#power").html(content);
                                        } else {
                                            notice.msg(data.msg, {icon: 2, audio:'1'});
                                        }
                                        form.render();
                                        form.val('SF_EditForm', mData);
                                        form.val('SF_EditForm', mData.info);
                                    },
                                    error: function() {
                                        notice.destroy();
                                        notice.msg("服务器错误！", {icon: 2, audio:'1'});
                                    }
                                });
                                $("#auth_data").css("display", "none");
                                $("#user_data").css("display", "inherit");
                                $("#balance_data").css("display", "none");
                                $("#integral_data").css("display", "none");
                            }else if (mData.cdkey_type == 'balance'){
                                $("#auth_data").css("display", "none");
                                $("#user_data").css("display", "none");
                                $("#balance_data").css("display", "inherit");
                                $("#integral_data").css("display", "none");
                                form.val('SF_EditForm', mData);
                                form.val('SF_EditForm', mData.info);
                            }else if (mData.cdkey_type == 'integral'){
                                $("#auth_data").css("display", "none");
                                $("#user_data").css("display", "none");
                                $("#balance_data").css("display", "none");
                                $("#integral_data").css("display", "inherit");
                                form.val('SF_EditForm', mData);
                                form.val('SF_EditForm', mData.info);
                            }
                            form.on('radio(cdkey_type)', function (data) {
                                if (data.value == 'auth') {
                                    let appid = $("select[name='appid']").val();
                                    notice.msg('正在获取列表中..', {icon: 4, close: true});
                                    $.ajax({
                                        type: "POST",
                                        url: '<?php echo url("/Auth/getAuthPriceList"); ?>',
                                        data: {appid:appid},
                                        dataType: "json",
                                        success: function(data) {
                                            notice.destroy();
                                            if (data.code == 0) {
                                                notice.msg("获取列表成功！", {icon: 1, audio:'1'});
                                                var content = '<option value="">请选择授权时间</option>';
                                                $.each(data.data,function(index, value){
                                                    $.each(value,function(index, value){
                                                        if(value.diy_switch == 1){
                                                            content += '<option value="'+value.id+'" diy="1">'+value.name+'</option>';
                                                        }else{
                                                            content += '<option value="'+value.id+'" diy="0">'+value.name+'</option>';
                                                        }
                                                    });
                                                });
                                                $("#auth_price_list").html(content);
                                            } else {
                                                notice.msg(data.msg, {icon: 2, audio:'1'});
                                            }
                                            form.render();
                                        },
                                        error: function() {
                                            notice.destroy();
                                            notice.msg("服务器错误！", {icon: 2, audio:'1'});
                                        }
                                    });
                                    $("#auth_data").css("display", "inherit");
                                    $("#user_data").css("display", "none");
                                    $("#balance_data").css("display", "none");
                                    $("#integral_data").css("display", "none");
                                }else if (data.value == 'user'){
                                    let appid = $("select[name='appid']").val();
                                    notice.msg('正在获取列表中..', {icon: 4, close: true});
                                    $.ajax({
                                        type: "POST",
                                        url: '<?php echo url("/Cdkey/getPowerList"); ?>',
                                        data: {"appid":appid},
                                        dataType: "json",
                                        success: function(data) {
                                            notice.destroy();
                                            if (data.code == 0) {
                                                notice.msg(data.msg, {icon: 1, audio:'1'});
                                                var content = '<option value="">请选择权限</option>';
                                                $.each(data.data.data, function(index, value) {
                                                    content +='<option value="'+value.id+'">'+value.name+'</option>';
                                                });
                                                $("#power").html(content);
                                            } else {
                                                notice.msg(data.msg, {icon: 2, audio:'1'});
                                            }
                                            form.render();
                                        },
                                        error: function() {
                                            notice.destroy();
                                            notice.msg("服务器错误！", {icon: 2, audio:'1'});
                                        }
                                    });
                                    $("#auth_data").css("display", "none");
                                    $("#user_data").css("display", "inherit");
                                    $("#balance_data").css("display", "none");
                                    $("#integral_data").css("display", "none");
                                }else if (data.value == 'balance'){
                                    $("#auth_data").css("display", "none");
                                    $("#user_data").css("display", "none");
                                    $("#balance_data").css("display", "inherit");
                                    $("#integral_data").css("display", "none");
                                }else if (data.value == 'integral'){
                                    $("#auth_data").css("display", "none");
                                    $("#user_data").css("display", "none");
                                    $("#balance_data").css("display", "none");
                                    $("#integral_data").css("display", "inherit");
                                }
                            });
                        }else{
                            form.on('select(appid)', function (data) {
                                if (data.value == "") {
                                    $("#form_data").css("display", "none");
                                    $("#diy").css("display", "none");
                                }else{
                                    $("#form_data").css("display","inherit");
                                }
                                $("input[name='cdkey_type']").prop('checked', false);
                                form.render('radio');
                                $("#auth_data").css("display", "none");
                                $("#user_data").css("display", "none");
                                $("#balance_data").css("display", "none");
                                $("#integral_data").css("display", "none");
                                $("#number").css("display", "none");
                            });
                            form.on('radio(cdkey_type)', function (data) {
                                if (data.value == 'auth') {
                                    let appid = $("select[name='appid']").val();
                                    notice.msg('正在获取列表中..', {icon: 4, close: true});
                                    $.ajax({
                                        type: "POST",
                                        url: '<?php echo url("/Auth/getAuthPriceList"); ?>',
                                        data: {appid:appid},
                                        dataType: "json",
                                        success: function(data) {
                                            notice.destroy();
                                            if (data.code == 0) {
                                                notice.msg("获取列表成功！", {icon: 1, audio:'1'});
                                                var content = '<option value="">请选择授权时间</option>';
                                                $.each(data.data,function(index, value){
                                                    $.each(value,function(index, value){
                                                        if(value.diy_switch == 1){
                                                            content += '<option value="'+value.id+'" diy="1">'+value.name+'</option>';
                                                        }else{
                                                            content += '<option value="'+value.id+'" diy="0">'+value.name+'</option>';
                                                        }
                                                    });
                                                });
                                                $("#auth_price_list").html(content);
                                            } else {
                                                notice.msg(data.msg, {icon: 2, audio:'1'});
                                            }
                                            form.render();
                                        },
                                        error: function() {
                                            notice.destroy();
                                            notice.msg("服务器错误！", {icon: 2, audio:'1'});
                                        }
                                    });
                                    $("#auth_data").css("display", "inherit");
                                    $("#user_data").css("display", "none");
                                    $("#balance_data").css("display", "none");
                                    $("#integral_data").css("display", "none");
                                    $("#number").css("display", "inherit");
                                }else if (data.value == 'user'){
                                    let appid = $("select[name='appid']").val();
                                    notice.msg('正在获取列表中..', {icon: 4, close: true});
                                    $.ajax({
                                        type: "POST",
                                        url: '<?php echo url("/Cdkey/getPowerList"); ?>',
                                        data: {"appid":appid},
                                        dataType: "json",
                                        success: function(data) {
                                            notice.destroy();
                                            if (data.code == 0) {
                                                notice.msg(data.msg, {icon: 1, audio:'1'});
                                                var content = '<option value="">请选择权限</option>';
                                                $.each(data.data.data, function(index, value) {
                                                    content +='<option value="'+value.id+'">'+value.name+'</option>';
                                                });
                                                $("#power").html(content);
                                            } else {
                                                notice.msg(data.msg, {icon: 2, audio:'1'});
                                            }
                                            form.render();
                                        },
                                        error: function() {
                                            notice.destroy();
                                            notice.msg("服务器错误！", {icon: 2, audio:'1'});
                                        }
                                    });
                                    $("#auth_data").css("display", "none");
                                    $("#user_data").css("display", "inherit");
                                    $("#balance_data").css("display", "none");
                                    $("#integral_data").css("display", "none");
                                    $("#number").css("display", "inherit");
                                }else if (data.value == 'balance'){
                                    $("#auth_data").css("display", "none");
                                    $("#user_data").css("display", "none");
                                    $("#balance_data").css("display", "inherit");
                                    $("#integral_data").css("display", "none");
                                    $("#number").css("display", "inherit");
                                }else if (data.value == 'integral'){
                                    $("#auth_data").css("display", "none");
                                    $("#user_data").css("display", "none");
                                    $("#balance_data").css("display", "none");
                                    $("#integral_data").css("display", "inherit");
                                    $("#number").css("display", "inherit");
                                }
                            });
                            form.render();
                            $("#form_data").css("display","none");
                            $("#cdkey").css("display", "none");
                            $("#number").css("display", "none");
                            $("#type").css("display", "none");
                            $("#diy").css("display", "none");
                        }

                        // 表单提交事件
                        form.on('submit(SF_EditSubmit)', function (data) {
                            notice.msg('正在执行中..', {icon: 4, close: true});
                            $.ajax({
                                type: "POST",
                                url: '<?php echo url("/Cdkey/edit"); ?>',
                                data: data.field,
                                dataType: "json",
                                success: function(data) {
                                    notice.destroy();
                                    if (data.code == 0) {
                                        table.reload('SF_Table');
                                        notice.msg(data.msg, {icon: 1, audio:'1'});
                                    } else {
                                        notice.msg(data.msg, {icon: 2, audio:'1'});
                                    }
                                },
                                error: function() {
                                    notice.destroy();
                                    notice.msg("服务器错误！", {icon: 2, audio:'1'});
                                }
                            });
                            return false;
                        });
                        // 禁止弹窗出现滚动条
                        $(layero).children('.layui-layer-content').css('overflow', 'visible');
                    }
                });
            }

            /* 删除 */
            function doDel(obj) {
                layer.confirm('确定要删除选中数据吗？', {
                    skin: 'layui-layer-admin',
                    icon: 3,
                    shade: .1
                }, function (i) {
                    layer.close(i);
                    notice.msg('正在执行中..', {icon: 4, close: true});
                    $.ajax({
                        type: "POST",
                        url: obj.data ? '<?php echo url("/Cdkey/drop"); ?>' : '<?php echo url("/Cdkey/batchDrop"); ?>',
                        data: {id: obj.data ? obj.data.id : obj.ids.join(',')},
                        dataType: "json",
                        success: function(data) {
                            notice.destroy();
                            if (data.code == 0) {
                                insTb.reload();
                                notice.msg(data.msg, {icon: 1, audio:'1'});
                            }else{
                                notice.msg(data.msg, {icon: 2, audio:'1'});
                            }
                        },
                        error: function() {
                            notice.destroy();
                            notice.msg("服务器错误！", {icon: 2, audio:'1'});
                        }
                    });
                });
            }

            /* 修改用户状态 */
            form.on('switch(SF_TbStateCk)', function (obj) {
                notice.msg('正在执行中..', {icon: 4, close: true});
                $.ajax({
                    type: "POST",
                    url:'<?php echo url("/Cdkey/setStatus"); ?>',
                    data: {id:obj.value,status:obj.elem.checked ? 1 : 0},
                    dataType: "json",
                    success: function(data) {
                        notice.destroy();
                        if (data.code == 0) {
                            insTb.reload();
                            notice.msg(data.msg, {icon: 1, audio:'1'});
                        }else{
                            notice.msg(data.msg, {icon: 2, audio:'1'});
                        }
                    },
                    error: function() {
                        notice.destroy();
                        notice.msg("服务器错误！", {icon: 2, audio:'1'});
                    }
                });
            });
        });
</script>