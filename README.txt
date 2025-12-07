=== Printful Integration for FluentCart ===
Contributors: georgenicolaou
Tags: ecommerce, fluentcart, printful, dropshipping
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.1.0
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

== Changelog ==

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
