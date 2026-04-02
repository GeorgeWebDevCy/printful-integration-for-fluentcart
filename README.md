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

## Release Notes

### 1.0.1

- Fixed FluentCart dependency detection so the plugin no longer shows a false "requires FluentCart to be installed and active" notice on valid installs.

### 1.0.2

- Fixed the settings save flow so an existing Printful API key is preserved unless you intentionally replace it.
- Fixed missing settings UI and save handling for product costs, shipping emails, refund auto-cancel, and failed-order retry options.
- Added webhook registration feedback after saving settings so configuration problems are surfaced in the admin UI.

### 1.0.3

- Added visible loading indicators for connection testing, settings saves, product sync actions, order lookups, and bulk fulfillment actions.
- Refreshed the admin screens to better match FluentCart's visual language and workflow patterns.

### 1.0.4

- Reworked the admin layout to use a shared FluentCart-style header, navigation, and hero section across plugin pages.
- Brought the overall admin structure closer to the FluentCart reference UI instead of a generic standalone WordPress settings layout.

### 1.0.5

- Switched the plugin admin pages into a FluentCart-style app wrapper using `#fct_admin_app_wrapper`, the global admin menu holder, and a settings-style nav/content shell.
- Replaced the custom page shell with a layout that more closely matches the FluentCart admin DOM structure shown in the reference markup.

### 1.0.6

- Added a WordPress `Update URI` header so the plugin reliably uses its custom GitHub update source instead of any WordPress.org lookup path.
- Added a WordPress-style `readme.txt` so plugin-update-checker can expose proper update metadata, changelog details, and stable version info.

### 1.0.7

- Enqueued FluentCart's real admin app stylesheet on the Printful submenu pages so the `fct_*` layout now has the same CSS foundation as FluentCart itself.

### 1.0.8

- Removed the incompatible FluentCart global admin script from the Printful submenu pages and styled the shared `fct_*` markup directly to better match the reference UI on standalone submenu screens.

### 1.0.9

- Registered Printful as a real FluentCart integration module so its configuration now opens inside FluentCart's native Integrations UI.
- Redirected the old Printful settings submenu into the FluentCart integration screen and updated legacy setup links to land there as well.

### 1.0.10

- Updated the Printful settings submenu link so it now points directly to FluentCart's native `admin.php?page=fluent-cart#/integrations/printful` route.

### 1.0.11

- Rebuilt the Printful integration settings payload to match FluentCart's native global integration schema instead of a custom field format that the Vue app could not render.
- Removed the submenu URL rewrite hack and kept the old settings page as a normal redirect to the FluentCart integration route to avoid breaking FluentCart's admin runtime.

### 1.0.18

- Removed the broken copied FluentCart shell from the standalone Printful tool pages and replaced it with a clean shared Printful tools header and nav.
- Kept the main Printful settings entry native inside FluentCart while making the remaining standalone admin screens usable and visually consistent again.

### 1.0.17

- Fixed the native FluentCart Printful integration screen by returning the wrapped REST payload shape FluentCart's Vue app expects, so the page now renders properly inside FluentCart instead of failing with a blank settings pane.
- Changed `FluentCart > Printful` to redirect cleanly into the native FluentCart integration route instead of landing on a broken standalone page.
- Moved the main fulfillment and sync behavior toggles into the native FluentCart integration screen so core Printful settings are managed inside FluentCart itself.

### 1.0.16

- Changed the main `FluentCart > Printful` submenu to hand off directly to FluentCart's native integration screen so the primary settings entry now uses the real FluentCart UI.
- Moved the old standalone behavior toggles to a dedicated `Printful Advanced` page and updated internal links to point to the correct native or advanced screen.

### 1.0.15

- Added self-healing option initialization so plugin defaults and version metadata are restored automatically if activation did not re-run after a path or bootstrap-file change.
- Synced the FluentCart integration option automatically during boot so local and migrated environments expose the Printful integration state consistently.
- Added backward-compatible support for the older `fluent-cart-printful/v1/webhook` namespace alongside the current `pifc/v1/webhook` route.

### 1.0.14

- Reworked Printful shipping-method sync to avoid relying on JSON-path queries in the FluentCart model layer when locating existing managed methods.
- Updated address-sync handling so customer changes also propagate to connected orders when the linked Printful orders are still editable.
- Clear stale fulfillment error meta after a successful recipient update.

### 1.0.13

- Secured the Printful webhook endpoint by requiring the generated shared secret on inbound requests and on the registered webhook URL.
- Fixed refund automation so it now matches FluentCart's refund event payload shape instead of expecting a bare `Order` model.
- Removed the WooCommerce-only currency dependency from live shipping rate calculation and now resolve checkout currency from FluentCart request data.
- Mark locally synced variations inactive when they are removed from Printful, and tightened catalog "already synced" detection to reduce false positives.

### 1.0.12

- Restored the standalone Printful settings screen so advanced fulfillment, retry, sync, refund, and shipping email options are editable again.
- Synced the standalone settings save flow with FluentCart's integration option store so both admin entry points stay consistent.
- Added a direct advanced-settings link inside the native FluentCart integration screen.

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) or <https://www.gnu.org/licenses/gpl-2.0.html>.

## Author

[George Nicolaou](https://georgewebdev.cy)
