# Changelog

## Unreleased

## 1.2.5

- [feat] `magento_purchase_completed` carries `commerce.payment_status` (pending, paid, cancelled, refunded, partially_refunded) from `PaymentStatusNormalizer`.
- [feat] `OrderPaymentStatusObserver` on `sales_order_save_after` emits `magento_order_payment_status_changed` once per distinct status after the purchase was sent. New table `pantosource_order_payment_status` stores statuses already sent (run `setup:upgrade`).
- [fix] `extensions.magento.module_version` now matches the package version (was stale at 1.2.3).
- [change] Last-known identity apply/persist only when Magento `CustomerSession::isLoggedIn()`. Guest pageviews do not inherit a previous order email.
- [feat] `PaymentInfoObserver`, `CheckoutInitObserver`, and frontend `add_payment_info` emit `magento_customer_identified` at first quote billing email (`event.type: identify`, `extensions.identity.action: link`). `add_payment_info` stays track.
