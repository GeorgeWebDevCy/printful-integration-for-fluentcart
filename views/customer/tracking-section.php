<?php
/**
 * Customer portal order-detail: Printful tracking block.
 *
 * Variables available (set by CustomerPortalService::injectPortalTracking):
 *   $order          FluentCart\App\Models\Order
 *   $printfulStatus string  e.g. 'draft', 'pending', 'fulfilled', 'shipped'
 *   $tracking       array   keys: tracking_number, tracking_url, carrier, ship_date
 */
defined('ABSPATH') || exit;

$trackingNumber = $tracking['tracking_number'] ?? '';
$trackingUrl    = $tracking['tracking_url']    ?? '';
$carrier        = $tracking['carrier']         ?? '';
$shipDate       = $tracking['ship_date']       ?? '';
?>
<div class="pifc-portal-tracking" style="margin-top:24px;padding:16px 20px;border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc;">
    <h4 style="margin:0 0 12px;font-size:14px;font-weight:600;color:#1e293b;">
        <?php esc_html_e('Printful Fulfillment', 'printful-for-fluentcart'); ?>
    </h4>

    <p style="margin:0 0 6px;font-size:13px;color:#475569;">
        <strong><?php esc_html_e('Status:', 'printful-for-fluentcart'); ?></strong>
        <?php echo esc_html(ucfirst($printfulStatus ?: __('Processing', 'printful-for-fluentcart'))); ?>
    </p>

    <?php if ($carrier): ?>
    <p style="margin:0 0 6px;font-size:13px;color:#475569;">
        <strong><?php esc_html_e('Carrier:', 'printful-for-fluentcart'); ?></strong>
        <?php echo esc_html($carrier); ?>
    </p>
    <?php endif; ?>

    <?php if ($trackingNumber): ?>
    <p style="margin:0 0 6px;font-size:13px;color:#475569;">
        <strong><?php esc_html_e('Tracking Number:', 'printful-for-fluentcart'); ?></strong>
        <?php if ($trackingUrl): ?>
            <a href="<?php echo esc_url($trackingUrl); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html($trackingNumber); ?>
            </a>
        <?php else: ?>
            <?php echo esc_html($trackingNumber); ?>
        <?php endif; ?>
    </p>
    <?php endif; ?>

    <?php if ($shipDate): ?>
    <p style="margin:0;font-size:13px;color:#475569;">
        <strong><?php esc_html_e('Ship Date:', 'printful-for-fluentcart'); ?></strong>
        <?php echo esc_html($shipDate); ?>
    </p>
    <?php endif; ?>
</div>
