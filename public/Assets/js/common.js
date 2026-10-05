/** EasyWeb iframe v3.1.8 date:2020-05-04 License By http://easyweb.vip */
(function (window) {
    var parentMessages = null;
    var parentLocale = null;
    try {
        if (window.parent && window.parent !== window) {
            parentMessages = window.parent.QH_I18N_MESSAGES || null;
            parentLocale = window.parent.QH_I18N_LOCALE || null;
        }
    } catch (e) {}

    var messages = window.QH_I18N_MESSAGES || parentMessages || {};
    var locale = window.QH_I18N_LOCALE || parentLocale || (window.QH_LANG && window.QH_LANG.current) || 'en-us';

    function getMessage(key) {
        var currentMessages = window.QH_I18N_MESSAGES || messages;
        if (Object.prototype.hasOwnProperty.call(currentMessages, key)) {
            return currentMessages[key];
        }
        try {
            if (window.parent && window.parent !== window) {
                var currentParentMessages = window.parent.QH_I18N_MESSAGES || parentMessages || {};
                if (Object.prototype.hasOwnProperty.call(currentParentMessages, key)) {
                    return currentParentMessages[key];
                }
            }
        } catch (e) {}
        return key;
    }

    window.QH_I18N = {
        messages: messages,
        lang: function () {
            return locale;
        },
        t: function (key, vars) {
            var text = getMessage(key);
            vars = vars || {};
            return String(text).replace(/\{:?([\w]+)\}/g, function (match, name) {
                return vars[name] === undefined ? match : vars[name];
            });
        },
        switchLang: function (lang) {
            if (window.QH_LANG) {
                window.QH_LANG.set(lang);
            }
        }
    };
    window.t = window.QH_I18N.t;
})(window);

// 全局金额输入校验：限制最大值和两位小数（原生JS，jQuery 加载前即生效）
document.addEventListener('blur', function(e){
    var el = e.target;
    if(el.type !== 'number') return;
    var max = parseFloat(el.getAttribute('max'));
    var min = parseFloat(el.getAttribute('min'));
    var step = el.getAttribute('step');
    var val = parseFloat(el.value);
    if(isNaN(val)) return;
    if(!isNaN(max) && val > max) val = max;
    if(!isNaN(min) && val < min) val = min;
    if(step === '0.01') val = Math.round(val * 100) / 100;
    if(!isNaN(val)) el.value = val;
}, true);

