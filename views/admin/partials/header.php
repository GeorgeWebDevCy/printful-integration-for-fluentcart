<?php
defined('ABSPATH') || exit;

$pifcCurrentPage  = $pifc_current_page ?? 'settings';
$pifcPageTitle    = $pifc_page_title ?? __('Printful for FluentCart', 'printful-for-fluentcart');
$pifcPageIcon     = $pifc_page_icon ?? 'dashicons-store';
$pifcPageSubtitle = $pifc_page_subtitle ?? '';

$pifcMenuItems = [
    'settings' => ['label' => __('Settings', 'printful-for-fluentcart'), 'url' => admin_url('admin.php?page=pifc-settings')],
    'product-sync' => ['label' => __('Product Sync', 'printful-for-fluentcart'), 'url' => admin_url('admin.php?page=pifc-product-sync')],
    'orders' => ['label' => __('Orders', 'printful-for-fluentcart'), 'url' => admin_url('admin.php?page=pifc-orders')],
    'bulk-fulfill' => ['label' => __('Bulk Fulfill', 'printful-for-fluentcart'), 'url' => admin_url('admin.php?page=pifc-bulk-fulfill')],
    'catalog' => ['label' => __('Catalog', 'printful-for-fluentcart'), 'url' => admin_url('admin.php?page=pifc-catalog')],
    'shipping-setup' => ['label' => __('Shipping', 'printful-for-fluentcart'), 'url' => admin_url('admin.php?page=pifc-shipping-setup')],
];
?>

<div class="pifc-shell">
    <div class="pifc-shell__topbar">
        <div class="pifc-shell__brand">
            <a class="pifc-shell__brand-link" href="<?php echo esc_url(admin_url('admin.php?page=fluent-cart#/')); ?>">
                <span class="pifc-shell__brand-mark">P</span>
                <span class="pifc-shell__brand-text"><?php esc_html_e('Printful', 'printful-for-fluentcart'); ?></span>
            </a>
            <span class="pifc-shell__brand-context"><?php esc_html_e('inside FluentCart', 'printful-for-fluentcart'); ?></span>
        </div>

        <div class="pifc-shell__back">
            <a href="<?php echo esc_url(admin_url('admin.php?page=fluent-cart#/')); ?>">
                <?php esc_html_e('Back to FluentCart', 'printful-for-fluentcart'); ?>
            </a>
        </div>
    </div>

    <div class="pifc-shell__menu">
        <ul class="pifc-shell__menu-list">
            <?php foreach ($pifcMenuItems as $slug => $item) : ?>
                <li class="pifc-shell__menu-item">
                    <a class="pifc-shell__menu-link <?php echo $slug === $pifcCurrentPage ? 'is-active' : ''; ?>" href="<?php echo esc_url($item['url']); ?>">
                        <?php echo esc_html($item['label']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="pifc-shell__hero">
        <div class="pifc-shell__hero-icon dashicons <?php echo esc_attr($pifcPageIcon); ?>"></div>
        <div class="pifc-shell__hero-copy">
            <h1 class="pifc-shell__hero-title"><?php echo esc_html($pifcPageTitle); ?></h1>
            <?php if ($pifcPageSubtitle) : ?>
                <p class="pifc-shell__hero-subtitle"><?php echo esc_html($pifcPageSubtitle); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
