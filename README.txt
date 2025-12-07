=== Printful Integration for FluentCart ===
Contributors: georgenicolaou
Tags: ecommerce, fluentcart, printful, dropshipping
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 3.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Connect your FluentCart store to Printful for automated fulfilment, webhooks, and live shipping rates.

== Description ==

Push FluentCart orders to Printful automatically, keep fulfilment status in sync (webhooks or polling), and fetch live shipping rates during checkout. Includes admin settings for API key, webhooks, queue visibility, and a catalog cache to assist variant mapping.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/`.
2. Activate it from **Plugins**.
3. Go to **FluentCart → Printful Integration**, paste your Printful API key, configure webhooks/polling, and map variants.

== Frequently Asked Questions ==

= Do I need both webhooks and polling? =

Webhooks are preferred for immediate updates. Enable polling as a fallback or when webhooks are not available in your environment.

= Can I mix non-Printful items in the cart? =

Yes. Only mapped variants are sent to Printful. Live rates require all physical items in the cart to be mapped.

== WooCommerce parity status ==

See `docs/woocommerce-parity.md` for a detailed comparison against the official Printful WooCommerce integration. Highlights:
- Implemented: order push, webhook/polling sync, live rates, catalog cache + mapping helper, admin diagnostics, order actions (send/refresh/cancel).
- Missing: product import/creation, carrier/service UI, tax helpers, dashboard/status widgets, request log viewer, size guides/customizer, REST endpoints. These remain to be built.

== Changelog ==

= 1.2.0 =
* Added WooCommerce parity report and documented remaining gaps.
* Minor styling polish for order widget and activity logging on admin actions.
* Bumped version to 1.2.0.

= 1.3.0 =
* Added diagnostics dashboard (health summary + request logs) and REST health/config/log endpoints.
* Added carrier allowlist + fallback shipping option.
* Added basic product import shell, size guide shortcode, and request log capture.

= 1.3.1 =
* Added full product import to create FluentCart products/variations with media, pricing (with markup), and mapping.
* Added carrier service allowlist option and refined import UI messaging.

= 1.3.2 =
* Added carrier/service fetcher with checkbox UI, dashboard health widget, log clearing, and enriched diagnostics stats.

= 1.4.0 =
* Added size guide editor metabox for Fluent products and shortcode rendering.
* Parity doc updated for remaining gaps.

= 1.5.0 =
* Added REST endpoints for settings/mappings, per-request sender (origin) address, and richer settings fields.
* Added size guide UI, carrier/service fetch UI, diagnostics improvements, and REST log access.

= 1.6.0 =
* Added product-level fulfilment overrides and preferred service meta box.
* Added origin sender address for rate requests.
* Added REST endpoints for products and settings updates, plus token migration stub.

= 1.7.0 =
* Added regional origin override (alternate sender for specified countries).
* Added per-product fulfilment/service overrides respected in orders and rates.
* Expanded REST/config fields accordingly.

= 1.8.0 =
* Added tax helper toggles, diagnostics checklist, and REST/settings coverage for new fields.
* Added per-region origin selection for rates and deeper fulfilment overrides.

= 1.8.1 =
* Added quick “Open in Printful” link in the product metabox.
* Updated parity notes to reflect current coverage.

= 1.9.0 =
* Added multi-slot regional origin overrides UI (up to 3) and passing sender by destination country.
* Added tax helper flags, diagnostics checklist, and REST/settings coverage for new fields.

= 1.1.0 =
* Added admin diagnostics (API connection test and queue depth).
* Added Printful catalog sync cache to help variant mapping and optional daily auto-refresh.
* Added catalog helper UI for mapping variants quickly.
* Improved settings copy and bumped plugin version.

= 1.0.0 =
* Initial release with order sync, webhooks/polling, and live shipping rates.

== Changelog ==

= 1.0 =
* A change since the previous version.
* Another change.

= 0.5 =
* List versions from most recent at top to oldest at bottom.

== Upgrade Notice ==

= 1.0 =
Upgrade notices describe the reason a user should upgrade.  No more than 300 characters.

= 0.5 =
This version fixes a security related bug.  Upgrade immediately.

== Arbitrary section ==

You may provide arbitrary sections, in the same format as the ones above.  This may be of use for extremely complicated
plugins where more information needs to be conveyed that doesn't fit into the categories of "description" or
"installation."  Arbitrary sections will be shown below the built-in sections outlined above.

== A brief Markdown Example ==

Ordered list:

1. Some feature
1. Another feature
1. Something else about the plugin

Unordered list:

* something
* something else
* third thing

Here's a link to [WordPress](http://wordpress.org/ "Your favorite software") and one to [Markdown's Syntax Documentation][markdown syntax].
Titles are optional, naturally.

[markdown syntax]: http://daringfireball.net/projects/markdown/syntax
            "Markdown is what the parser uses to process much of the readme file"

Markdown uses email style notation for blockquotes and I've been told:
> Asterisks for *emphasis*. Double it up  for **strong**.

`<?php code(); // goes in backticks ?>`
= 1.9.1 =
* Minor parity doc touch-up and metabox convenience link.

= 2.0.0 =
* Added admin token migration runner (legacy option sweep) surfaced in diagnostics.

= 2.1.0 =
* Added direct links to Printful product and designer/mockup from the product metabox.

= 2.2.0 =
* Added per-product origin profile selection and extended multi-origin handling for shipping.
* REST products payload now includes origin index and designer URL.

= 2.3.0 =
* Added log-level filtering in diagnostics and REST logs endpoint.

= 2.4.0 =
* Added mockup preview URL storage and REST exposure; per-product origin selection retained.

= 2.5.0 =
* Added dynamic multi-origin profile UI; shipping honors per-product origin profile first, then destination-based profiles.
* REST products now include mockup URL; admin mockup preview field added.

= 2.6.0 =
* Added optional embedded Printful designer modal and mockup preview field; designer URLs exposed via REST.

= 2.7.0 =
* Added log search/filtering in diagnostics and REST logs.

= 2.8.0 =
* Added admin notices for missing API key and webhook signature failures.
* Added dry-run token migration and broader legacy key search.
* Designer embed toggle; dynamic origin profile UI kept; mockup field retained.

= 2.9.0 =
* Added order payload `external_taxes` flag when tax helper is enabled.
* Added recent error panel and search in diagnostics; REST logs accept search param.
* Added dry-run toggle to migration action.

= 3.0.0 =
* Added queue reset action, error highlights in diagnostics, and broadened tax helper in order payloads.
* Expanded migration options (env/legacy) with dry-run.

= 3.1.0 =
* Added log search/limit in diagnostics and REST; recent errors panel.
* Added taxes_included flag when tax helper enabled; dry-run queue clear.

= 3.2.0 =
* Added tax-status REST endpoint exposing Printful tax helper flags.
* Surfaced last migration metadata in diagnostics and settings; extended migration storage.
* Added queue clear action, log limit parameter, and broader diagnostics status coverage.