layui.config({  // common.js是配置layui扩展模块的目录，每个页面都需要引入
    version: '329',   // 更新组件缓存，设为true不缓存，也可以设一个固定值
    base: getProjectUrl() + 'Assets/module/',
    defaultTheme: 'theme-qh',
    closeFooter: true,
    pageTabs: false,
    cacheTab: false,
    tabAutoRefresh: false,
    navArrow: 'arrow2',
    defaultLoading: 3,
    tableName: 'QH-AUTH',
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
}).use(['layer', 'admin', 'form'], function () {
    var $ = layui.jquery;
    var layer = layui.layer;
    var admin = layui.admin;
    var form = layui.form;
    var rawModelForm = admin.modelForm;

    // The authorization center has one fixed visual identity. Persist the
    // original blue QH theme so stale theme-green/default Layui preferences
    // from older releases cannot override it on the next page load.
    admin.changeTheme('theme-qh');

    form.verify({
        required: [/[^\s]+/, t('validation.required')],
        phone: [/^1\d{10}$/, t('validation.phone')],
        email: [/^([a-zA-Z0-9_.-])+@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/, t('validation.email')],
        url: [/(^#)|(^http(s*):\/\/[^\s]+\.[^\s]+)/, t('validation.url')],
        number: function (value) {
            if (!value || isNaN(value)) {
                return t('validation.number');
            }
        },
        date: [/^(\d{4})[-\/](\d{1}|0\d{1}|1[0-2])([-\/](\d{1}|0\d{1}|[1-2][0-9]|3[0-1]))*$/, t('validation.date')],
        identity: [/(^\d{15}$)|(^\d{17}(x|X|\d)$)/, t('validation.identity')]
    });

    function translateRenderedSelects(scope) {
        var $scope = scope ? $(scope) : $(document);
        $scope.find('.layui-form-select .layui-select-title input').each(function () {
            var $input = $(this);
            var placeholder = $input.attr('placeholder');
            if (placeholder === '请选择' || placeholder === '璇烽€夋嫨') {
                $input.attr('placeholder', t('common.please_select'));
            }
        });
        $scope.find('.layui-select-none').text(t('common.no_data'));
        $scope.find('.layui-form-select dd.layui-disabled').each(function () {
            var $option = $(this);
            if ($option.text() === '没有选项' || $option.text() === '娌℃湁閫夐」') {
                $option.text(t('common.no_data'));
            }
        });
    }

    var rawFormRender = form.render;
    form.render = function () {
        var result = rawFormRender.apply(this, arguments);
        translateRenderedSelects(document);
        return result;
    };
    translateRenderedSelects(document);
    $(document).off('keyup.qhFormI18n', '.layui-form-select .layui-select-title input')
        .on('keyup.qhFormI18n', '.layui-form-select .layui-select-title input', function () {
            setTimeout(function () {
                translateRenderedSelects(document);
            }, 0);
        });

    function getSubmitScope($layer) {
        var $scope = $layer.find('form.layui-form').first();
        if ($scope.length === 0) {
            $scope = $layer.find('.layui-form').first();
        }
        return $scope.length ? $scope : $layer;
    }

    function ensureSubmitProxy($layer, filter) {
        var $scope = getSubmitScope($layer);
        var $proxy = $scope.children('.qh-layer-submit-proxy[data-filter="' + filter + '"]');
        if ($proxy.length === 0) {
            $proxy = $('<button type="button" class="layui-hide qh-layer-submit-proxy"></button>');
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
            proxyId = 'qh_layer_submit_' + new Date().getTime() + '_' + Math.floor(Math.random() * 100000);
            $proxy.attr('id', proxyId);
        }

        // Form guard: route layer footer clicks through a hidden submit proxy first.
        // This prevents layer's default btn0 handler from closing the dialog before custom validation returns false.
        $btn.removeAttr('lay-submit').removeAttr('lay-filter').attr('data-qh-submit-proxy', proxyId);
        $btn.each(function () {
            if (this.qhLayerSubmitProxyBound) {
                return;
            }
            this.qhLayerSubmitProxyBound = true;
            this.addEventListener('click', function (event) {
                var id = $(this).attr('data-qh-submit-proxy');
                var $target = id ? $('#' + id) : $();
                var layerIndex = $(this).closest('.layui-layer').attr('times');
                var ajaxNamespace = '.qhLayerSubmit' + layerIndex;
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

/**
 * Keep every Layui row toolbar on one line and size its column to the widest
 * rendered action group. Layui 2.x only accepts numeric column widths and
 * cannot derive an "auto" width from toolbar templates by itself.
 */
(function (window, document) {
    if (!window.layui || !window.MutationObserver) {
        return;
    }

    layui.use(['jquery'], function () {
        var $ = layui.jquery;
        var resizeTimer = null;

        function measureCell(cell) {
            var clone = cell.cloneNode(true);
            clone.removeAttribute('id');
            clone.setAttribute('data-qh-action-measure', '1');
            clone.style.setProperty('position', 'absolute', 'important');
            clone.style.setProperty('left', '-100000px', 'important');
            clone.style.setProperty('top', '-100000px', 'important');
            clone.style.setProperty('display', 'inline-block', 'important');
            clone.style.setProperty('visibility', 'hidden', 'important');
            clone.style.setProperty('width', 'auto', 'important');
            clone.style.setProperty('min-width', '0', 'important');
            clone.style.setProperty('max-width', 'none', 'important');
            clone.style.setProperty('height', 'auto', 'important');
            clone.style.setProperty('overflow', 'visible', 'important');
            clone.style.setProperty('white-space', 'nowrap', 'important');
            clone.style.setProperty('pointer-events', 'none', 'important');
            document.body.appendChild(clone);
            var width = Math.ceil(clone.getBoundingClientRect().width);
            document.body.removeChild(clone);
            return width;
        }

        function fitActionColumns(view) {
            var $view = $(view);
            var columns = {};

            $view.find('.layui-table-main td[data-off="true"]').each(function () {
                var key = $(this).attr('data-key');
                if (!key) {
                    return;
                }
                var cell = $(this).children('.layui-table-cell')[0];
                if (!cell) {
                    return;
                }
                var minWidth = parseInt($(this).attr('data-minwidth'), 10) || 0;
                var contentWidth = measureCell(cell);
                columns[key] = Math.max(columns[key] || 0, minWidth, contentWidth + 4, 70);
            });

            var hasActionColumn = false;
            $.each(columns, function (key, width) {
                hasActionColumn = true;
                var selector = '[data-key="' + key + '"] > .layui-table-cell';
                $view.find(selector).css('width', width + 'px');
            });

            if (hasActionColumn && $view.find('.layui-table-fixed-r td[data-off="true"]').length) {
                $view.addClass('qh-action-columns-scroll');
            }
        }

        function fitAllActionColumns() {
            $('.layui-table-view').each(function () {
                fitActionColumns(this);
            });
        }

        function scheduleFit() {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(fitAllActionColumns, 20);
        }

        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var addedNodes = mutations[i].addedNodes || [];
                for (var j = 0; j < addedNodes.length; j++) {
                    var node = addedNodes[j];
                    if (node.nodeType === 1 && node.getAttribute('data-qh-action-measure') === '1') {
                        continue;
                    }
                    scheduleFit();
                    return;
                }
            }
        });
        observer.observe(document.body, {childList: true, subtree: true});
        $(window).off('resize.qhActionColumns').on('resize.qhActionColumns', scheduleFit);
        $(scheduleFit);
    });
})(window, document);
