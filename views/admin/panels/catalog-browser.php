<?php defined('ABSPATH') || exit; ?>

<div class="pifc-card">
    <h2><?php esc_html_e('Browse Products', 'printful-for-fluentcart'); ?></h2>
    <p><?php esc_html_e('Browse the Printful product catalog. When you find a product you want to sell, set it up in your Printful store first (add design, configure variants, set retail price), then use the Product Sync page to import it into FluentCart.', 'printful-for-fluentcart'); ?></p>
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-bottom:16px">
        <div>
            <label for="pifc-category-select"><strong><?php esc_html_e('Category', 'printful-for-fluentcart'); ?></strong></label><br>
            <select id="pifc-category-select" class="regular-text" disabled>
                <option value=""><?php esc_html_e('— loading categories —', 'printful-for-fluentcart'); ?></option>
            </select>
        </div>
        <button type="button" id="pifc-load-categories" class="button button-secondary"><?php esc_html_e('Load Categories', 'printful-for-fluentcart'); ?></button>
    </div>
    <div id="pifc-catalog-products" style="display:none">
        <h3 id="pifc-catalog-category-title"></h3>
        <div id="pifc-product-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:16px"></div>
    </div>
</div>

<div id="pifc-catalog-detail" class="pifc-card" style="display:none">
    <h2 id="pifc-detail-title"></h2>
    <div style="display:flex;gap:20px;flex-wrap:wrap">
        <div style="flex:0 0 200px"><img id="pifc-detail-image" src="" alt="" style="width:100%;border-radius:4px"></div>
        <div style="flex:1;min-width:200px">
            <p id="pifc-detail-desc" style="color:#50575e;font-size:13px"></p>
            <p><strong><?php esc_html_e('Brand:', 'printful-for-fluentcart'); ?></strong> <span id="pifc-detail-brand"></span></p>
            <p><strong><?php esc_html_e('Type:', 'printful-for-fluentcart'); ?></strong> <span id="pifc-detail-type"></span></p>
            <p id="pifc-detail-synced-note" style="display:none;color:#007a2f;font-weight:600"><?php esc_html_e('A product with this name is already in your Printful store.', 'printful-for-fluentcart'); ?></p>
            <div class="pifc-actions" style="margin-top:12px">
                <a id="pifc-open-printful" href="#" target="_blank" rel="noopener noreferrer" class="button button-primary"><?php esc_html_e('Set Up in Printful Dashboard →', 'printful-for-fluentcart'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=fluent-cart#/integrations/printful?view=sync')); ?>" class="button button-secondary"><?php esc_html_e('Go to Product Sync', 'printful-for-fluentcart'); ?></a>
            </div>
        </div>
    </div>
    <h3><?php esc_html_e('Variants', 'printful-for-fluentcart'); ?> (<span id="pifc-variant-count">0</span>)</h3>
    <div class="pifc-orders-table-wrap">
        <table class="pifc-orders-table" id="pifc-variants-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'printful-for-fluentcart'); ?></th>
                    <th><?php esc_html_e('Size', 'printful-for-fluentcart'); ?></th>
                    <th><?php esc_html_e('Color', 'printful-for-fluentcart'); ?></th>
                    <th><?php esc_html_e('Printful Base Price', 'printful-for-fluentcart'); ?></th>
                    <th><?php esc_html_e('In Stock', 'printful-for-fluentcart'); ?></th>
                </tr>
            </thead>
            <tbody id="pifc-variants-tbody"></tbody>
        </table>
    </div>
</div>

<script>
window.pifcAdminInitPanel && window.pifcAdminInitPanel('catalog');
</script>
