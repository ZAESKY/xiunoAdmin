/** EasyWeb iframe v3.1.8 date:2020-05-04 License By http://easyweb.vip */
(function (window) {
    var messages = {
        'zh-cn': {
            'common.save': '保存',
            'common.cancel': '取消',
            'common.confirm': '确定',
            'common.loading': '正在执行中...',
            'common.loading_list': '正在获取列表中...',
            'common.list_success': '获取列表成功！',
            'common.server_error': '服务器错误！',
            'validation.required': '请填写必填项！'
        },
        'en-us': {
            'common.save': 'Save',
            'common.cancel': 'Cancel',
            'common.confirm': 'OK',
            'common.loading': 'Processing...',
            'common.loading_list': 'Loading list...',
            'common.list_success': 'List loaded successfully.',
            'common.server_error': 'Server error.',
            'validation.required': 'Please fill in required fields.'
        }
    };

    function getLang() {
        var match = document.cookie.match(/(?:^|;\s*)think_lang=([^;]+)/);
        return decodeURIComponent(match ? match[1] : 'zh-cn').toLowerCase();
    }

    // Lightweight frontend i18n entry. Pages can call t('common.save') without a heavy framework.
    window.SF_I18N = {
        messages: messages,
        lang: getLang,
        t: function (key, vars) {
            var lang = getLang();
            var text = (messages[lang] && messages[lang][key]) || messages['zh-cn'][key] || key;
            vars = vars || {};
            return text.replace(/\{(\w+)\}/g, function (match, name) {
                return vars[name] === undefined ? match : vars[name];
            });
        }
    };
    window.t = window.SF_I18N.t;
})(window);

layui.config({  // common.js是配置layui扩展模块的目录，每个页面都需要引入
    version: '320',   // 更新组件缓存，设为true不缓存，也可以设一个固定值
    base: getProjectUrl() + 'Assets/module/',
    defaultTheme: 'theme-sf',
    closeFooter: true,
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
    var allowedThemes = ['theme-sf', 'theme-dark'];
    var rawChangeTheme = admin.changeTheme;
    var rawModelForm = admin.modelForm;

    // Theme guard: legacy cached color themes are no longer supported.
    admin.changeTheme = function (theme, win, notSave, recursive) {
        theme = allowedThemes.indexOf(theme) === -1 ? 'theme-sf' : theme;
        return rawChangeTheme.call(admin, theme, win, notSave, recursive);
    };

    if (allowedThemes.indexOf(layui.cache.defaultTheme) === -1) {
        admin.changeTheme('theme-sf');
    }

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
