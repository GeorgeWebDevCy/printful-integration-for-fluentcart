# REST API

All endpoints live under the `printful-fluentcart/v1` namespace and require an authenticated user with `manage_options` capability unless otherwise stated.

## Health and configuration
- `GET /health` – status summary including webhook and queue visibility.
- `GET /config` – public configuration snapshot (polling, rates, origins, etc.).
- `GET /tax-status` – tax helper toggles.
- `GET /logs` – recent request log entries with optional search/level filters.
- `GET /settings` – current settings (secret/API key omitted).
- `GET /mappings` – current variant mappings.
- `GET /products` – mapped products with Printful metadata.
- `GET /products/{id}/variant-map` – stored variant mapping for a product.
- `GET /status` – diagnostics snapshot for external monitors.
- `GET /status-checklist` – health checklist items.
- `POST /migrate-tokens` – trigger legacy token migration; accepts `{ "dry_run": true }` for discovery only.

## Settings mutations
- `POST /settings` – upsert allowed keys (e.g., `enable_webhooks`, `webhook_secret`, origins, carrier lists, tax toggles). Payload is a partial settings object; unrecognised keys are ignored.
- `DELETE /settings/{key}` – remove a stored setting key (defaults will still apply when read).

## Mapping mutations
- `POST /mappings/{variation_id}` – create a variant mapping by storing `printful_variant_id` against a FluentCart variation ID.
- `PUT /mappings/{variation_id}` – update an existing mapping; body matches the create call.
- `DELETE /mappings/{variation_id}` – remove a stored mapping for the provided variation.

## Product mutations
- `PATCH /products/{product_id}` – update Printful metadata for a FluentCart product. Accepts partial payloads such as `printful_product_id`, `fulfilment_mode`, `service_code`, `origin_index`, and `mockup_url`/`designer_url` overrides.
- `DELETE /products/{product_id}` – clear Printful mapping and related per-product metadata.

> Tip: the existing `settings`, `mappings`, and `products` GET endpoints remain available for read-only consumers.
