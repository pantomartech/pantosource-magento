# Changelog

## Unreleased

- [change] Last-known identity apply/persist only when Magento `CustomerSession::isLoggedIn()`. Guest pageviews do not inherit a previous order email.
- [feat] `PaymentInfoObserver`, `CheckoutInitObserver`, and frontend `add_payment_info` emit `magento_customer_identified` at first quote billing email (`event.type: identify`, `extensions.identity.action: link`). `add_payment_info` stays track.
