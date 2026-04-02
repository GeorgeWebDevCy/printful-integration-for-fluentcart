<?php

namespace PrintfulForFluentCart\Cli;

use FluentCart\App\Models\Order;
use FluentCart\App\Models\OrderMeta;
use FluentCart\App\Helpers\Status;
use PrintfulForFluentCart\Api\PrintfulClient;
use PrintfulForFluentCart\Services\OrderFulfillmentService;
use PrintfulForFluentCart\Services\ProductSyncService;

defined('ABSPATH') || exit;

/**
 * WP-CLI commands for the Printful FluentCart integration.
 *
 * Usage
 * ──────
 *   wp pifc status
 *   wp pifc sync-products [--dry-run]
 *   wp pifc sync-product <id>
 *   wp pifc fulfill-order <order_id> [--confirm]
 *   wp pifc list-orders [--status=<status>] [--limit=<n>]
 *   wp pifc cancel-fulfillment <order_id>
 */
class CliCommands
{
    // ─── wp pifc status ───────────────────────────────────────────────────────

    /**
     * Show the Printful integration status and connection info.
     *
     * ## EXAMPLES
     *
     *     wp pifc status
     *
     * @when after_wp_load
     */
    public function status()
    {
        $settings = get_option('pifc_settings', []);
        $apiKey   = $settings['api_key'] ?? '';

        \WP_CLI::line('');
        \WP_CLI::line('Printful Integration for FluentCart v' . PIFC_VERSION);
        \WP_CLI::line(str_repeat('─', 50));

        if (empty($apiKey)) {
            \WP_CLI::warning('No API key configured.');
            return;
        }

        \WP_CLI::line('API Key     : ' . substr($apiKey, 0, 6) . '…' . substr($apiKey, -4));
        \WP_CLI::line('Auto-Fulfill: ' . (!empty($settings['auto_fulfill']) ? 'yes' : 'no'));
        \WP_CLI::line('Auto-Confirm: ' . (!empty($settings['auto_confirm']) ? 'yes' : 'no'));
        \WP_CLI::line('Test Mode   : ' . (!empty($settings['test_mode'])    ? 'YES (no charges)' : 'no'));

        // Test API connection
        $client = new PrintfulClient($apiKey);
        $store  = $client->getStore();

        if (is_wp_error($store)) {
            \WP_CLI::error('API connection failed: ' . $store->get_error_message(), false);
        } else {
            \WP_CLI::success('Connected to Printful store: ' . ($store['name'] ?? 'unknown'));
        }

        // Quick stats
        $total    = OrderMeta::where('meta_key', '_printful_order_id')->count();
        $failed   = OrderMeta::where('meta_key', '_printful_order_status')->where('meta_value', 'failed')->count();
        $shipped  = OrderMeta::where('meta_key', '_printful_order_status')->where('meta_value', 'fulfilled')->count();

        \WP_CLI::line('');
        \WP_CLI::line("Total orders sent to Printful : {$total}");
        \WP_CLI::line("Shipped                       : {$shipped}");
        \WP_CLI::line("Failed                        : {$failed}");
        \WP_CLI::line('');
    }

    // ─── wp pifc sync-products ────────────────────────────────────────────────

    /**
     * Sync all products from the connected Printful store into FluentCart.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Show what would be synced without making any changes.
     *
     * ## EXAMPLES
     *
     *     wp pifc sync-products
     *     wp pifc sync-products --dry-run
     *
     * @when after_wp_load
     * @param array $args
     * @param array $assocArgs
     */
    public function sync_products($args, $assocArgs)
    {
        $dryRun = !empty($assocArgs['dry-run']);

        if ($dryRun) {
            \WP_CLI::line('Dry-run mode — no changes will be made.');
            $client   = new PrintfulClient();
            $products = $client->getProducts(0, 100);

            if (is_wp_error($products)) {
                \WP_CLI::error($products->get_error_message());
            }

            \WP_CLI::success(sprintf('Found %d product(s) in your Printful store.', count($products)));
            return;
        }

        \WP_CLI::line('Syncing products from Printful…');

        $service = new ProductSyncService();
        $results = $service->syncAllProducts();

        \WP_CLI::success(sprintf(
            'Done — %d created, %d updated.',
            $results['created'],
            $results['updated']
        ));

        if (!empty($results['errors'])) {
            foreach ($results['errors'] as $err) {
                \WP_CLI::warning($err);
            }
        }
    }

    // ─── wp pifc sync-product <id> ────────────────────────────────────────────

    /**
     * Sync a single product by its Printful sync-product ID.
     *
     * ## OPTIONS
     *
     * <id>
     * : The Printful sync-product ID.
     *
     * ## EXAMPLES
     *
     *     wp pifc sync-product 123456789
     *
     * @when after_wp_load
     * @param array $args
     */
    public function sync_product($args)
    {
        $productId = (int) ($args[0] ?? 0);

        if (!$productId) {
            \WP_CLI::error('Please provide a Printful product ID.');
        }

        \WP_CLI::line("Syncing Printful product #{$productId}…");

        $service = new ProductSyncService();
        $result  = $service->syncProduct($productId);

        if (is_wp_error($result)) {
            \WP_CLI::error($result->get_error_message());
        }

        \WP_CLI::success("Product #{$productId} {$result}.");
    }

