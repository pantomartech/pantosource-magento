define([], function () {
    'use strict';

    function readCookie(name) {
        var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : '';
    }

    function writeCookie(name, value, days) {
        var expires = new Date();
        expires.setDate(expires.getDate() + days);
        document.cookie =
            name +
            '=' +
            encodeURIComponent(value) +
            ';expires=' +
            expires.toUTCString() +
            ';path=/;SameSite=Lax';
    }

    function getOrCreateAnonymousId() {
        var key = 'panto_anonymous_id';
        var id = readCookie(key);
        if (id) {
            return id;
        }
        id = 'anon_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
        writeCookie(key, id, 365);
        return id;
    }

    function getOrCreateSessionId() {
        var cookieName = 'panto_session_id';
        var sid = readCookie(cookieName);
        if (sid) {
            return sid;
        }
        sid = 'sess_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
        var expires = new Date();
        expires.setTime(expires.getTime() + 24 * 60 * 60 * 1000);
        document.cookie =
            cookieName +
            '=' +
            encodeURIComponent(sid) +
            ';expires=' +
            expires.toUTCString() +
            ';path=/;SameSite=Lax';
        return sid;
    }

    function getMetaBrowserCookies() {
        var fbp = readCookie('_fbp');
        var fbc = readCookie('_fbc');
        var ttp = readCookie('_ttp');
        var out = {};
        if (fbp) {
            out.fbp = fbp;
        }
        if (fbc) {
            out.fbc = fbc;
        } else {
            var qs = new URLSearchParams(window.location.search || '');
            var fbclid = qs.get('fbclid');
            if (fbclid) {
                out.fbc = 'fb.1.' + Date.now() + '.' + fbclid;
            }
        }
        if (ttp) {
            out.ttp = ttp;
        }
        return out;
    }

    function getOrCaptureJourneyId() {
        var param = new URLSearchParams(window.location.search || '').get('panto_journey_id');
        if (param && /^[0-9a-f-]{36}$/i.test(param)) {
            writeCookie('panto_journey_id', param, 365);
            return param;
        }
        var existing = readCookie('panto_journey_id');
        if (existing) {
            return existing;
        }
        var id = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (Math.random() * 16) | 0;
            var v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
        writeCookie('panto_journey_id', id, 365);
        return id;
    }

    var BROWSER_CTX_COOKIE = 'pantosource_browser_ctx';

    function writeBrowserContextCookie() {
        try {
            var landing = sessionStorage.getItem('pantosource_session_landing');
            if (!landing) {
                landing = window.location.href || '';
                sessionStorage.setItem('pantosource_session_landing', landing);
            }
            var payload = {
                session: { landing_url: landing },
                device: {
                    language: (navigator.language || ''),
                    screen_width: window.screen ? window.screen.width : 0,
                    screen_height: window.screen ? window.screen.height : 0,
                    color_depth: window.screen ? window.screen.colorDepth : 0
                },
                fingerprint: {
                    screen_res: window.screen ? window.screen.width + 'x' + window.screen.height : '',
                    color_depth: window.screen ? window.screen.colorDepth : 0,
                    language: navigator.language || '',
                    platform: navigator.platform || ''
                }
            };
            var expires = new Date();
            expires.setTime(expires.getTime() + 30 * 60 * 1000);
            document.cookie =
                BROWSER_CTX_COOKIE +
                '=' +
                encodeURIComponent(JSON.stringify(payload)) +
                ';expires=' +
                expires.toUTCString() +
                ';path=/;SameSite=Lax';
        } catch (e) {
            /* sessionStorage/cookie disabled */
        }
    }

    function getAttributionFromUrl() {
        var qs = new URLSearchParams(window.location.search || '');
        var keys = [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'gclid',
            'fbclid',
            'ttclid',
            'msclkid',
            'gbraid',
            'wbraid',
            'dclid',
            'gad_source'
        ];
        var out = {};
        keys.forEach(function (k) {
            var v = qs.get(k);
            if (v) {
                out[k] = v;
            }
        });
        return out;
    }

    function buildPageContext(cfg) {
        var hint =
            (cfg.flags && cfg.flags.pageTypeHint) || '';
        return {
            timezone:
                (typeof Intl !== 'undefined' &&
                    Intl.DateTimeFormat &&
                    Intl.DateTimeFormat().resolvedOptions().timeZone) ||
                '',
            page: {
                url: window.location.href || '',
                path:
                    (window.location.pathname || '') +
                    (window.location.search || ''),
                title: (typeof document !== 'undefined' && document.title) || '',
                referrer: (typeof document !== 'undefined' && document.referrer) || '',
                type: hint
            },
            device: {
                platform:
                    (typeof navigator !== 'undefined' && navigator.platform) || '',
                user_agent:
                    (typeof navigator !== 'undefined' && navigator.userAgent) || ''
            },
            attribution: { first_touch: {}, last_touch: {} }
        };
    }

    function urlWithFormKey(url, formKey) {
        if (!url || !formKey) {
            return url;
        }
        var sep = url.indexOf('?') >= 0 ? '&' : '?';
        return url + sep + 'form_key=' + encodeURIComponent(formKey);
    }

    function sendMarker(cfg, actionName, eventName, data) {
        if (!cfg.markerUrl || !cfg.formKey) {
            return;
        }
        var body = JSON.stringify({
            form_key: cfg.formKey,
            action_name: actionName || '',
            event_name: eventName || '',
            data: data || {}
        });
        fetch(urlWithFormKey(cfg.markerUrl, cfg.formKey), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: body
        }).catch(function () {});
    }

    function postEvent(cfg, eventName, data) {
        if (!cfg.eventUrl || !cfg.formKey || !eventName) {
            return;
        }
        var attrOn =
            !cfg.flags ||
            cfg.flags.attributionEnabled === undefined ||
            cfg.flags.attributionEnabled === true;
        var attribution = attrOn ? getAttributionFromUrl() : {};
        var meta = attrOn ? getMetaBrowserCookies() : {};
        var payloadData = data ? Object.assign({}, data) : {};
        if (!payloadData.source) {
            payloadData.source = 'frontend_js';
        }
        if (!payloadData.dedupe_key) {
            payloadData.dedupe_key = [
                eventName,
                payloadData.product_id || '',
                payloadData.quantity || '',
                payloadData.quote_item_id || ''
            ].join(':');
        }
        payloadData.anonymous_id = getOrCreateAnonymousId();
        payloadData.session_id = getOrCreateSessionId();
        writeBrowserContextCookie();
        var journeyId = getOrCaptureJourneyId();

        var payload = {
            form_key: cfg.formKey,
            eventName: eventName,
            data: payloadData,
            context: buildPageContext(cfg)
        };
        if (journeyId) {
            payloadData.journey_id = journeyId;
        }
        Object.keys(attribution).forEach(function (k) {
            payload[k] = attribution[k];
        });
        Object.keys(meta).forEach(function (k) {
            payload[k] = meta[k];
        });

        if (cfg.debug) {
            sendMarker(cfg, 'frontend_send', eventName, payloadData);
        }

        fetch(urlWithFormKey(cfg.eventUrl, cfg.formKey), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Trace-Id': journeyId || ''
            },
            body: JSON.stringify(payload)
        })
            .then(function (response) {
                if (cfg.debug) {
                    console.warn('[PantoSource] tracker/event HTTP', response.status, response.url);
                }
            })
            .catch(function (err) {
                if (cfg.debug) {
                    console.warn('[PantoSource] tracker/event failed', err);
                }
            });
    }

    function trackPageview(cfg, fired, pathKey) {
        var key = pathKey || window.location.pathname + window.location.search;
        once(fired, 'pageview:' + key, function () {
            postEvent(cfg, 'pageview', { page_type: (cfg.flags && cfg.flags.pageTypeHint) || 'page' });
        });
    }

    function setupSpaPageviews(cfg, fired) {
        if (!cfg.flags || !cfg.flags.enableSpaPageviews) {
            return;
        }
        var lastPath = window.location.pathname + window.location.search;
        function checkPath() {
            var current = window.location.pathname + window.location.search;
            if (current !== lastPath) {
                lastPath = current;
                trackPageview(cfg, fired, current);
            }
        }
        var origPush = history.pushState;
        var origReplace = history.replaceState;
        history.pushState = function () {
            origPush.apply(history, arguments);
            checkPath();
        };
        history.replaceState = function () {
            origReplace.apply(history, arguments);
            checkPath();
        };
        window.addEventListener('popstate', checkPath);
    }

    function once(fired, key, fn) {
        if (fired[key]) {
            return;
        }
        fired[key] = true;
        fn();
    }

    function maybeIdentifyCustomer(cfg) {
        var id = cfg.customerId ? parseInt(cfg.customerId, 10) : 0;
        if (!id) {
            return;
        }
        try {
            if (sessionStorage.getItem('panto_customer_identified')) {
                return;
            }
            sessionStorage.setItem('panto_customer_identified', '1');
        } catch (e) {
            /* sessionStorage disabled */
        }
        postEvent(cfg, 'magento_customer_identified', { source: 'frontend_js' });
    }

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function startTracker(cfg) {
        if (!cfg || !cfg.eventUrl || !cfg.formKey) {
            return;
        }
        var fired = {};

        onReady(function () {
            trackPageview(cfg, fired);
            setupSpaPageviews(cfg, fired);

            // product_viewed / product_list_viewed / search_performed are server-canonical
            // (ProductViewObserver / CategoryViewObserver / SearchObserver). Config is loaded
            // on a separate GET, so JS must not emit those — it only stamps pageview type.

            // checkout_initiated is emitted server-side by CheckoutInitObserver
            // (controller_action_predispatch_checkout_index_index), which provides a richer
            // payload (full cart items with categories, cart_total, currency, item_count) and
            // is already quote-scoped via CheckoutSession key
            // pantosource_checkout_init_sent_{quoteId}. Emitting it here too caused a duplicate.

            // Cart add/remove/qty are server-canonical (Woo model). Do not emit optimistic
            // .tocart clicks — CartAddObserver fires after Magento confirms the add.

            maybeIdentifyCustomer(cfg);
        });

        window.pantoSourceTrack = function (eventName, data) {
            postEvent(cfg, eventName, data || {});
        };
    }

    function loadConfigFromUrl(configUrl) {
        var pagePath = (window.location.pathname || '') + (window.location.search || '');
        if (pagePath.charAt(0) !== '/') {
            pagePath = '/' + pagePath;
        }
        if (pagePath.length > 2048) {
            pagePath = pagePath.slice(0, 2048);
        }
        var sep = configUrl.indexOf('?') >= 0 ? '&' : '?';
        var url = configUrl + sep + 'page_path=' + encodeURIComponent(pagePath);
        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        })
            .then(function (response) {
                if (response.status === 204 || !response.ok) {
                    return null;
                }
                return response.json();
            })
            .then(function (cfg) {
                if (!cfg) {
                    return;
                }
                startTracker(cfg);
            })
            .catch(function () {});
    }

    return function (config) {
        if (!config) {
            return;
        }

        if (config.configUrl) {
            loadConfigFromUrl(config.configUrl);
            return;
        }

        startTracker(config);
    };
});
