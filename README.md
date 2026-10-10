# PantoSource for Magento 2 (Open Source)

PHP module namespace **`Panto\Source`**, Composer package **`pantosource/magento`** (version **1.2.7**).

## Requirements

- **Adobe Commerce / Magento Open Source 2.4.9** (this release line only).
- **PHP 8.4 or 8.5** (as required by Magento 2.4.9).
- HTTPS recommended for production; API key and ingestion URL are configured per scope.

Adobe Commerce 2.4.9 release notes: [experienceleague.adobe.com](https://experienceleague.adobe.com/en/docs/commerce-operations/release/notes/magento-open-source/2-4-9).

## Install

From the Magento project root, require **`pantosource/magento`** from Packagist, then enable the module:

```bash
composer require pantosource/magento
bin/magento module:enable Panto_Source
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Composer installs the package under `vendor/` and loads `registration.php`, which registers the Magento module **`Panto_Source`**.

**Admin → Stores → Configuration → PantoSource**

- Enable integration, set **Ingestion base URL** (no trailing slash required).
- Enter **API key** (stored encrypted in config; decrypted at runtime for outbound `Authorization: Bearer` headers).
- Optional: **Frontend tracker**, **Attribution**, **Debug mode**.

After deploy, assign the **PantoSource → Operations** ACL resource to roles that should use the ops page (in addition to Configuration / Debug as needed).

Magento cron must be running (`bin/magento cron:run` on a system schedule, typically every minute) so the module queue and order-snapshot jobs execute.
