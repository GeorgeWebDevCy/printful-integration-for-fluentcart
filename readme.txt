=== Printful Integration for FluentCart ===
Contributors: georgenicolaou
Tags: fluentcart, printful, print on demand, ecommerce, fulfillment
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.6
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
