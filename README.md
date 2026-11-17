# PantoSource for Magento 2

PHP module namespace **`Panto\Source`**, Composer package **`pantosource/magento`** (version **1.2.10**).

## Requirements

- **Adobe Commerce / Magento Open Source 2.4.9** (this release line only).
- **PHP 8.4 or 8.5** (as required by Magento 2.4.9).

## Install

```bash
composer require pantosource/magento
bin/magento module:enable Panto_Source
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

**Admin → Stores → Configuration → PantoSource → Settings**: enable the integration, set the API key, and optionally adjust Advanced settings (ingestion/portal URLs, tracker, attribution).

Magento cron must be running so queue retry and order-snapshot export run.
