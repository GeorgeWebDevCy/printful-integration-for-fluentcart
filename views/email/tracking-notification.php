<?php
/**
 * Tracking notification email template.
 *
 * Available variables:
 *   $order    FluentCart\App\Models\Order
 *   $tracking array  { tracking_number, tracking_url, carrier, ship_date, shipped_at }
 */
defined('ABSPATH') || exit;

$siteName      = get_bloginfo('name');
$siteUrl       = home_url();
$accentColor   = apply_filters('pifc/email_accent_color', '#0073aa');
$trackingNo    = $tracking['tracking_number'] ?? '';
$trackingUrl   = $tracking['tracking_url']    ?? '';
$carrier       = $tracking['carrier']         ?? '';
$shipDate      = $tracking['ship_date']       ?? '';

$customerName  = '';
if ($order->customer) {
    $customerName = trim(
        ($order->customer->first_name ?? '') . ' ' .
        ($order->customer->last_name  ?? '')
    );
}
if ($customerName === '') {
    $customerName = __('there', 'printful-for-fluentcart');
}

$shippingAddress = $order->shipping_address ?? $order->billing_address ?? null;
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr(get_locale()); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html($siteName); ?></title>
<style>
  body { margin:0; padding:0; background:#f4f4f4; font-family: Arial, sans-serif; }
  .wrapper { max-width:600px; margin:0 auto; background:#ffffff; }
  .header  { background:<?php echo esc_attr($accentColor); ?>; padding:28px 40px; text-align:center; }
  .header h1 { color:#fff; margin:0; font-size:22px; font-weight:700; }
  .body    { padding:32px 40px; color:#333333; font-size:15px; line-height:1.6; }
  .body h2 { margin:0 0 16px; font-size:18px; color:#111111; }
  .tracking-box { background:#f9f9f9; border:1px solid #e0e0e0; border-radius:6px; padding:20px 24px; margin:24px 0; }
  .tracking-box .label { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#888; margin-bottom:3px; }
  .tracking-box .value { font-size:15px; font-weight:600; color:#111; margin-bottom:14px; }
  .tracking-box .value:last-child { margin-bottom:0; }
  .btn { display:inline-block; background:<?php echo esc_attr($accentColor); ?>; color:#ffffff !important; text-decoration:none; padding:12px 24px; border-radius:4px; font-size:15px; font-weight:600; margin:8px 0; }
  .address-block { font-size:13px; color:#555; line-height:1.5; }
  .footer  { padding:20px 40px; text-align:center; color:#aaa; font-size:12px; border-top:1px solid #eee; }
</style>
</head>
<body>
<div class="wrapper">

  <div class="header">
    <h1><?php echo esc_html($siteName); ?></h1>
  </div>

  <div class="body">
    <h2><?php
      printf(
        /* translators: %s: customer first name */
        esc_html__('Your order is on its way, %s!', 'printful-for-fluentcart'),
        esc_html($customerName)
      );
    ?></h2>

    <p><?php
      printf(
        /* translators: %s: order ID */
        esc_html__('Great news — your order #%s has been shipped and is heading your way.', 'printful-for-fluentcart'),
        esc_html($order->id)
      );
    ?></p>

    <div class="tracking-box">

      <?php if ($trackingNo): ?>
      <div class="label"><?php esc_html_e('Tracking Number', 'printful-for-fluentcart'); ?></div>
      <div class="value"><?php echo esc_html($trackingNo); ?></div>
      <?php endif; ?>

      <?php if ($carrier): ?>
      <div class="label"><?php esc_html_e('Carrier', 'printful-for-fluentcart'); ?></div>
      <div class="value"><?php echo esc_html($carrier); ?></div>
      <?php endif; ?>

      <?php if ($shipDate): ?>
      <div class="label"><?php esc_html_e('Ship Date', 'printful-for-fluentcart'); ?></div>
      <div class="value"><?php echo esc_html($shipDate); ?></div>
      <?php endif; ?>

      <?php if ($trackingUrl): ?>
      <p style="margin:16px 0 0;">
        <a href="<?php echo esc_url($trackingUrl); ?>" class="btn" target="_blank" rel="noopener noreferrer">
          <?php esc_html_e('Track Your Package', 'printful-for-fluentcart'); ?>
        </a>
      </p>
      <?php endif; ?>

    </div>

    <?php if ($shippingAddress): ?>
    <p><strong><?php esc_html_e('Shipping to:', 'printful-for-fluentcart'); ?></strong></p>
    <div class="address-block">
      <?php echo nl2br(esc_html(implode("\n", array_filter([
        $shippingAddress->name     ?? $shippingAddress->full_name ?? '',
        $shippingAddress->address_1 ?? '',
        $shippingAddress->address_2 ?? '',
        trim(implode(', ', array_filter([
          $shippingAddress->city     ?? '',
          $shippingAddress->state    ?? '',
          $shippingAddress->postcode ?? '',
        ]))),
        $shippingAddress->country ?? '',
      ])))); ?>
    </div>
    <?php endif; ?>

    <p style="margin-top:28px;">
      <?php esc_html_e('If you have any questions about your order, please reply to this email.', 'printful-for-fluentcart'); ?>
    </p>

    <p><?php
      printf(
        /* translators: %s: site name */
        esc_html__('Thank you for shopping with %s!', 'printful-for-fluentcart'),
        esc_html($siteName)
      );
    ?></p>
  </div>

  <div class="footer">
    <p>
      <a href="<?php echo esc_url($siteUrl); ?>"><?php echo esc_html($siteName); ?></a>
      &nbsp;·&nbsp;
      <?php esc_html_e('Fulfilled by Printful', 'printful-for-fluentcart'); ?>
    </p>
  </div>

</div>
</body>
</html>
