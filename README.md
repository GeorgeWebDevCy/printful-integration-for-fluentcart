# Printful Integration for FluentCart

Connects [Printful](https://printful.com) print-on-demand fulfillment with [FluentCart](https://fluentcart.com). Automatic order fulfillment, live shipping rates, product sync, shipment tracking notifications, and more — all built on FluentCart's native APIs.

## Features

| # | Feature | Description |
|---|---------|-------------|
| 1 | **Product Sync** | Import Printful sync-products into FluentCart as physical products with variations, thumbnails, and optional production costs |
| 2 | **Automatic Order Fulfillment** | Paid FluentCart orders containing Printful-linked items are automatically sent to Printful |
| 3 | **Live Shipping Rates** | Real-time rates fetched from Printful at checkout and injected into the FluentCart checkout flow |
| 4 | **Webhook Handler** | Receives Printful webhooks for shipment events, updates order status and stores tracking data |
| 5 | **Shipping Tracking Email** | Sends a branded HTML email to the customer when their order ships, including tracking number and carrier |
| 6 | **Refund / Cancel Handling** | Automatically cancels draft/pending Printful orders on refund; flags shipped orders for manual return |
| 7 | **Activity Logging** | All fulfillment events are written to the FluentCart activity log per order |
| 8 | **Dashboard Widget** | WordPress dashboard widget showing pending, shipped, failed, and manual-return counts |
| 9 | **Bulk Fulfillment** | Admin page to review and bulk-send unfulfilled paid orders to Printful in one click |
| 10 | **Catalog Browser** | Browse the full Printful product catalog (categories, products, variants) directly from WP admin |
| 11 | **Shipping Setup** | Map Printful shipping service codes to FluentCart shipping methods with zone assignment |
| 12 | **WP-CLI Commands** | Full CLI interface for status, product sync, order fulfillment, and order listing |
| 13 | **Auto-Updates** | GitHub-based automatic update notifications via plugin-update-checker (watches `main` branch) |

## Requirements

- WordPress 5.8+
- PHP 7.4+
- [FluentCart](https://fluentcart.com) (free or Pro)
- A [Printful](https://printful.com) account with an API key

## Installation

1. Download the latest zip from the [Releases](../../releases) page or clone this repository.
2. Upload to `wp-content/plugins/` and activate from **Plugins**.
3. Go to **FluentCart → Printful → Settings** and enter your Printful API key.

Because `vendor/` is committed, no Composer step is needed on the server.

## Configuration

### Settings (FluentCart → Printful → Settings)

| Option | Default | Description |
|--------|---------|-------------|
| API Key | — | Your Printful API key (Dashboard → Stores → API) |
| Auto-Fulfill | On | Send orders to Printful automatically when paid |
| Auto-Confirm | Off | Immediately confirm Printful orders (triggers production & billing) |
| Test Mode | Off | Use Printful's test mode — no charges |
| Sync Product Costs | Off | Pull production costs from the Printful catalog and store them on variations |
| Disable Shipping Email | Off | Suppress the tracking notification email |
| Disable Auto-Cancel on Refund | Off | Do not cancel Printful orders automatically on refund |

### Webhook Setup

In **Settings**, register the webhook with Printful in one click. This enables live shipment status updates and tracking number storage.

### Shipping Setup (FluentCart → Printful → Shipping Setup)

Enable the Printful shipping services you want to offer at checkout and assign them to FluentCart shipping zones. Rates are fetched live from Printful — the amount stored in FluentCart is a placeholder only.

Available service codes: `STANDARD`, `PRINTFUL_FAST`, `PRINTFUL_OVERNIGHT`, `EXPRESS`, `ECONOMY`.

## WP-CLI

```bash
# Show integration status and API connection
wp pifc status

# Sync all products from Printful into FluentCart
wp pifc sync-products
wp pifc sync-products --dry-run

# Sync a single product by its Printful sync-product ID
wp pifc sync-product <id>

# Send an order to Printful for fulfillment
wp pifc fulfill-order <order_id>
wp pifc fulfill-order <order_id> --confirm   # also confirm immediately

# Cancel a Printful order
wp pifc cancel-fulfillment <order_id>

# List orders with Printful fulfillment data
wp pifc list-orders
wp pifc list-orders --status=failed
wp pifc list-orders --limit=50
```

## Custom Actions & Filters

### Actions fired by the plugin

| Hook | Arguments | Description |
|------|-----------|-------------|
| `pifc/order_fulfilled` | `$order`, `$printfulOrder` | After a FluentCart order is successfully sent to Printful |
| `pifc/fulfillment_canceled` | `$order` | After a Printful order is canceled |
| `pifc/order_shipped` | `$order`, `$shipment` | After a shipment webhook is received |
| `pifc/fulfillment_failed` | `$order`, `$data` | After Printful reports an order failure |
| `pifc/order_canceled_on_refund` | `$order` | After a Printful order is auto-canceled on refund |
| `pifc/manual_return_required` | `$order` | When a refund cannot auto-cancel (order already in production) |

### Filters

| Hook | Description |
|------|-------------|
| `pifc/shipping_email_subject` | Modify the tracking notification email subject |
| `pifc/shipping_email_from_name` | Modify the sender name for tracking emails |
| `pifc/shipping_email_from_address` | Modify the sender address for tracking emails |

## Architecture

```
src/
├── Api/
│   └── PrintfulClient.php          Printful REST API v2 wrapper
├── Admin/
│   ├── AdminMenu.php               Registers WP admin menu pages
│   ├── BulkFulfillPage.php         Bulk fulfillment UI + AJAX
│   ├── CatalogBrowserPage.php      Printful catalog browser UI + AJAX
│   ├── DashboardWidget.php         WP dashboard widget
│   ├── OrderPanel.php              Printful meta box on order edit screen
│   ├── ProductSyncPage.php         Product sync UI + AJAX
│   ├── SettingsPage.php            Plugin settings UI + AJAX
│   └── ShippingSetupPage.php       Shipping service mapping UI + AJAX
├── Cli/
│   └── CliCommands.php             WP-CLI command group (wp pifc …)
├── Helpers/
│   └── OrderMapper.php             Maps FC order data → Printful recipient
└── Services/
    ├── ActivityLogger.php          Writes events to FC activity log
    ├── OrderFulfillmentService.php Auto-fulfillment on order paid
    ├── ProductSyncService.php      Imports Printful products into FC
    ├── RefundService.php           Handles refund → cancel/flag logic
    ├── ShippingEmailService.php    Sends shipment tracking email
    ├── ShippingRateService.php     Injects live rates at checkout
    └── WebhookService.php          Handles incoming Printful webhooks
vendor/
└── yahnis-elsts/plugin-update-checker   GitHub auto-update library
views/
├── admin/                          Admin page templates
└── email/
    └── tracking-notification.php  Shipment tracking email template
```

## Auto-Updates

This plugin self-updates from the `main` branch of this repository. When the `Version:` header in the main plugin file is bumped and pushed, WordPress sites running this plugin will see an update notification in **Dashboard → Updates**.

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) or <https://www.gnu.org/licenses/gpl-2.0.html>.

## Author

[George Nicolaou](https://georgewebdev.cy)
