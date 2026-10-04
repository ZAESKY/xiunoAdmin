(function () {
    function normalize(lang) {
        lang = String(lang || '').toLowerCase().replace('_', '-');
        if (lang.indexOf('zh') === 0) {
            return 'zh-cn';
        }
        if (lang.indexOf('en') === 0) {
            return 'en-us';
        }
        return 'en-us';
    }

    function readCookie(name) {
        var parts = document.cookie ? document.cookie.split('; ') : [];
        for (var i = 0; i < parts.length; i++) {
            var item = parts[i].split('=');
            if (decodeURIComponent(item[0]) === name) {
                return decodeURIComponent(item.slice(1).join('='));
            }
        }
        return '';
    }

    function writeCookie(name, value) {
        var secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = encodeURIComponent(name) + '=' + encodeURIComponent(value) + '; path=/; max-age=31536000; SameSite=Lax' + secure;
    }

    var cookieName = 'think_lang';
    var current = normalize(readCookie(cookieName));
    var browser = normalize(navigator.language || (navigator.languages && navigator.languages[0]));
    var query = new URLSearchParams(window.location.search);
    var requested = query.get('lang');
    var next = requested ? normalize(requested) : (readCookie(cookieName) ? current : browser);

    if (readCookie(cookieName) !== next) {
        writeCookie(cookieName, next);
        window.location.reload();
        return;
    }

    window.SF_LANG = {
        current: next,
        set: function (lang) {
            var normalized = normalize(lang);
            writeCookie(cookieName, normalized);
            var url = new URL(window.location.href);
            if (url.searchParams.has('lang')) {
                url.searchParams.set('lang', normalized);
                window.location.replace(url.toString());
                return;
            }
            window.location.reload();
        },
        toggle: function () {
            this.set(this.current === 'zh-cn' ? 'en-us' : 'zh-cn');
        }
    };
})();
