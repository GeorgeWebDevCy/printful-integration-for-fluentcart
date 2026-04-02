=== Printful Integration for FluentCart ===
Contributors: georgenicolaou
Tags: fluentcart, printful, print on demand, ecommerce, fulfillment
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.15
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Printful print-on-demand fulfillment with FluentCart.

== Description ==

Printful Integration for FluentCart connects your FluentCart store with Printful for product sync, automatic order fulfillment, shipment tracking, and live shipping rates.

Features include:

* Product sync from your Printful store into FluentCart.
* Automatic fulfillment for paid orders.
* Manual and bulk fulfillment tools.
* Shipment tracking sync and customer notifications.
* Shipping service mapping for FluentCart zones.
* WP-CLI support.
* GitHub-based updates via plugin-update-checker.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/`.
2. Activate **Printful Integration for FluentCart** in WordPress.
3. Make sure FluentCart is installed and active.
4. Open **FluentCart > Printful** and save your Printful API key.

== Changelog ==

= 1.0.15 =
* Added self-healing option initialization so plugin defaults and version metadata are restored automatically if activation did not re-run after a path or bootstrap-file change.
* Synced the FluentCart integration option automatically during boot so local and migrated environments expose the Printful integration state consistently.
* Added backward-compatible support for the older `fluent-cart-printful/v1/webhook` namespace alongside the current `pifc/v1/webhook` route.

= 1.0.14 =
* Reworked Printful shipping-method sync to avoid relying on JSON-path queries in the FluentCart model layer when locating existing managed methods.
* Updated address-sync handling so customer changes also propagate to connected orders when the linked Printful orders are still editable.
* Clear stale fulfillment error meta after a successful recipient update.

= 1.0.13 =
* Secured the Printful webhook endpoint by requiring the generated shared secret on inbound requests and on the registered webhook URL.
* Fixed refund automation so it now matches FluentCart's refund event payload shape instead of expecting a bare Order model.
* Removed the WooCommerce-only currency dependency from live shipping rate calculation and now resolve checkout currency from FluentCart request data.
* Mark locally synced variations inactive when they are removed from Printful, and tightened catalog "already synced" detection to reduce false positives.

= 1.0.12 =
* Restored the standalone Printful settings screen so advanced fulfillment and sync options are editable again.
* Synced the standalone settings save flow with FluentCart's integration option store so both admin entry points stay consistent.
* Added a direct advanced-settings link inside the native FluentCart integration screen.

= 1.0.11 =
* Rebuilt the Printful integration settings payload so it matches FluentCart's native global integration schema instead of a custom format that broke the Vue integration screen.
* Removed the submenu URL rewrite hack and kept the legacy settings page as a redirect into FluentCart's integration route for safer admin app behavior.

= 1.0.10 =
* Updated the Printful settings submenu link so it points directly to FluentCart's native `admin.php?page=fluent-cart#/integrations/printful` route instead of first loading a plugin-specific page slug.

= 1.0.9 =
* Registered Printful as a real FluentCart integration module so configuration now lives in FluentCart's native Integrations UI.
* Redirected the old Printful settings submenu into the FluentCart integrations screen to keep the entry point consistent.
* Updated legacy configuration links to point to the new FluentCart integration route.

= 1.0.8 =
* Removed the incompatible FluentCart global admin script from the Printful submenu pages and styled the `fct_*` markup directly so the layout matches FluentCart without requiring the full SPA boot process.

= 1.0.7 =
* Enqueued FluentCart's actual admin app stylesheet on the Printful submenu pages so the shared `fct_*` wrapper renders with the proper FluentCart UI foundation.

= 1.0.6 =
* Added a WordPress `Update URI` header so the plugin reliably uses its custom GitHub update source.
* Added a WordPress-style `readme.txt` so plugin-update-checker can provide proper update metadata and version details.

= 1.0.5 =
* Switched the plugin admin pages into a FluentCart-style app wrapper using `#fct_admin_app_wrapper`, the global admin menu holder, and a settings-style nav/content shell.
* Replaced the custom page shell with a layout that more closely matches the FluentCart admin DOM structure shown in the reference markup.

= 1.0.4 =
* Reworked the admin layout to use a shared FluentCart-style header, navigation, and hero section across plugin pages.
* Brought the overall admin structure closer to the FluentCart reference UI instead of a generic standalone WordPress settings layout.

= 1.0.3 =
* Added visible loading indicators for connection testing, settings saves, product sync actions, order lookups, and bulk fulfillment actions.
* Refreshed the admin screens to better match FluentCart's visual language and workflow patterns.

= 1.0.2 =
* Fixed the settings save flow so an existing Printful API key is preserved unless you intentionally replace it.
* Fixed missing settings UI and save handling for product costs, shipping emails, refund auto-cancel, and failed-order retry options.
* Added webhook registration feedback after saving settings so configuration problems are surfaced in the admin UI.

= 1.0.1 =
* Fixed FluentCart dependency detection so the plugin no longer shows a false "requires FluentCart to be installed and active" notice on valid installs.
