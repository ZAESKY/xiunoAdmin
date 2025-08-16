layui.use(['layer', 'steps', 'form', 'admin', 'formX', 'notice'], function () {
        var $ = layui.jquery;
        var layer = layui.layer;
        var steps = layui.steps;
        var form = layui.form;
        var admin = layui.admin;
        var formX = layui.formX;
        var notice = layui.notice;
       
        // 填写手机号
        form.on('submit(stepDemoFormSubmit1)', function (data) {
            admin.showLoading('#divLoading', 2, '.8');
            admin.btnLoading('[lay-filter="stepDemoFormSubmit1"]');
            setTimeout(function () {
                 var chk_value = [];
                $('input[name="agreen"]:checked').each(function () {
                chk_value.push($(this).val());
                });
                if (chk_value == ""){
                admin.removeLoading('#divLoading', true, true);
                admin.btnLoading('[lay-filter="stepDemoFormSubmit1"]', false);
                notice.msg('请同意服务协议！', {icon: 3});
                layer.tips('请勾选该选项', '#id', {
  tips: 1
});
                return false;
                }else{
                admin.removeLoading('#divLoading', true, true);
                admin.btnLoading('[lay-filter="stepDemoFormSubmit1"]', false);
                steps.next('stepsDemoForget');  
                $('#SF_title').html('环境检测');
                }
            }, 600);
            return false;
        });

        // 获取验证码
        form.on('submit(stepDemoFormSubmit2)', function (data) {
            admin.showLoading('#divLoading2', 2, '.8');
            admin.btnLoading('[lay-filter="stepDemoFormSubmit2"]');
            var a = $("input[name='phpver']").val();
            var b = $("input[name='hq']").val();
            var c = $("input[name='file']").val();
            var d = $("input[name='session']").val();
            var e = $("input[name='mkdir']").val();
            var f = $("input[name='put']").val();
             
            if(a+b+c+d+e+f != '111111'){
            admin.removeLoading('#divLoading2', true, true);
            admin.btnLoading('[lay-filter="stepDemoFormSubmit2"]', false);
            layer.confirm('当前环境可能不适合本程序运行，是否继续安装？', {
		btn: ['是','否'],icon:3,closeBtn:0
		}, function(index){
		    $.ajax({
             type: "GET",
             url: "SF_install_ajax.php?SF=is_config",
             dataType: "json",
             success: function(data) {
               if (data.code == 0) {
               steps.next('stepsDemoForget');
               steps.next('stepsDemoForget');
               $('#SF_title').html('创建数据表'); 
               } else {
               steps.next('stepsDemoForget');
               $('#SF_title').html('填写数据库信息'); 
               }
             }
            });
            layer.close(index);
		}, function(){
			}); 
			return false;
            }else{
            setTimeout(function () {
                admin.removeLoading('#divLoading2', true, true);
                admin.btnLoading('[lay-filter="stepDemoFormSubmit2"]', false);
                $.ajax({
                  type: "GET",
                  url: "SF_install_ajax.php?SF=config",
                  dataType: "json",
                    success: function(data) {
                        if (data.code == 0) {
                            steps.next('stepsDemoForget');
                            steps.next('stepsDemoForget');
                            $('#SF_title').html('创建数据表');
                            $('#mysql_install').html(data.data);
                            notice.msg('连接数据库成功，已自动为您跳过填写数据库步骤！', {icon: 1});
                        } else {
                            steps.next('stepsDemoForget');
                            $('#SF_title').html('填写数据库信息'); 
                        }
                    }
                });
            }, 600);
            return false;
            }
        });

        
        form.on('submit(stepDemoFormSubmit3)', function (data) {
            admin.showLoading('#divLoading3', 2, '.8');
            admin.btnLoading('[lay-filter="stepDemoFormSubmit3"]');
            var a = $("input[name='db_host']").val();
            var b = $("input[name='db_port']").val();
            var c = $("input[name='db_user']").val();
            var d = $("input[name='db_pwd']").val();
            var e = $("input[name='db_name']").val();
            if(a=='' || b=='' || c=='' || d=='' || e==''){
            admin.removeLoading('#divLoading3', true, true);
            admin.btnLoading('[lay-filter="stepDemoFormSubmit3"]', false);
            notice.msg('请填写完整的数据库信息！', {icon: 3});  
            return false;
            }
             $.ajax({
             type: "POST",
             url: "SF_install_ajax.php?SF=config",
             data : {host:a,port:b,user:c,pwd:d,name:e},
             dataType: "json",
             success: function(data) {
               if (data.code == 0) {
                admin.removeLoading('#divLoading3', true, true);
                admin.btnLoading('[lay-filter="stepDemoFormSubmit3"]', false);
                steps.next('stepsDemoForget');
                notice.msg(data.msg, {icon: 1});
                $('#SF_title').html('创建数据表');
                $('#mysql_install').html(data.data);
		        
               } else {
                admin.removeLoading('#divLoading3', true, true);
                admin.btnLoading('[lay-filter="stepDemoFormSubmit3"]', false);
                notice.msg(data.msg, {icon: 2});
               }
        }
    });
            
            return false;
        });
        
        form.on('submit(jump_install)', function (data) {
		    admin.showLoading('#divLoading4', 2, '.8');
            admin.btnLoading('[lay-filter="jump_install"]');
		    steps.next('stepsDemoForget');
		    steps.next('stepsDemoForget');
		    $('#SF_title').html('完成安装');
		    admin.removeLoading('#divLoading4', true, true);
            admin.btnLoading('[lay-filter="jump_install"]', false);
            $.ajax({
             type: "GET",
             url: "SF_install_ajax.php?SF=put_SF_install_lock",
             dataType: "json",
             success: function(data) {
               if (data.code == 0) {
               notice.msg('恭喜您，安装程序成功！', {icon: 1});
               $('#SF_user').html(data.user);
               $('#SF_pwd').html(data.pwd);
               $('#SF_qq').html(data.qq);
               } else {
               notice.msg('安装锁未创建成功，请您手动在install目录下创建SF_auth.lock！', {icon: 2});
               }
              }
            });
            
        });
    
        form.on('submit(must_install)', function (data) {
            layer.confirm('全新安装将会清空所有数据，是否继续？', {
		btn: ['是','否'],icon:3,closeBtn:0
		}, function(index){
		    admin.showLoading('#divLoading4', 2, '.8');
            admin.btnLoading('[lay-filter="must_install"]');
		     $.ajax({
             type: "GET",
             url: "SF_install_ajax.php?SF=mysql",
             dataType: "json",
             success: function(data) {
               if (data.code == 0) {
                admin.removeLoading('#divLoading4', true, true);
                admin.btnLoading('[lay-filter="must_install"]', false);
                steps.next('stepsDemoForget');
                notice.msg(data.msg, {icon: 1});
                $('#SF_title').html('填写管理员信息');
                $('#admin').html(data.data);
		
               } else {
                admin.removeLoading('#divLoading4', true, true);
                admin.btnLoading('[lay-filter="must_install"]', false);
                notice.msg(data.msg, {icon: 2});
                $('[lay-filter="must_install"]').html('点击重试');
               }
        }
    });
            layer.close(index);
		}, function(){
			}); 
           return false; 
        });   
        form.on('submit(mysql_install)', function (data) {
            admin.showLoading('#divLoading4', 2, '.8');
            admin.btnLoading('[lay-filter="mysql_install"]');
             $.ajax({
             type: "GET",
             url: "SF_install_ajax.php?SF=mysql",
             dataType: "json",
             success: function(data) {
               if (data.code == 0) {
                admin.removeLoading('#divLoading4', true, true);
                admin.btnLoading('[lay-filter="mysql_install"]', false);
                steps.next('stepsDemoForget');
                notice.msg(data.msg, {icon: 1});
                $('#SF_title').html('填写管理员信息');
                $('#admin').html(data.data);
		
               } else {
                admin.removeLoading('#divLoading4', true, true);
                admin.btnLoading('[lay-filter="mysql_install"]', false);
                notice.msg(data.msg, {icon: 2});
               $('[lay-filter="mysql_install"]').html('点击重试');
               }
        }
    });
            
            return false;
        });
        
        form.on('submit(admin_info)', function (data) {
            admin.showLoading('#divLoading5', 2, '.8');
            admin.btnLoading('[lay-filter="admin_info"]');
            var a = $("input[name='user']").val();
            var b = $("input[name='pwd']").val();
            var c = $("input[name='qq']").val();
            var d = $("input[name='mail']").val();
            var e = $("input[name='authcode']").val();
            var login_key = $("input[name='login_key']").val();
            var sitename = $("input[name='sitename']").val();
            if(a=='' || b=='' || c=='' || d=='' || e=='' || login_key=='' || sitename==''){
            admin.removeLoading('#divLoading5', true, true);
            admin.btnLoading('[lay-filter="admin_info"]', false);
            notice.msg('请勿留空！', {icon: 3});  
            return false;
            }
             $.ajax({
             type: "POST",
             url: "SF_install_ajax.php?SF=admin",
             data : {user:a,pwd:b,qq:c,mail:d,authcode:e,login_key:login_key,sitename:sitename},
             dataType: "json",
             success: function(data) {
               if (data.code == 0) {
                admin.removeLoading('#divLoading5', true, true);
                admin.btnLoading('[lay-filter="admin_info"]', false);
                steps.next('stepsDemoForget');
                notice.msg(data.msg, {icon: 1});
                $('#SF_title').html('完成安装');
                $.ajax({
                 type: "GET",
                 url: "SF_install_ajax.php?SF=put_SF_install_lock",
                 dataType: "json",
                 success: function(data) {
                   if (data.code == 0) {
                       notice.msg('恭喜您，安装程序成功！', {icon: 1})
                   } else {
                       notice.msg('安装锁未创建成功，请您手动在install目录下创建SF_auth.lock！', {icon: 2});
                   } 
                   }
               });
               } else {
                admin.removeLoading('#divLoading5', true, true);
                admin.btnLoading('[lay-filter="admin_info"]', false);
                notice.msg(data.msg, {icon: 2});
                $('[lay-filter="admin_info"]').html('点击重试');
               }
        }
    });
            
            return false;
        });
        
        form.on('submit(SFindex)', function (data) {
            admin.showLoading('#divLoading6', 2, '.8');
            admin.btnLoading('[lay-filter="SFindex"]');
            window.location.href="/";
            return false;
    });
    
    form.on('submit(loginadmin)', function (data) {
            admin.showLoading('#divLoading6', 2, '.8');
            admin.btnLoading('[lay-filter="loginadmin"]');
             $.ajax({
             type: "GET",
             url: "SF_install_ajax.php?SF=SF_login",
             dataType: "json",
             success: function(data) {
               if (data.code == 0) {
                window.location.href=data.url;
               } else {
                window.location.href="/admin";
               }
             }
       });
            
            return false;
    });


    });