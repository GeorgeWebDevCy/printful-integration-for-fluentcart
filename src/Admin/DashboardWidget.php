<?php

namespace PrintfulForFluentCart\Admin;

use FluentCart\App\Helpers\Status;
use FluentCart\App\Models\Order;
use FluentCart\App\Models\OrderMeta;

defined('ABSPATH') || exit;

/**
 * Registers a WordPress admin dashboard widget showing a live snapshot
 * of Printful fulfillment activity for the connected store.
 */
class DashboardWidget
{
    public function register()
    {
        wp_add_dashboard_widget(
            'pifc_fulfillment_widget',
            __('Printful Fulfillment Status', 'printful-for-fluentcart'),
            [$this, 'render']
        );
    }

    public function render()
    {
        $settings = get_option('pifc_settings', []);

        if (empty($settings['api_key'])) {
            echo '<p>';
            printf(
                /* translators: %s: settings page link */
                esc_html__('No API key configured. %s to get started.', 'printful-for-fluentcart'),
                '<a href="' . esc_url(admin_url('admin.php?page=pifc-settings')) . '">'
                    . esc_html__('Configure Printful', 'printful-for-fluentcart')
                    . '</a>'
            );
            echo '</p>';
            return;
        }

        $stats = $this->getStats();
        ?>
        <div class="pifc-widget">
            <ul class="pifc-widget-stats">
                <li class="pifc-stat">
                    <span class="pifc-stat-value"><?php echo (int) $stats['pending']; ?></span>
                    <span class="pifc-stat-label"><?php esc_html_e('Awaiting Fulfillment', 'printful-for-fluentcart'); ?></span>
                </li>
                <li class="pifc-stat">
                    <span class="pifc-stat-value pifc-stat-good"><?php echo (int) $stats['shipped_today']; ?></span>
                    <span class="pifc-stat-label"><?php esc_html_e('Shipped Today', 'printful-for-fluentcart'); ?></span>
                </li>
                <li class="pifc-stat">
                    <span class="pifc-stat-value pifc-stat-good"><?php echo (int) $stats['total_fulfilled']; ?></span>
                    <span class="pifc-stat-label"><?php esc_html_e('Total Fulfilled', 'printful-for-fluentcart'); ?></span>
                </li>
                <li class="pifc-stat">
                    <span class="pifc-stat-value pifc-stat-bad"><?php echo (int) $stats['failed']; ?></span>
                    <span class="pifc-stat-label"><?php esc_html_e('Failed / Needs Attention', 'printful-for-fluentcart'); ?></span>
                </li>
                <li class="pifc-stat">
                    <span class="pifc-stat-value"><?php echo (int) $stats['needs_manual_return']; ?></span>
                    <span class="pifc-stat-label"><?php esc_html_e('Awaiting Manual Return', 'printful-for-fluentcart'); ?></span>
                </li>
            </ul>

            <div class="pifc-widget-links">
                <a href="<?php echo esc_url(admin_url('admin.php?page=pifc-orders')); ?>">
                    <?php esc_html_e('View Printful Orders ->', 'printful-for-fluentcart'); ?>
                </a>
                &nbsp;|&nbsp;
                <a href="<?php echo esc_url(admin_url('admin.php?page=pifc-bulk-fulfill')); ?>">
                    <?php esc_html_e('Bulk Fulfill ->', 'printful-for-fluentcart'); ?>
                </a>
            </div>

            <?php if (!empty($settings['test_mode'])): ?>
            <p style="color:#9a6700;font-weight:600;margin-top:8px">
                <?php esc_html_e('Test / Draft mode is active.', 'printful-for-fluentcart'); ?>
            </p>
            <?php endif; ?>
        </div>

        <style>
        .pifc-widget-stats { margin:0; padding:0; list-style:none; display:flex; flex-wrap:wrap; gap:12px; }
        .pifc-stat { flex:1 1 90px; background:#f6f7f7; border:1px solid #dcdcde; border-radius:4px; padding:10px 14px; text-align:center; }
        .pifc-stat-value { display:block; font-size:26px; font-weight:700; line-height:1; }
        .pifc-stat-label { display:block; font-size:11px; color:#50575e; margin-top:4px; }
        .pifc-stat-good  { color:#007a2f; }
        .pifc-stat-bad   { color:#b32d2e; }
        .pifc-widget-links { margin-top:12px; font-size:13px; }
        </style>
        <?php
    }

    /**
     * @return array
     */
    private function getStats()
    {
        $cacheKey = 'pifc_dashboard_stats';
        $cached   = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $fulfilledMetas = OrderMeta::where('meta_key', '_printful_order_id')->get();
        $fulfilledIds   = $fulfilledMetas->pluck('order_id')->toArray();

        $pendingCount = Order::where('payment_status', Status::PAYMENT_PAID)
            ->when(!empty($fulfilledIds), function ($q) use ($fulfilledIds) {
                $q->whereNotIn('id', $fulfilledIds);
            })
            ->count();

        $todayStart    = date('Y-m-d 00:00:00');
        $shippedToday  = OrderMeta::where('meta_key', '_printful_order_status')
            ->where('meta_value', 'fulfilled')
            ->whereDate('updated_at', '>=', $todayStart)
            ->count();

        $totalFulfilled = OrderMeta::where('meta_key', '_printful_order_status')
            ->whereIn('meta_value', ['fulfilled', 'shipped', 'pending', 'in_process'])
            ->count();

        $failed = OrderMeta::where('meta_key', '_printful_order_status')
            ->where('meta_value', 'failed')
            ->count();

        $needsReturn = OrderMeta::where('meta_key', '_printful_needs_manual_return')
            ->where('meta_value', '1')
            ->count();

        $stats = [
            'pending'             => $pendingCount,
            'shipped_today'       => $shippedToday,
            'total_fulfilled'     => $totalFulfilled,
            'failed'              => $failed,
            'needs_manual_return' => $needsReturn,
        ];

        set_transient($cacheKey, $stats, 5 * MINUTE_IN_SECONDS);

        return $stats;
    }
}
