/** EasyWeb iframe v3.1.8 date:2020-05-04 License By http://easyweb.vip */
(function (window) {
    var messages = {
        'zh-cn': {
            'common.save': '保存',
            'common.cancel': '取消',
            'common.confirm': '确定',
            'common.reset': '重置',
            'common.submit': '提交',
            'common.search': '搜索',
            'common.delete': '删除',
            'common.edit': '修改',
            'common.add': '添加',
            'common.upload': '上传',
            'common.close': '关闭',
            'common.copy': '复制',
            'common.preview': '预览',
            'common.select': '选择',
            'common.remove': '移除',
            'common.loading': '正在执行中...',
            'common.loading_list': '正在获取列表中...',
            'common.list_success': '获取列表成功！',
            'common.server_error': '服务器错误！',
            'common.no_data': '暂无数据',
            'common.please_select': '请选择',
            'common.operation': '操作',
            'common.status': '状态',
            'common.add_time': '添加时间',
            'common.normal': '正常',
            'common.ban': '封禁',
            'common.show': '显示',
            'common.hide': '隐藏',
            'common.all': '全部',
            'common.enable': '启用',
            'common.disable': '禁用',
            'common.open': '开启',
            'common.closed': '关闭',
            'common.more': '更多',
            'common.home': '首页',
            'common.detail': '详情',
            'common.importance': '重要程度',
            'common.current_safety': '当前安全等级',
            'common.refresh': '刷新',
            'common.export': '导出',
            'common.print': '打印',
            'common.filter': '筛选',
            'common.export_selected': '导出选中数据',
            'common.export_current_page': '导出当前页数据',
            'common.export_all': '导出全部数据',
            'common.please_select_export': '请选择要导出的数据',
            'common.ie_export_not_supported': '不支持ie导出',
            'common.load_more': '加载更多',
            'common.no_more_data': '没有更多数据了~',
            'common.load_failed_retry': '加载失败，请重试',
            'common.select_file': '选择文件',
            'common.confirm_selection': '完成选择',
            'common.max_select': '最多只能选择{n}个',
            'common.nothing_selected': '请选择',
            'common.upload_failed': '上传失败',
            'common.no_file': '没有文件',
            'common.load_failed': '加载失败',
            'common.search_placeholder': '输入关键字按回车键搜索',
            'common.search_by_id_or_app_name': '输入 ID 或应用名称',
            'common.search_by_id_or_auth_content_or_qq': '输入 ID、授权内容或 QQ',
            'common.search_by_id_or_log_title_or_ip': '输入 ID、日志标题或 IP',
            'common.search_by_id_or_order_no': '输入 ID 或订单号',
            'common.search_by_id_or_version': '输入 ID、版本或版本号',
            'common.search_by_id_or_name': '输入 ID 或名称',
            'common.search_by_id_or_username_or_qq': '输入 ID、用户名或 QQ',
            'common.search_by_id_or_pirate_content': '输入 ID 或盗版内容',
            'common.search_by_power_name': '输入权限名称',
            'common.enter_auth_holder_qq': '请输入授权持有者 QQ',
            'common.searching': '搜索中..',
            'common.refresh_current': '刷新当前',
            'common.close_current': '关闭当前',
            'common.close_other': '关闭其他',
            'common.close_all': '关闭全部',
            'common.nice_tips': '温馨提示',
            'common.go_back': '返回上一页',
            'common.go_home': '返回首页',
            'common.home_page': '主页',
            'common.home_cannot_close': '主页不能关闭',
            'common.system_busy': '系统繁忙，请稍候再试',
            'common.invalid_id': '请选择有效的数据！',
            'common.expand': '展开',
            'common.collapse': '收起',
            'common.back': '返回',
            'common.loading_text': '加载中',
            'common.success': '成功',
            'common.failed': '失败',
            'common.yes': '确认',
            'common.no': '取消',
            'dashboard.welcome': '欢迎使用',
            'dashboard.subtitle': '综合验证授权系统',
            'install.agree_service': '请先同意服务协议',
            'install.check_option': '请勾选此项',
            'install.env_check': '环境检测',
            'install.env_not_compatible': '当前环境不兼容，是否继续安装？',
            'install.create_table': '创建数据表',
            'install.db_info': '数据库信息',
            'install.db_connected': '数据库连接成功',
            'install.fill_db_info': '请填写数据库信息',
            'install.complete': '安装完成',
            'install.install_success': '安装成功',
            'install.lock_not_created': '安装锁创建失败',
            'install.clear_all_data': '此操作会清空所有数据，确定继续吗？',
            'install.admin_info': '管理员信息',
            'install.retry': '重试',
            'validation.required': '请填写必填项！',
            'validation.not_empty': '请勿留空！',
            'validation.phone': 'SF提示您：请输入正确的手机号',
            'validation.email': 'SF提示您：邮箱格式不正确',
            'validation.url': 'SF提示您：链接格式不正确',
            'validation.number': 'SF提示您：只能填写数字',
            'validation.date': 'SF提示您：日期格式不正确',
            'validation.identity': 'SF提示您：请输入正确的身份证号',
            'validation.password': 'SF提示您：密码必须5到12位，且不能出现空格',
            'validation.equal_to': 'SF提示您：两次输入不一致',
            'validation.digits': 'SF提示您：只能输入整数',
            'validation.digits_positive': 'SF提示您：只能输入正整数',
            'validation.digits_negative': 'SF提示您：只能输入负整数',
            'validation.digits_positive_zero': 'SF提示您：只能输入正整数和0',
            'validation.digits_negative_zero': 'SF提示您：只能输入负整数和0',
            'validation.minlength': 'SF提示您：最少输入{minlength}个字符',
            'validation.maxlength': 'SF提示您：最多输入{maxlength}个字符',
            'validation.min': 'SF提示您：值不能小于{min}',
            'validation.max': 'SF提示您：值不能大于{max}',
            'validation.max_tabs': 'SF提示您：最多打开{maxTabNum}个选项卡',
            'validation.params_missing': '参数不能为空！',
            'login.logout_confirm': '确定要退出登录吗？',
            'login.logout_success': '退出登录成功！',
            'login.logging_out': '正在退出登录中..',
            'password.modify': '修改密码',
            'layout.choose_location': '选择位置',
            'layout.search_keyword': '输入关键字搜索',
            'layout.crop_image': '裁剪图片',
            'layout.done': '完成',
            'layout.crop_failed': '裁剪失败',
            'layout.close_current_tab': '关闭当前标签页',
            'layout.close_other_tabs': '关闭其它标签页',
            'layout.close_all_tabs': '关闭全部标签页',
            'confirm.delete_selected': '确定要删除选中数据吗？',
            'confirm.please_select_data': '请选择要删除的数据！',
            'confirm.not_image_preview': '这不是图片类型，可能需要下载才能预览，确定要打开吗？',
            'notify.copy_success': '复制成功！',
            'notify.copy_failed': '复制失败！',
            'notify.please_select_position': '请点击位置列表选择',
            'table_ui.loading': '加载中..',
            'table_ui.status_code_error': '返回的数据不符合规范，正确的成功状态码 ({statusName}) 应为：{statusCode}',
            'table_ui.ajax_error': '数据接口请求异常：{error}',
            'datagrid_ui.template_error': 'DataGrid Error: Template [{template}] not found',
            'treeTable.add': '添加',
            'treeTable.edit': '修改',
            'treeTable.delete': '删除',
            'treeTable.filter': '筛选',
            'treeTable.export': '导出',
            'treeTable.print': '打印',
            'treeTable.load_failed': '加载失败',
            'treeTable.no_data': '暂无数据'
        },
        'en-us': {
            'common.save': 'Save',
            'common.cancel': 'Cancel',
            'common.confirm': 'OK',
            'common.reset': 'Reset',
            'common.submit': 'Submit',
            'common.search': 'Search',
            'common.delete': 'Delete',
            'common.edit': 'Edit',
            'common.add': 'Add',
            'common.upload': 'Upload',
            'common.close': 'Close',
            'common.copy': 'Copy',
            'common.preview': 'Preview',
            'common.select': 'Select',
            'common.remove': 'Remove',
            'common.loading': 'Processing...',
            'common.loading_list': 'Loading list...',
            'common.list_success': 'List loaded successfully.',
            'common.server_error': 'Server error.',
            'common.no_data': 'No data available.',
            'common.please_select': 'Please select',
            'common.operation': 'Operation',
            'common.status': 'Status',
            'common.add_time': 'Add time',
            'common.normal': 'Normal',
            'common.ban': 'Banned',
            'common.show': 'Show',
            'common.hide': 'Hide',
            'common.all': 'All',
            'common.enable': 'Enable',
            'common.disable': 'Disable',
            'common.open': 'Open',
            'common.closed': 'Closed',
            'common.more': 'More',
            'common.home': 'Home',
            'common.detail': 'Details',
            'common.importance': 'Importance',
            'common.current_safety': 'Current safety level',
            'common.refresh': 'Refresh',
            'common.export': 'Export',
            'common.print': 'Print',
            'common.filter': 'Filter',
            'common.export_selected': 'Export selected',
            'common.export_current_page': 'Export current page',
            'common.export_all': 'Export all',
            'common.please_select_export': 'Please select data to export',
            'common.ie_export_not_supported': 'IE export not supported',
            'common.load_more': 'Load more',
            'common.no_more_data': 'No more data.',
            'common.load_failed_retry': 'Failed to load. Please retry.',
            'common.select_file': 'Select file',
            'common.confirm_selection': 'Confirm selection',
            'common.max_select': 'Max {n} items allowed.',
            'common.nothing_selected': 'Please select',
            'common.upload_failed': 'Upload failed',
            'common.no_file': 'No file',
            'common.load_failed': 'Failed to load',
            'common.search_placeholder': 'Enter keyword and press Enter',
            'common.search_by_id_or_app_name': 'Enter ID or app name',
            'common.search_by_id_or_auth_content_or_qq': 'Enter ID, auth content, or QQ',
            'common.search_by_id_or_log_title_or_ip': 'Enter ID, log title, or IP',
            'common.search_by_id_or_order_no': 'Enter ID or order number',
            'common.search_by_id_or_version': 'Enter ID, version, or version number',
            'common.search_by_id_or_name': 'Enter ID or name',
            'common.search_by_id_or_username_or_qq': 'Enter ID, username or QQ',
            'common.search_by_id_or_pirate_content': 'Enter ID or pirate content',
            'common.search_by_power_name': 'Enter permission name',
            'common.enter_auth_holder_qq': 'Enter authorization holder QQ',
            'common.searching': 'Searching...',
            'common.refresh_current': 'Refresh current',
            'common.close_current': 'Close current',
            'common.close_other': 'Close others',
            'common.close_all': 'Close all',
            'common.nice_tips': 'Tips',
            'common.go_back': 'Go back',
            'common.go_home': 'Go home',
            'common.home_page': 'Home',
            'common.home_cannot_close': 'Home page cannot be closed.',
            'common.system_busy': 'System is busy. Please try again later.',
            'common.invalid_id': 'Please select valid data.',
            'common.expand': 'Expand',
            'common.collapse': 'Collapse',
            'common.back': 'Back',
            'common.loading_text': 'Loading',
            'common.success': 'Success',
            'common.failed': 'Failed',
            'common.yes': 'Yes',
            'common.no': 'No',
            'dashboard.welcome': 'Welcome',
            'dashboard.subtitle': 'Integrated Verification Authorization System',
            'install.agree_service': 'Please agree to the service agreement first.',
            'install.check_option': 'Please check this option.',
            'install.env_check': 'Environment Check',
            'install.env_not_compatible': 'The current environment is incompatible. Continue installation?',
            'install.create_table': 'Create Tables',
            'install.db_info': 'Database Info',
            'install.db_connected': 'Database connected successfully.',
            'install.fill_db_info': 'Please fill in database information.',
            'install.complete': 'Installation Complete',
            'install.install_success': 'Installation successful.',
            'install.lock_not_created': 'Failed to create install lock.',
            'install.clear_all_data': 'This will clear all data. Continue?',
            'install.admin_info': 'Admin Info',
            'install.retry': 'Retry',
            'validation.required': 'Please fill in required fields.',
            'validation.not_empty': 'This field cannot be empty.',
            'validation.phone': 'Please enter a valid phone number.',
            'validation.email': 'Invalid email format.',
            'validation.url': 'Invalid URL format.',
            'validation.number': 'Only numbers are allowed.',
            'validation.date': 'Invalid date format.',
            'validation.identity': 'Please enter a valid ID number.',
            'validation.password': 'Password must be 5-12 characters without spaces.',
            'validation.equal_to': 'The two entries do not match.',
            'validation.digits': 'Only integers are allowed.',
            'validation.digits_positive': 'Only positive integers are allowed.',
            'validation.digits_negative': 'Only negative integers are allowed.',
            'validation.digits_positive_zero': 'Only positive integers and zero are allowed.',
            'validation.digits_negative_zero': 'Only negative integers and zero are allowed.',
            'validation.minlength': 'At least {minlength} characters required.',
            'validation.maxlength': 'At most {maxlength} characters allowed.',
            'validation.min': 'Value cannot be less than {min}.',
            'validation.max': 'Value cannot be greater than {max}.',
            'validation.max_tabs': 'Max {maxTabNum} tabs allowed.',
            'validation.params_missing': 'Parameters cannot be empty.',
            'login.logout_confirm': 'Are you sure you want to log out?',
            'login.logout_success': 'Logged out successfully.',
            'login.logging_out': 'Logging out...',
            'password.modify': 'Change Password',
            'layout.choose_location': 'Choose Location',
            'layout.search_keyword': 'Search by keyword',
            'layout.crop_image': 'Crop Image',
            'layout.done': 'Done',
            'layout.crop_failed': 'Crop failed',
            'layout.close_current_tab': 'Close current tab',
            'layout.close_other_tabs': 'Close other tabs',
            'layout.close_all_tabs': 'Close all tabs',
            'confirm.delete_selected': 'Are you sure you want to delete the selected data?',
            'confirm.please_select_data': 'Please select data to delete.',
            'confirm.not_image_preview': 'This is not an image. Preview may download it. Open anyway?',
            'notify.copy_success': 'Copied successfully.',
            'notify.copy_failed': 'Copy failed.',
            'notify.please_select_position': 'Please select a location from the list.',
            'table_ui.loading': 'Loading...',
            'table_ui.status_code_error': 'Response does not match specification. Expected status code ({statusName}): {statusCode}',
            'table_ui.ajax_error': 'Request error: {error}',
            'datagrid_ui.template_error': 'DataGrid Error: Template [{template}] not found',
            'treeTable.add': 'Add',
            'treeTable.edit': 'Edit',
            'treeTable.delete': 'Delete',
            'treeTable.filter': 'Filter',
            'treeTable.export': 'Export',
            'treeTable.print': 'Print',
            'treeTable.load_failed': 'Failed to load',
            'treeTable.no_data': 'No data'
        }
    };

    function getBrowserLang() {
        if (navigator.language) {
            var lang = navigator.language.toLowerCase();
            if (lang.indexOf('zh') === 0) return 'zh-cn';
            if (lang.indexOf('en') === 0) return 'en-us';
        }
        return 'en-us';
    }

    function getCookieLang() {
        var match = document.cookie.match(/(?:^|;\s*)think_lang=([^;]+)/);
        return match ? decodeURIComponent(match[1]).toLowerCase() : null;
    }

    function setCookieLang(lang) {
        document.cookie = 'think_lang=' + encodeURIComponent(lang) + ';path=/;max-age=' + (365 * 24 * 60 * 60);
    }

    function getLang() {
        var cookie = getCookieLang();
        if (cookie && (cookie === 'zh-cn' || cookie === 'en-us')) return cookie;
        var browser = getBrowserLang();
        setCookieLang(browser);
        return browser;
    }

    window.SF_I18N = {
        messages: messages,
        lang: getLang,
        t: function (key, vars) {
            var lang = getLang();
            var text = (messages[lang] && messages[lang][key]) ? messages[lang][key] : ((messages['en-us'] && messages['en-us'][key]) ? messages['en-us'][key] : key);
            vars = vars || {};
            return text.replace(/\{(\w+)\}/g, function (match, name) {
                return vars[name] === undefined ? match : vars[name];
            });
        },
        switchLang: function (lang) {
            if (lang === 'zh-cn' || lang === 'en-us') {
                setCookieLang(lang);
                location.reload();
            }
        }
    };
    window.t = window.SF_I18N.t;
})(window);