    // ─── wp pifc fulfill-order <order_id> ────────────────────────────────────

    /**
     * Send a FluentCart order to Printful for fulfillment.
     *
     * ## OPTIONS
     *
     * <order_id>
     * : The FluentCart order ID.
     *
     * [--confirm]
     * : Immediately confirm the Printful order (triggers production + billing).
     *
     * ## EXAMPLES
     *
     *     wp pifc fulfill-order 42
     *     wp pifc fulfill-order 42 --confirm
     *
     * @when after_wp_load
     * @param array $args
     * @param array $assocArgs
     */
    public function fulfill_order($args, $assocArgs)
    {
        $orderId = (int) ($args[0] ?? 0);

        if (!$orderId) {
            \WP_CLI::error('Please provide a FluentCart order ID.');
        }

        $order = Order::find($orderId);

        if (!$order) {
            \WP_CLI::error("Order #{$orderId} not found.");
        }

        if ($order->getMeta('_printful_order_id')) {
            \WP_CLI::warning("Order #{$orderId} is already fulfilled (Printful #{$order->getMeta('_printful_order_id')}).");
            return;
        }

        \WP_CLI::line("Sending order #{$orderId} to Printful…");

        $service = new OrderFulfillmentService();
        $result  = $service->fulfillOrder($order);

        if (is_wp_error($result)) {
            \WP_CLI::error($result->get_error_message());
        }

        $printfulId = $result['id'] ?? '?';
        \WP_CLI::success("Order #{$orderId} sent. Printful order #{$printfulId}.");

        if (!empty($assocArgs['confirm'])) {
            $client    = new PrintfulClient();
            $confirmed = $client->confirmOrder((int) $printfulId);

            if (is_wp_error($confirmed)) {
                \WP_CLI::warning('Confirmation failed: ' . $confirmed->get_error_message());
            } else {
                \WP_CLI::success("Printful order #{$printfulId} confirmed (production started).");
            }
        }
    }

    // ─── wp pifc cancel-fulfillment <order_id> ───────────────────────────────

    /**
     * Cancel a Printful order for a given FluentCart order.
     *
     * ## OPTIONS
     *
     * <order_id>
     * : The FluentCart order ID.
     *
     * ## EXAMPLES
     *
     *     wp pifc cancel-fulfillment 42
     *
     * @when after_wp_load
     * @param array $args
     */
    public function cancel_fulfillment($args)
    {
        $orderId = (int) ($args[0] ?? 0);

        if (!$orderId) {
            \WP_CLI::error('Please provide a FluentCart order ID.');
        }

        $order = Order::find($orderId);

        if (!$order) {
            \WP_CLI::error("Order #{$orderId} not found.");
        }

        $service = new OrderFulfillmentService();
        $result  = $service->cancelFulfillment($order);

        if (is_wp_error($result)) {
            \WP_CLI::error($result->get_error_message());
        }

        \WP_CLI::success("Fulfillment for order #{$orderId} canceled.");
    }

    // ─── wp pifc list-orders ──────────────────────────────────────────────────

    /**
     * List FluentCart orders and their Printful fulfillment status.
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Filter by Printful status (draft, pending, fulfilled, failed, canceled, shipped).
     *
     * [--limit=<n>]
     * : Number of orders to show. Default: 25.
     *
     * ## EXAMPLES
     *
     *     wp pifc list-orders
     *     wp pifc list-orders --status=failed
     *     wp pifc list-orders --limit=50
     *
     * @when after_wp_load
     * @param array $args
     * @param array $assocArgs
     */
    public function list_orders($args, $assocArgs)
    {
        $limit        = max(1, (int) ($assocArgs['limit'] ?? 25));
        $statusFilter = $assocArgs['status'] ?? '';

        $query = OrderMeta::where('meta_key', '_printful_order_id')
            ->orderBy('order_id', 'desc')
            ->limit($limit);

        $metas = $query->get();

        if ($metas->isEmpty()) {
            \WP_CLI::line('No orders with Printful fulfillment data found.');
            return;
        }

        $rows = [];

        foreach ($metas as $meta) {
            $order = Order::find($meta->order_id);
            if (!$order) continue;

            $pStatus  = $order->getMeta('_printful_order_status', '');
            $tracking = $order->getMeta('_printful_tracking', []);
            $trackNo  = is_array($tracking) ? ($tracking['tracking_number'] ?? '') : '';

            if ($statusFilter && $pStatus !== $statusFilter) {
                continue;
            }

            $rows[] = [
                'FC Order'       => '#' . $order->id,
                'Printful #'     => '#' . $meta->meta_value,
                'P. Status'      => $pStatus ?: '—',
                'FC Status'      => $order->status ?? '—',
                'Tracking'       => $trackNo ?: '—',
            ];
        }

        if (empty($rows)) {
            \WP_CLI::line('No orders match the given filter.');
            return;
        }

        \WP_CLI\Utils\format_items('table', $rows, array_keys($rows[0]));
    }
}
