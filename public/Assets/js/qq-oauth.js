(function (window, document) {
    'use strict';

    var active = {};
    var STATUS_URL = '/api.php/Social/status';
    var POLL_INTERVAL_MS = 800;
    var POPUP_CLOSE_GRACE_MS = 8000;
    var COMPLETION_RESULT_GRACE_MS = 15000;
    var RESUME_RESULT_GRACE_MS = 15000;

    function encode(data) {
        var parts = [];
        Object.keys(data || {}).forEach(function (key) {
            if (data[key] === undefined || data[key] === null) return;
            parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(String(data[key])));
        });
        return parts.join('&');
    }

    function request(method, url, data, done, failed) {
        var xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        if (method === 'POST') xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            if (xhr.status < 200 || xhr.status >= 300) {
                failed(new Error('HTTP ' + xhr.status));
                return;
            }
            try {
                done(JSON.parse(xhr.responseText));
            } catch (error) {
                failed(error);
            }
        };
        xhr.onerror = function () { failed(new Error('network')); };
        xhr.send(method === 'POST' ? encode(data) : null);
    }

    function popupWindow() {
        var width = Math.min(760, Math.max(520, window.screen.availWidth - 80));
        var height = Math.min(760, Math.max(620, window.screen.availHeight - 100));
        var left = Math.max(0, Math.round((window.screen.availWidth - width) / 2));
        var top = Math.max(0, Math.round((window.screen.availHeight - height) / 2));
        return window.open('about:blank', 'qh_qq_oauth', 'popup=yes,width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes');
    }

    function stop(flowId) {
        var item = active[flowId];
        if (!item) return;
        if (item.timer) window.clearInterval(item.timer);
        if (item.timeout) window.clearTimeout(item.timeout);
        delete active[flowId];
    }

    function failFlow(flowId, message) {
        var item = active[flowId];
        if (!item) return;
        stop(flowId);
        if (item.popup && !item.popup.closed) item.popup.close();
        item.onError(message);
    }

    function popupIsClosed(item) {
        return !!(item.popup && item.popup.closed);
    }

    function closedResultDeadline(item) {
        if (item.completionNotifiedAt > 0) {
            return item.completionNotifiedAt + COMPLETION_RESULT_GRACE_MS;
        }
        return item.popupClosedAt > 0 ? item.popupClosedAt + POPUP_CLOSE_GRACE_MS : 0;
    }

    function finishIncompleteFlow(flowId) {
        var item = active[flowId];
        if (!item || item.reading) return;
        var now = Date.now();
        var deadline = item.resuming
            ? item.startedAt + RESUME_RESULT_GRACE_MS
            : closedResultDeadline(item);
        if (!deadline || now < deadline) return;
        var message = 'QQ 授权窗口已关闭，操作已取消';
        if (item.lastReadFailed) {
            message = 'QQ 授权结果查询失败，请检查网络后重试';
        } else if (item.resuming || item.completionNotifiedAt > 0) {
            message = 'QQ 授权结果同步超时，请刷新页面后重试';
        }
        failFlow(flowId, message);
    }

    function readResult(flowId) {
        var item = active[flowId];
        if (!item) return;
        if (item.reading) return;
        item.reading = true;
        request('GET', STATUS_URL + '?flow_id=' + encodeURIComponent(flowId), null, function (response) {
            item = active[flowId];
            if (!item) return;
            item.reading = false;
            item.lastReadFailed = false;
            if (response && response.code === 0 && response.data && response.data.completed) {
                stop(flowId);
                if (item.popup && !item.popup.closed) item.popup.close();
                item.onResult(response.data.result || {code: -1, msg: 'QQ 授权结果异常'});
                return;
            }
            if (!response || response.code !== 0) item.lastReadFailed = true;
            finishIncompleteFlow(flowId);
        }, function () {
            item = active[flowId];
            if (!item) return;
            item.reading = false;
            item.lastReadFailed = true;
            finishIncompleteFlow(flowId);
        });
    }

    function watch(flowId, popup, onResult, onError, resuming) {
        active[flowId] = {
            popup: popup,
            onResult: onResult,
            onError: onError,
            reading: false,
            lastReadFailed: false,
            popupClosedAt: 0,
            completionNotified: false,
            completionNotifiedAt: 0,
            resuming: !!resuming,
            startedAt: Date.now(),
            timer: window.setInterval(function () {
                var item = active[flowId];
                if (!item) return;
                if (item.resuming) {
                    readResult(flowId);
                    finishIncompleteFlow(flowId);
                    return;
                }
                if (!popupIsClosed(item)) return;
                if (!item.popupClosedAt) item.popupClosedAt = Date.now();
                readResult(flowId);
                finishIncompleteFlow(flowId);
            }, POLL_INTERVAL_MS),
            timeout: window.setTimeout(function () {
                var item = active[flowId];
                if (!item) return;
                stop(flowId);
                if (item.popup && !item.popup.closed) item.popup.close();
                item.onError('QQ 授权已超时，请重新操作');
            }, 10 * 60 * 1000)
        };
    }

    function start(options) {
        options = options || {};
        var onResult = typeof options.onResult === 'function' ? options.onResult : function () {};
        var onError = typeof options.onError === 'function' ? options.onError : function () {};
        var popup = popupWindow();
        var popupWasOpened = !!popup;
        if (popup) {
            try {
                popup.document.write('<!doctype html><meta charset="utf-8"><title>QQ授权</title><body style="font-family:sans-serif;text-align:center;padding-top:70px;color:#667085">正在连接 QQ 互联…</body>');
            } catch (ignore) {}
        }
        request('POST', options.url || '/api.php/Social/login', options.data || {}, function (response) {
            if (!response || response.code !== 0 || !response.data || !response.data.url) {
                if (popup && !popup.closed) popup.close();
                onError(response && response.msg ? response.msg : '无法发起 QQ 授权');
                return;
            }
            if (!response.data.popup_enabled) {
                if (popup && !popup.closed) popup.close();
                window.location.href = response.data.url;
                return;
            }
            var flowId = String(response.data.flow_id || '');
            if (!/^[a-f0-9]{64}$/.test(flowId)) {
                if (popup && !popup.closed) popup.close();
                onError('QQ 授权流程标识无效');
                return;
            }
            watch(flowId, popup, onResult, onError, false);
            if (popup && !popup.closed) {
                popup.location.replace(response.data.url);
                popup.focus();
            } else if (popupWasOpened) {
                failFlow(flowId, 'QQ 授权窗口已关闭，操作已取消');
            } else {
                stop(flowId);
                window.location.href = response.data.url;
            }
        }, function () {
            if (popup && !popup.closed) popup.close();
            onError('QQ 授权服务连接失败，请稍后重试');
        });
    }

    function resume(onResult, onError) {
        var params = new URLSearchParams(window.location.search);
        var flowId = params.get('qq_oauth_flow') || '';
        if (!/^[a-f0-9]{64}$/.test(flowId)) return false;
        params.delete('qq_oauth_flow');
        var clean = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + window.location.hash;
        if (window.history && window.history.replaceState) window.history.replaceState(null, document.title, clean);
        watch(flowId, null, typeof onResult === 'function' ? onResult : function () {}, typeof onError === 'function' ? onError : function () {}, true);
        readResult(flowId);
        return true;
    }

    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin || !event.data || event.data.type !== 'qh.qq.oauth.complete') return;
        var flowId = String(event.data.flow_id || '');
        var item = active[flowId];
        if (!item || (item.popup && event.source !== item.popup)) return;
        item.completionNotified = true;
        item.completionNotifiedAt = Date.now();
        readResult(flowId);
    });

    window.QHQqOauth = {start: start, resume: resume};
})(window, document);