layui.config({  // common.js是配置layui扩展模块的目录，每个页面都需要引入
    version: '325',   // 更新组件缓存，设为true不缓存，也可以设一个固定值
    base: getProjectUrl() + 'Assets/module/',
    defaultTheme: 'theme-sf',
    closeFooter: true,
    pageTabs: false,
    cacheTab: false,
    tabAutoRefresh: false,
    navArrow: 'arrow2',
    defaultLoading: 3,
    tableName: 'SF-AUTH',
}).extend({
    steps: 'steps/steps',
    notice: 'notice/notice',
    cascader: 'cascader/cascader',
    dropdown: 'dropdown/dropdown',
    fileChoose: 'fileChoose/fileChoose',
    Split: 'Split/Split',
    Cropper: 'Cropper/Cropper',
    tagsInput: 'tagsInput/tagsInput',
    citypicker: 'city-picker/city-picker',
    introJs: 'introJs/introJs',
    zTree: 'zTree/zTree'
}).use(['layer', 'admin'], function () {
    var $ = layui.jquery;
    var layer = layui.layer;
    var admin = layui.admin;
    var rawModelForm = admin.modelForm;

    function getSubmitScope($layer) {
        var $scope = $layer.find('form.layui-form').first();
        if ($scope.length === 0) {
            $scope = $layer.find('.layui-form').first();
        }
        return $scope.length ? $scope : $layer;
    }

    function ensureSubmitProxy($layer, filter) {
        var $scope = getSubmitScope($layer);
        var $proxy = $scope.children('.sf-layer-submit-proxy[data-filter="' + filter + '"]');
        if ($proxy.length === 0) {
            $proxy = $('<button type="button" class="layui-hide sf-layer-submit-proxy"></button>');
            $scope.append($proxy);
        }
        $proxy.attr({
            'lay-submit': '',
            'lay-filter': filter,
            'data-filter': filter
        });
        return $proxy;
    }

    function bindLayerSubmitProxy($layer, filter) {
        var $btn = $layer.find('.layui-layer-btn .layui-layer-btn0');
        if ($btn.length === 0) {
            return;
        }
        var $proxy = ensureSubmitProxy($layer, filter);
        var proxyId = $proxy.attr('id');
        if (!proxyId) {
            proxyId = 'sf_layer_submit_' + new Date().getTime() + '_' + Math.floor(Math.random() * 100000);
            $proxy.attr('id', proxyId);
        }

        // Form guard: route layer footer clicks through a hidden submit proxy first.
        // This prevents layer's default btn0 handler from closing the dialog before custom validation returns false.
        $btn.removeAttr('lay-submit').removeAttr('lay-filter').attr('data-sf-submit-proxy', proxyId);
        $btn.each(function () {
            if (this.sfLayerSubmitProxyBound) {
                return;
            }
            this.sfLayerSubmitProxyBound = true;
            this.addEventListener('click', function (event) {
                var id = $(this).attr('data-sf-submit-proxy');
                var $target = id ? $('#' + id) : $();
                var layerIndex = $(this).closest('.layui-layer').attr('times');
                var ajaxNamespace = '.sfLayerSubmit' + layerIndex;
                if ($target.length === 0) {
                    return;
                }
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                $(document).off(ajaxNamespace).one('ajaxSuccess' + ajaxNamespace, function (ajaxEvent, xhr, settings, response) {
                    var result = response || xhr.responseJSON;
                    if (!result && xhr.responseText) {
                        try {
                            result = JSON.parse(xhr.responseText);
                        } catch (e) {}
                    }
                    if (result && (result.code === 0 || result.code === '0')) {
                        layer.close(layerIndex);
                    }
                    $(document).off(ajaxNamespace);
                });
                setTimeout(function () {
                    $(document).off(ajaxNamespace);
                }, 10000);
                $target.trigger('click');
                return false;
            }, true);
        });
    }

    admin.modelForm = function (elem, submitFilter, formFilter) {
        rawModelForm.call(admin, elem, submitFilter, formFilter);
        bindLayerSubmitProxy($(elem), submitFilter);
    };

});

/** 获取当前项目的根路径，通过获取layui.js全路径截取assets之前的地址 */
function getProjectUrl() {
    var layuiDir = layui.cache.dir;
    if (!layuiDir) {
        var js = document.scripts, last = js.length - 1, src;
        for (var i = last; i > 0; i--) {
            if (js[i].readyState === 'interactive') {
                src = js[i].src;
                break;
            }
        }
        var jsPath = src || js[last].src;
        layuiDir = jsPath.substring(0, jsPath.lastIndexOf('/') + 1);
    }
    return layuiDir.substring(0, layuiDir.indexOf('Assets'));
}
