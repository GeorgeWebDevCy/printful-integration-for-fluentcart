# Printful WooCommerce → FluentCart parity report

Reference plugin: `jimmy-printful/printful-shipping-for-woocommerce` (v2.2.11).

## Implemented in FluentCart
- **Order push**: Auto send paid orders to Printful with mapped variants; stores `_printful_order_id`/status, enqueues follow-up polling.
- **Status sync**: Webhooks (with HMAC validation) + polling queue; updates shipping status and tracking meta.
- **Live rates**: Calls `shipping/rates`, caches per cart, supports markup %, resolves `printful:` methods.
- **Variant mapping**: Meta-backed mapping; helper table fed by cached Printful catalog; manual mapping textarea retained.
- **Catalog cache**: Manual + optional daily sync to pull Printful products/variants for mapping assist.
- **Admin diagnostics**: Connection test, queue depth, webhook endpoint/secret, logging toggle.
- **Order actions**: Admin widget to Send/Refresh/Cancel and display Printful ID/status/tracking inside order view.

## Missing vs WooCommerce integration
- **Product/catalog sync & creation**: Basic cached catalog browser + REST-exposed variant maps exist, with hourly delta refresh and a helper that proxies Printful variants into the importer; still lacks full bulk import UI and advanced mapping automation.
- **Shipping UI parity**: Multi-warehouse/origin handling still missing beyond a single override slot.
- **Tax helpers**: No automatic tax toggles or Printful tax address sync.
- **Admin dashboards**: Missing deep stats checklist/status report screens; basic diagnostics and dashboard widget exist.
- **Customizer/design tools**: No mockup generator or on-site designer (link-out only).
- **REST surface**: Health/config/logs + settings/mappings/products endpoints exist; still missing richer endpoints and mutations for catalog/mappings.
- **Token migration/legacy flows**: Token migration helper is a stub (no real migration).
- **Automation depth**: Per-product fulfilment toggle and service overrides exist; still missing deeper rules and multi-warehouse handling.

## Recommended next steps to reach parity
1) **Product import & mapping UI**: Fetch catalog, render option-level mapping (size/color), allow creating FluentCart products/variants with images/prices/markup rules; add delta sync job.
2) **Shipping controls**: Add carrier/service listing and enable/disable UI; optional fallback table and multi-warehouse origin support; log rate failures to a viewer.
3) **Admin UX**: Add diagnostics dashboard (connectivity, webhook status, queue, recent errors), request-log viewer, and health checklist similar to Woo status screen.
4) **Size guides & templates**: Provide shortcode/block/tab for size charts and product templates if desired; optional assets downloader.
5) **REST & webhooks**: Expose REST endpoints for status/health and mapping management; add webhook signature failure alerts.
6) **Taxes & settings**: Mirror Woo toggles for tax sync/rounding where applicable.

Tracked gaps should stay in README until implemented. Update this file when a gap is closed.
