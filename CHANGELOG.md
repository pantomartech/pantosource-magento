# Changelog

## Unreleased

- [fix] Purchase `order_totals.discount_total` is sent as a magnitude (`abs`). Magento stores order discounts as negative numbers, which the ingestion schema (`commerce.discount >= 0`) rejects.
- [fix] Skip `magento_customer_identified` (`checkout_email`) when the quote already has a logged-in customer. Logged-in JS identify is unchanged.
- [fix] Skip `magento_customer_updated` on checkout, PayPal, REST, GraphQL, AJAX, and IPN paths (account edits still emit).

## 1.2.8

- [change] Ingestion base URL defaults to `https://events.pantosource.com` and is prefilled in admin. Empty values and `events.staging.pantosource.com` are rewritten to that URL (`setup:upgrade` updates saved config).

## 1.2.7

- [fix] SPA history navigation to a PDP emits JS `product_viewed` when `ProductViewObserver` did not claim the product; tracker re-fetches config by `page_path` and may send client `occurred_at`. Document product views remain server-canonical.

## 1.2.6

- [feat] Store health: online-store channel marking on `pantosource_order_identity.sales_channel` (frontend, graphql, webapi_rest with cart token). Purchase, cancel, refund, fulfill and order-placed events are gated to online store only; `commerce.sales_channel` on order payloads.
- [feat] Hourly cron `pantosource_export_order_snapshots` uploads order snapshots to the portal (`pantosource/portal/base_url`, default `https://app.pantosource.com`) with Bearer API key and `X-Panto-Integration: magento`. Tombstones on `sales_order_delete_after`. Tables: `sales_channel` column, `pantosource_snapshot_tombstone` (run `setup:upgrade`).
- [change] Requires Magento cron (`bin/magento cron:run`) for snapshot export and queue processing.

## 1.2.5

- [feat] `magento_purchase_completed` carries `commerce.payment_status` (pending, paid, cancelled, refunded, partially_refunded) from `PaymentStatusNormalizer`.
- [feat] `OrderPaymentStatusObserver` on `sales_order_save_after` emits `magento_order_payment_status_changed` once per distinct status after the purchase was sent. New table `pantosource_order_payment_status` stores statuses already sent (run `setup:upgrade`).
- [fix] `extensions.magento.module_version` now matches the package version (was stale at 1.2.3).
- [change] Last-known identity apply/persist only when Magento `CustomerSession::isLoggedIn()`. Guest pageviews do not inherit a previous order email.
- [feat] `PaymentInfoObserver`, `CheckoutInitObserver`, and frontend `add_payment_info` emit `magento_customer_identified` at first quote billing email (`event.type: identify`, `extensions.identity.action: link`). `add_payment_info` stays track.
