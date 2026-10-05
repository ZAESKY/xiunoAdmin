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
                notice.msg(window.t('install.agree_service'), {icon: 3});
                layer.tips(window.t('install.check_option'), '#id', {
	  tips: 1
});
                return false;
                }else{
                admin.removeLoading('#divLoading', true, true);
                admin.btnLoading('[lay-filter="stepDemoFormSubmit1"]', false);
                steps.next('stepsDemoForget');
                $('#QH_title').html(window.t('install.env_check'));
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
            layer.confirm(window.t('install.env_not_compatible'), {
		btn: [window.t('common.yes'),window.t('common.no')],icon:3,closeBtn:0
		}, function(index){
		    $.ajax({
	             type: "GET",
	             url: "QH_install_ajax.php?QH=is_config",
	             dataType: "json",
	             success: function(data) {
	               if (data.code == 0) {
	               steps.next('stepsDemoForget');
	               steps.next('stepsDemoForget');
	               $('#QH_title').html(window.t('install.create_table'));
	               } else {
	               steps.next('stepsDemoForget');
	               $('#QH_title').html(window.t('install.db_info'));
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
                  url: "QH_install_ajax.php?QH=config",
                  dataType: "json",
                    success: function(data) {
                        if (data.code == 0) {
                            steps.next('stepsDemoForget');
                            steps.next('stepsDemoForget');
                            $('#QH_title').html(window.t('install.create_table'));
                            $('#mysql_install').html(data.data);
                            notice.msg(window.t('install.db_connected'), {icon: 1});
                        } else {
                            steps.next('stepsDemoForget');
                            $('#QH_title').html(window.t('install.db_info'));
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
            notice.msg(window.t('install.fill_db_info'), {icon: 3});
            return false;
            }
             $.ajax({
             type: "POST",
             url: "QH_install_ajax.php?QH=config",
             data : {host:a,port:b,user:c,pwd:d,name:e},
             dataType: "json",
             success: function(data) {
               if (data.code == 0) {
                admin.removeLoading('#divLoading3', true, true);
                admin.btnLoading('[lay-filter="stepDemoFormSubmit3"]', false);
                steps.next('stepsDemoForget');
                notice.msg(data.msg, {icon: 1});
                $('#QH_title').html(window.t('install.create_table'));
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
		    $('#QH_title').html(window.t('install.complete'));
		    admin.removeLoading('#divLoading4', true, true);
            admin.btnLoading('[lay-filter="jump_install"]', false);
            $.ajax({
	             type: "GET",
	             url: "QH_install_ajax.php?QH=put_QH_install_lock",
	             dataType: "json",
	             success: function(data) {
	               if (data.code == 0) {
	               notice.msg(window.t('install.install_success'), {icon: 1});
	               $('#QH_user').html(data.user);
	               $('#QH_pwd').html(data.pwd);
	               $('#QH_qq').html(data.qq);
	               } else {
	               notice.msg(window.t('install.lock_not_created'), {icon: 2});
	               }
	              }
	            });

        });

        form.on('submit(must_install)', function (data) {
            layer.confirm(window.t('install.clear_all_data'), {
		btn: [window.t('common.yes'),window.t('common.no')],icon:3,closeBtn:0
		}, function(index){
		    admin.showLoading('#divLoading4', 2, '.8');
            admin.btnLoading('[lay-filter="must_install"]');
		     $.ajax({
	             type: "GET",
	             url: "QH_install_ajax.php?QH=mysql",
	             dataType: "json",
	             success: function(data) {
	               if (data.code == 0) {
	                admin.removeLoading('#divLoading4', true, true);
	                admin.btnLoading('[lay-filter="must_install"]', false);
	                steps.next('stepsDemoForget');
	                notice.msg(data.msg, {icon: 1});
	                $('#QH_title').html(window.t('install.admin_info'));
	                $('#admin').html(data.data);

	               } else {
	                admin.removeLoading('#divLoading4', true, true);
	                admin.btnLoading('[lay-filter="must_install"]', false);
	                notice.msg(data.msg, {icon: 2});
	                $('[lay-filter="must_install"]').html(window.t('install.retry'));
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
             url: "QH_install_ajax.php?QH=mysql",
             dataType: "json",
             success: function(data) {
               if (data.code == 0) {
                admin.removeLoading('#divLoading4', true, true);
                admin.btnLoading('[lay-filter="mysql_install"]', false);
                steps.next('stepsDemoForget');
                notice.msg(data.msg, {icon: 1});
                $('#QH_title').html(window.t('install.admin_info'));
                $('#admin').html(data.data);

               } else {
                admin.removeLoading('#divLoading4', true, true);
                admin.btnLoading('[lay-filter="mysql_install"]', false);
                notice.msg(data.msg, {icon: 2});
               $('[lay-filter="mysql_install"]').html(window.t('install.retry'));
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
            notice.msg(window.t('validation.not_empty'), {icon: 3});
            return false;
            }
             $.ajax({
             type: "POST",
             url: "QH_install_ajax.php?QH=admin",
             data : {user:a,pwd:b,qq:c,mail:d,authcode:e,login_key:login_key,sitename:sitename},
             dataType: "json",
             success: function(data) {
               if (data.code == 0) {
                admin.removeLoading('#divLoading5', true, true);
                admin.btnLoading('[lay-filter="admin_info"]', false);
                steps.next('stepsDemoForget');
                notice.msg(data.msg, {icon: 1});
                $('#QH_title').html(window.t('install.complete'));
                $.ajax({
                 type: "GET",
                 url: "QH_install_ajax.php?QH=put_QH_install_lock",
                 dataType: "json",
                 success: function(data) {
                   if (data.code == 0) {
                       notice.msg(window.t('install.install_success'), {icon: 1})
                   } else {
                       notice.msg(window.t('install.lock_not_created'), {icon: 2});
                   }
                   }
               });
               } else {
                admin.removeLoading('#divLoading5', true, true);
                admin.btnLoading('[lay-filter="admin_info"]', false);
                notice.msg(data.msg, {icon: 2});
                $('[lay-filter="admin_info"]').html(window.t('install.retry'));
               }
        }
    });

            return false;
        });

        form.on('submit(QHindex)', function (data) {
            admin.showLoading('#divLoading6', 2, '.8');
            admin.btnLoading('[lay-filter="QHindex"]');
            window.location.href="/";
            return false;
    });

    form.on('submit(loginadmin)', function (data) {
            admin.showLoading('#divLoading6', 2, '.8');
            admin.btnLoading('[lay-filter="loginadmin"]');
             $.ajax({
             type: "GET",
             url: "QH_install_ajax.php?QH=QH_login",
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