define([], function () {
    'use strict';

    return function (target) {
        return function (paymentMethod) {
            try {
                var method = '';
                if (paymentMethod && typeof paymentMethod === 'object') {
                    if (paymentMethod.method) {
                        method = paymentMethod.method;
                    } else if (typeof paymentMethod.getCode === 'function') {
                        method = paymentMethod.getCode();
                    }
                } else if (typeof paymentMethod === 'string') {
                    method = paymentMethod;
                }
                if (typeof window.pantoSourceTrack === 'function' && method) {
                    window.pantoSourceTrack('add_payment_info', {
                        payment_method: method,
                        source: 'checkout_mixin'
                    });
                }
            } catch (e) {
                /* tracking must not break checkout */
            }
            return target(paymentMethod);
        };
    };
});
