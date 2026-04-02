<?php
/**
 * Thank You / receipt page: Printful fulfillment notice.
 *
 * Variables available (set by CustomerPortalService::injectThankYouTracking):
 *   $order          FluentCart\App\Models\Order
 *   $printfulStatus string
 *   $tracking       array   keys: tracking_number, tracking_url, carrier, ship_date
 */
defined('ABSPATH') || exit;

$trackingNumber = $tracking['tracking_number'] ?? '';
$trackingUrl    = $tracking['tracking_url']    ?? '';
$carrier        = $tracking['carrier']         ?? '';

// Only render a shipped notice if we actually have shipment data; otherwise
// show a "being prepared" message to reassure the customer.
$hasTracking = !empty($trackingNumber);
?>
<div class="pifc-thankyou-fulfillment" style="margin:20px 0;padding:14px 18px;border-left:4px solid <?php echo $hasTracking ? '#22c55e' : '#3b82f6'; ?>;background:<?php echo $hasTracking ? '#f0fdf4' : '#eff6ff'; ?>;border-radius:0 6px 6px 0;">

    <?php if ($hasTracking): ?>

        <p style="margin:0 0 6px;font-size:14px;font-weight:600;color:#15803d;">
            <?php esc_html_e('Your order has shipped!', 'printful-for-fluentcart'); ?>
        </p>
        <p style="margin:0;font-size:13px;color:#166534;">
            <?php if ($carrier): ?>
                <?php echo esc_html(sprintf(
                    /* translators: %s: carrier name */
                    __('Shipped via %s.', 'printful-for-fluentcart'),
                    $carrier
                )); ?>
            <?php endif; ?>
            <?php if ($trackingUrl): ?>
                <a href="<?php echo esc_url($trackingUrl); ?>" target="_blank" rel="noopener noreferrer" style="color:#15803d;">
                    <?php echo esc_html(sprintf(
                        /* translators: %s: tracking number */
                        __('Track your package: %s', 'printful-for-fluentcart'),
                        $trackingNumber
                    )); ?>
                </a>
            <?php else: ?>
                <?php echo esc_html(sprintf(
                    /* translators: %s: tracking number */
                    __('Tracking number: %s', 'printful-for-fluentcart'),
                    $trackingNumber
                )); ?>
            <?php endif; ?>
        </p>

    <?php else: ?>

        <p style="margin:0 0 4px;font-size:14px;font-weight:600;color:#1d4ed8;">
            <?php esc_html_e('Your order is being prepared by Printful', 'printful-for-fluentcart'); ?>
        </p>
        <p style="margin:0;font-size:13px;color:#1e40af;">
            <?php esc_html_e('You will receive a shipping confirmation email with a tracking number once your order ships.', 'printful-for-fluentcart'); ?>
        </p>

    <?php endif; ?>

</div>
