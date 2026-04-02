<?php
defined('ABSPATH') || exit;

$pifcCurrentPage  = $pifc_current_page ?? 'settings';
$pifcPageTitle    = $pifc_page_title ?? __('Printful for FluentCart', 'printful-for-fluentcart');
$pifcPageSubtitle = $pifc_page_subtitle ?? '';

$pifcMenuItems = [
    'settings' => ['label' => __('Native Integration', 'printful-for-fluentcart'), 'url' => admin_url('admin.php?page=fluent-cart#/integrations/printful')],
    'advanced-settings' => ['label' => __('Advanced Settings', 'printful-for-fluentcart'), 'url' => admin_url('admin.php?page=pifc-advanced')],
    'product-sync' => ['label' => __('Product Sync', 'printful-for-fluentcart'), 'url' => admin_url('admin.php?page=pifc-product-sync')],
    'orders' => ['label' => __('Orders', 'printful-for-fluentcart'), 'url' => admin_url('admin.php?page=pifc-orders')],
    'bulk-fulfill' => ['label' => __('Bulk Fulfill', 'printful-for-fluentcart'), 'url' => admin_url('admin.php?page=pifc-bulk-fulfill')],
    'catalog' => ['label' => __('Catalog Browser', 'printful-for-fluentcart'), 'url' => admin_url('admin.php?page=pifc-catalog')],
    'shipping-setup' => ['label' => __('Shipping Setup', 'printful-for-fluentcart'), 'url' => admin_url('admin.php?page=pifc-shipping-setup')],
];
?>
<div class="pifc-admin-shell">
    <div class="pifc-admin-shell-header">
        <div>
            <div class="pifc-admin-eyebrow"><?php esc_html_e('Printful Tools', 'printful-for-fluentcart'); ?></div>
            <h1 class="pifc-admin-title"><?php echo esc_html($pifcPageTitle); ?></h1>
            <?php if ($pifcPageSubtitle) : ?>
                <p class="pifc-admin-subtitle"><?php echo esc_html($pifcPageSubtitle); ?></p>
            <?php endif; ?>
        </div>
        <div class="pifc-admin-shell-actions">
            <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=fluent-cart#/integrations/printful')); ?>">
                <?php esc_html_e('Open Native Integration', 'printful-for-fluentcart'); ?>
            </a>
        </div>
    </div>
    <div class="pifc-admin-shell-nav">
        <?php foreach ($pifcMenuItems as $slug => $item) : ?>
            <a class="pifc-admin-nav-link <?php echo $slug === $pifcCurrentPage ? 'is-active' : ''; ?>" href="<?php echo esc_url($item['url']); ?>">
                <?php echo esc_html($item['label']); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="pifc-admin-shell-content">
