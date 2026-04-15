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
        var out = {};
        if (fbp) {
            out.fbp = fbp;
        }
        if (fbc) {
            out.fbc = fbc;
        }
        return out;
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
            'wbraid'
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
        fetch(cfg.markerUrl, {
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
        var attribution = getAttributionFromUrl();
        var meta = getMetaBrowserCookies();
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

        var payload = {
            form_key: cfg.formKey,
            eventName: eventName,
            data: payloadData,
            context: buildPageContext(cfg)
        };
        Object.keys(attribution).forEach(function (k) {
            payload[k] = attribution[k];
        });
        Object.keys(meta).forEach(function (k) {
            payload[k] = meta[k];
        });

        if (cfg.debug) {
            sendMarker(cfg, 'frontend_send', eventName, payloadData);
        }

        fetch(cfg.eventUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(payload)
        }).catch(function () {});
    }

    function once(fired, key, fn) {
        if (fired[key]) {
            return;
        }
        fired[key] = true;
        fn();
    }

    function installAddToCartClicks(cfg, fired) {
        document.body.addEventListener(
            'click',
            function (event) {
                var btn = event.target.closest(
                    'button[type="submit"].tocart, .action.tocart, form[data-role="tocart-form"] button[type="submit"]'
                );
                if (!btn) {
                    return;
                }
                var form = btn.closest('form');
                if (!form) {
                    return;
                }
                var pidInput =
                    form.querySelector('input[name="product"]') ||
                    form.querySelector('input[name="product_id"]');
                var qtyInput = form.querySelector('input[name="qty"]');
                var productId = pidInput ? parseInt(pidInput.value, 10) : 0;
                var qty = qtyInput ? parseFloat(qtyInput.value) : 1;
                if (!productId) {
                    return;
                }
                postEvent(cfg, 'magento_add_to_cart', {
                    source: 'frontend_click',
                    product_id: productId,
                    quantity: isNaN(qty) || qty <= 0 ? 1 : qty,
                    sku: '',
                    currency: cfg.currency || ''
                });
            },
            true
        );
    }

    return function (config) {
        if (!config || !config.eventUrl || !config.formKey) {
            return;
        }
        var cfg = config;
        var fired = {};

        document.addEventListener('DOMContentLoaded', function () {
            if (cfg.flags && cfg.flags.isProduct && cfg.product && cfg.product.id) {
                once(fired, 'product_viewed', function () {
                    postEvent(cfg, 'product_viewed', {
                        page_type: 'product',
                        currency: cfg.currency || '',
                        product_id: cfg.product.id,
                        product_name: cfg.product.name || '',
                        price: cfg.product.price || '',
                        sku: cfg.product.sku || ''
                    });
                });
            }

            if (cfg.flags && cfg.flags.isCategoryList && cfg.category) {
                once(fired, 'product_list_viewed', function () {
                    postEvent(cfg, 'product_list_viewed', {
                        list_type: 'category',
                        category_id: cfg.category.id,
                        category_name: cfg.category.name || ''
                    });
                });
            }

            if (cfg.flags && cfg.flags.isSearch) {
                once(fired, 'search_performed', function () {
                    var q = (cfg.search && cfg.search.query) || '';
                    var rc =
                        cfg.search && typeof cfg.search.resultCount === 'number'
                            ? cfg.search.resultCount
                            : 0;
                    postEvent(cfg, 'search_performed', {
                        query: q,
                        result_count: rc
                    });
                });
            }

            if (cfg.flags && cfg.flags.isCheckout) {
                once(fired, 'checkout_initiated', function () {
                    postEvent(cfg, 'checkout_initiated', { source: 'frontend_js' });
                });
            }

            installAddToCartClicks(cfg, fired);
        });
    };
});
