# Changelog

## Unreleased

- [fix] A page with UTMs or click ids now replaces the session landing (ATR-16). Before, `context.session.landing_url` kept the first page of the tab or browser-context cookie, so a later Google or Meta landing in the same session was attributed to the earlier page. The tracker (`sessionStorage` key `pantosource_session_landing`) and server emits on that request (new `Helper/SessionLandingPolicy.php`, used by `BrowserContextBridge`) both use the campaign page; checkout, `customer/section`, `pantosource/`, REST, GraphQL, static and media paths and AJAX requests never become the landing. Run `setup:static-content:deploy` (tracker JS) and `cache:flush`; no `setup:upgrade` needed.
- [fix] `magento_add_to_cart` was sent twice for every new cart line (three times for configurable products): the quote-item add events ran before the line was saved and keyed it on product and qty, while `checkout/cart/add` postdispatch keyed the saved line on its item id. Unsaved lines are now held and emitted once from the new `sales_quote_item_save_commit_after` observer (`CartAddSaveCommitObserver`), so the event always carries `quote_item_id`, `cart_id` and the `cart_<item>_magento_add_to_cart` semantic key; child lines resolve to their parent line. Run `cache:flush` (new observer in `etc/events.xml`).

- [change] Admin config (Stores → Configuration → PantoSource) reorganised: the page is now **Settings** (was Event Ingestion), the **Ingestion Service** group is **API Key** and holds only the API key, **Tracking** is renamed **Advanced settings** and now also holds **Portal base URL** and **Ingestion Base URL**, and the **Portal (store health)** group is removed. Config paths are unchanged (`pantosource/portal/base_url`, `pantosource/ingestion/base_url`), so saved values carry over; run `setup:upgrade` / `cache:flush`.
- [feat] Hashed PII sends every ad-platform form (`current/pii-normalisation.md`): `phone_sha256` is the digits with the country code (national numbers get the shipping, then billing, country's calling code, trunk prefix removed), new `phone_e164_sha256` (`+` form), `email_google_sha256` and `email_microsoft_sha256` when they differ. New `Model/PiiHashes.php` (calling-code table, no dependency); `HashedPiiHasher::hashFields()` takes the phone countries; `PayloadBuilder` passes the new keys. `tests/Unit/PiiHashesTest.php` runs the shared `fixtures/pii-hash-vectors.json`. `HashedPiiHasher::normalizePhone()` removed.
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
