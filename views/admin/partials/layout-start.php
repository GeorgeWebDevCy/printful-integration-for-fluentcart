<?php
defined('ABSPATH') || exit;

$pifcCurrentPage  = $pifc_current_page ?? 'settings';
$pifcPageTitle    = $pifc_page_title ?? __('Printful for FluentCart', 'printful-for-fluentcart');
$pifcPageSubtitle = $pifc_page_subtitle ?? '';

$pifcMenuItems = [
    'settings' => ['label' => __('Settings', 'printful-for-fluentcart'), 'url' => admin_url('admin.php?page=pifc-settings')],
    'product-sync' => ['label' => __('Product Sync', 'printful-for-fluentcart'), 'url' => admin_url('admin.php?page=pifc-product-sync')],
    'orders' => ['label' => __('Orders', 'printful-for-fluentcart'), 'url' => admin_url('admin.php?page=pifc-orders')],
    'bulk-fulfill' => ['label' => __('Bulk Fulfill', 'printful-for-fluentcart'), 'url' => admin_url('admin.php?page=pifc-bulk-fulfill')],
    'catalog' => ['label' => __('Catalog Browser', 'printful-for-fluentcart'), 'url' => admin_url('admin.php?page=pifc-catalog')],
    'shipping-setup' => ['label' => __('Shipping Setup', 'printful-for-fluentcart'), 'url' => admin_url('admin.php?page=pifc-shipping-setup')],
];
?>
<div id="fct_admin_app_wrapper" class="pifc-fct-app-wrapper">
    <div id="fct_admin_menu_holder">
        <?php do_action('fluent_cart/admin_menu'); ?>
    </div>
    <div id="fluent_cart_plugin_app" class="warp fconnector_app fct_settings_page_plugin_app_wrap">
        <div class="fl_app fluent-cart-admin-pages">
            <div class="fct-setting-container setting-container pifc-setting-container">
                <div class="fct-settings-nav-wrap pifc-settings-nav-wrap">
                    <div class="fct-settings-nav-container">
                        <ul class="fct-settings-nav">
                            <?php foreach ($pifcMenuItems as $slug => $item) : ?>
                                <li class="fct-settings-nav-item <?php echo $slug === $pifcCurrentPage ? 'fct-settings-nav-item-active' : ''; ?>">
                                    <a class="fct-settings-nav-link" href="<?php echo esc_url($item['url']); ?>">
                                        <span class="fct-settings-nav-link-text"><?php echo esc_html($item['label']); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <div class="fct-settings-nav-content">
                    <div class="fct-settings-nav-content-inner">
                        <div class="setting-wrap pifc-setting-wrap">
                            <div class="fct-setting-header">
                                <div class="fct-setting-header-content">
                                    <h3 class="fct-setting-head-title"><?php echo esc_html($pifcPageTitle); ?></h3>
                                </div>
                            </div>
                            <?php if ($pifcPageSubtitle) : ?>
                                <div class="pifc-setting-subtitle"><?php echo esc_html($pifcPageSubtitle); ?></div>
                            <?php endif; ?>
                            <div class="setting-wrap-inner">
