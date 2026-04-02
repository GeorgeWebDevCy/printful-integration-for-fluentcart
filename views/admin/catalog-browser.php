<?php defined('ABSPATH') || exit; ?>

<div class="wrap pifc-wrap">

    <h1 class="pifc-page-title">
        <span class="dashicons dashicons-search"></span>
        <?php esc_html_e('Printful Catalog Browser', 'printful-for-fluentcart'); ?>
    </h1>

    <div class="pifc-card">
        <h2><?php esc_html_e('Browse Products', 'printful-for-fluentcart'); ?></h2>
        <p>
            <?php esc_html_e(
                'Browse the Printful product catalog. When you find a product you want to sell, '
                . 'set it up in your Printful store first (add design, configure variants, set retail price), '
                . 'then use the Product Sync page to import it into FluentCart.',
                'printful-for-fluentcart'
            ); ?>
        </p>

        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-bottom:16px">
            <div>
                <label for="pifc-category-select"><strong><?php esc_html_e('Category', 'printful-for-fluentcart'); ?></strong></label><br>
                <select id="pifc-category-select" class="regular-text" disabled>
                    <option value=""><?php esc_html_e('— loading categories —', 'printful-for-fluentcart'); ?></option>
                </select>
            </div>
            <button type="button" id="pifc-load-categories" class="button button-secondary">
                <?php esc_html_e('Load Categories', 'printful-for-fluentcart'); ?>
            </button>
        </div>

        <div id="pifc-catalog-products" style="display:none">
            <h3 id="pifc-catalog-category-title"></h3>
            <div id="pifc-product-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:16px"></div>
        </div>
    </div>

    <!-- Product detail panel -->
    <div id="pifc-catalog-detail" class="pifc-card" style="display:none">
        <h2 id="pifc-detail-title"></h2>
        <div style="display:flex;gap:20px;flex-wrap:wrap">
            <div style="flex:0 0 200px">
                <img id="pifc-detail-image" src="" alt="" style="width:100%;border-radius:4px">
            </div>
            <div style="flex:1;min-width:200px">
                <p id="pifc-detail-desc" style="color:#50575e;font-size:13px"></p>
                <p>
                    <strong><?php esc_html_e('Brand:', 'printful-for-fluentcart'); ?></strong>
                    <span id="pifc-detail-brand"></span>
                </p>
                <p>
                    <strong><?php esc_html_e('Type:', 'printful-for-fluentcart'); ?></strong>
                    <span id="pifc-detail-type"></span>
                </p>
                <p id="pifc-detail-synced-note" style="display:none;color:#007a2f;font-weight:600">
                    <?php esc_html_e('✓ A product with this name is already in your Printful store.', 'printful-for-fluentcart'); ?>
                </p>
                <div class="pifc-actions" style="margin-top:12px">
                    <a id="pifc-open-printful" href="#" target="_blank" rel="noopener noreferrer" class="button button-primary">
                        <?php esc_html_e('Set Up in Printful Dashboard →', 'printful-for-fluentcart'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=pifc-product-sync')); ?>" class="button button-secondary">
                        <?php esc_html_e('Go to Product Sync', 'printful-for-fluentcart'); ?>
                    </a>
                </div>
            </div>
        </div>

        <h3><?php esc_html_e('Variants', 'printful-for-fluentcart'); ?> (<span id="pifc-variant-count">0</span>)</h3>
        <div class="pifc-orders-table-wrap">
            <table class="pifc-orders-table" id="pifc-variants-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name',  'printful-for-fluentcart'); ?></th>
                        <th><?php esc_html_e('Size',  'printful-for-fluentcart'); ?></th>
                        <th><?php esc_html_e('Color', 'printful-for-fluentcart'); ?></th>
                        <th><?php esc_html_e('Printful Base Price', 'printful-for-fluentcart'); ?></th>
                        <th><?php esc_html_e('In Stock', 'printful-for-fluentcart'); ?></th>
                    </tr>
                </thead>
                <tbody id="pifc-variants-tbody"></tbody>
            </table>
        </div>
    </div>

</div>

<script type="text/javascript">
jQuery(function ($) {

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Load categories
    $('#pifc-load-categories').on('click', function () {
        var $btn = $(this).prop('disabled', true).text(pifcAdmin.i18n.loading);

        $.post(pifcAdmin.ajaxUrl, { action: 'pifc_get_catalog_categories', nonce: pifcAdmin.nonce })
        .done(function (res) {
            if (!res.success) { alert(res.data.message); return; }

            var $sel = $('#pifc-category-select').empty().prop('disabled', false);
            $sel.append('<option value=""><?php echo esc_js(__('— select a category —', 'printful-for-fluentcart')); ?></option>');
            $.each(res.data.categories, function (i, cat) {
                $sel.append('<option value="' + cat.id + '">' + escHtml(cat.title) + '</option>');
            });
        })
        .fail(function () { alert('<?php echo esc_js(__('Request failed.', 'printful-for-fluentcart')); ?>'); })
        .always(function () { $btn.prop('disabled', false).text('<?php echo esc_js(__('Load Categories', 'printful-for-fluentcart')); ?>'); });
    });

    // Category selected → load products
    $('#pifc-category-select').on('change', function () {
        var catId = $(this).val();
        if (!catId) { $('#pifc-catalog-products').hide(); return; }

        var catLabel = $('option:selected', this).text();
        $('#pifc-catalog-category-title').text(catLabel);
        $('#pifc-product-grid').html('<p><?php echo esc_js(__('Loading…', 'printful-for-fluentcart')); ?></p>');
        $('#pifc-catalog-products').show();
        $('#pifc-catalog-detail').hide();

        $.post(pifcAdmin.ajaxUrl, { action: 'pifc_get_catalog_products', nonce: pifcAdmin.nonce, category_id: catId })
        .done(function (res) {
            if (!res.success) { $('#pifc-product-grid').html('<p>' + res.data.message + '</p>'); return; }

            var products = res.data.products;
            if (!products.length) {
                $('#pifc-product-grid').html('<p><?php echo esc_js(__('No products found in this category.', 'printful-for-fluentcart')); ?></p>');
                return;
            }

            var html = '';
            $.each(products, function (i, p) {
                html += '<div class="pifc-catalog-card" data-id="' + p.id + '" style="cursor:pointer;border:1px solid #dcdcde;border-radius:4px;padding:12px;text-align:center;background:#fff">' +
                    (p.image ? '<img src="' + escHtml(p.image) + '" alt="" style="width:100%;height:130px;object-fit:contain;margin-bottom:8px">' : '') +
                    '<strong style="font-size:12px;display:block">' + escHtml(p.model) + '</strong>' +
                    '<span style="font-size:11px;color:#50575e">' + escHtml(p.brand) + '</span><br>' +
                    '<span style="font-size:11px;color:#a7aaad">' + p.variant_count + ' variants</span>' +
                    '</div>';
            });
            $('#pifc-product-grid').html(html);
        })
        .fail(function () { $('#pifc-product-grid').html('<p><?php echo esc_js(__('Request failed.', 'printful-for-fluentcart')); ?></p>'); });
    });

    // Product card click → show detail
    $(document).on('click', '.pifc-catalog-card', function () {
        var productId = $(this).data('id');
        $('#pifc-catalog-detail').show().find('tbody').html('<tr><td colspan="5"><?php echo esc_js(__('Loading…', 'printful-for-fluentcart')); ?></td></tr>');

        $.post(pifcAdmin.ajaxUrl, { action: 'pifc_get_catalog_product', nonce: pifcAdmin.nonce, product_id: productId })
        .done(function (res) {
            if (!res.success) { alert(res.data.message); return; }

            var p = res.data.product;
            var variants = res.data.variants;

            $('#pifc-detail-title').text(p.model);
            $('#pifc-detail-image').attr('src', p.image).attr('alt', p.model);
            $('#pifc-detail-desc').text(p.description.replace(/<[^>]+>/g, '').substring(0, 200));
            $('#pifc-detail-brand').text(p.brand);
            $('#pifc-detail-type').text(p.type_name);
            $('#pifc-detail-synced-note').toggle(!!res.data.already_synced);
            $('#pifc-variant-count').text(variants.length);
            $('#pifc-open-printful').attr('href', 'https://www.printful.com/dashboard/store/products/add');

            var rows = '';
            $.each(variants, function (i, v) {
                rows += '<tr>' +
                    '<td>' + escHtml(v.name)  + '</td>' +
                    '<td>' + escHtml(v.size)  + '</td>' +
                    '<td>' + escHtml(v.color) + '</td>' +
                    '<td>$' + escHtml(v.price) + '</td>' +
                    '<td>' + (v.in_stock ? '✓' : '—') + '</td>' +
                    '</tr>';
            });
            $('#pifc-variants-tbody').html(rows);

            $('html,body').animate({ scrollTop: $('#pifc-catalog-detail').offset().top - 40 }, 300);
        })
        .fail(function () { alert('<?php echo esc_js(__('Request failed.', 'printful-for-fluentcart')); ?>'); });
    });
});
</script>
