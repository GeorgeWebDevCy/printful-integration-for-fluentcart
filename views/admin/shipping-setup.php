<?php
defined('ABSPATH') || exit;

$savedServices = get_option(\PrintfulForFluentCart\Admin\ShippingSetupPage::OPTION_KEY, []);
$services      = \PrintfulForFluentCart\Admin\ShippingSetupPage::SERVICES;
?>

<div class="wrap pifc-wrap">
    <?php
    $pifc_current_page = 'shipping-setup';
    $pifc_page_title = __('Shipping Setup', 'printful-for-fluentcart');
    $pifc_page_subtitle = __('Map Printful shipping services into FluentCart zones and keep rate selection predictable.', 'printful-for-fluentcart');
    include __DIR__ . '/partials/layout-start.php';
    ?>

    <div class="pifc-card">
        <h2><?php esc_html_e('Shipping Services', 'printful-for-fluentcart'); ?></h2>
        <p>
            <?php esc_html_e(
                'Enable the Printful shipping services you want to offer at checkout. '
                . 'For each enabled service a FluentCart shipping method is created (or updated). '
                . 'The actual rate is fetched live from Printful at checkout — the amount shown '
                . 'in FluentCart\'s shipping zone settings is a placeholder only.',
                'printful-for-fluentcart'
            ); ?>
        </p>

        <?php if (empty($zones)): ?>
        <div class="notice notice-warning inline" style="margin:0 0 16px">
            <p><?php esc_html_e('No FluentCart shipping zones found. Create a shipping zone in FluentCart → Settings → Shipping first.', 'printful-for-fluentcart'); ?></p>
        </div>
        <?php endif; ?>

        <table class="form-table pifc-services-table" role="presentation">
            <thead>
                <tr>
                    <th style="width:30px"><?php esc_html_e('Enable', 'printful-for-fluentcart'); ?></th>
                    <th><?php esc_html_e('Service Code', 'printful-for-fluentcart'); ?></th>
                    <th><?php esc_html_e('Display Title', 'printful-for-fluentcart'); ?></th>
                    <th><?php esc_html_e('Shipping Zone', 'printful-for-fluentcart'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($services as $code => $defaultTitle):
                $svc     = $savedServices[$code] ?? [];
                $enabled = !empty($svc['enabled']);
                $title   = $svc['title']   ?? $defaultTitle;
                $zoneId  = (int) ($svc['zone_id'] ?? 0);
            ?>
            <tr>
                <td>
                    <input type="checkbox"
                        class="pifc-svc-enabled"
                        data-code="<?php echo esc_attr($code); ?>"
                        <?php checked($enabled); ?>>
                </td>
                <td><code><?php echo esc_html($code); ?></code></td>
                <td>
                    <input type="text"
                        class="pifc-svc-title regular-text"
                        data-code="<?php echo esc_attr($code); ?>"
                        value="<?php echo esc_attr($title); ?>">
                </td>
                <td>
                    <select class="pifc-svc-zone" data-code="<?php echo esc_attr($code); ?>">
                        <option value="0"><?php esc_html_e('— any zone —', 'printful-for-fluentcart'); ?></option>
                        <?php foreach ($zones as $zone): ?>
                        <option value="<?php echo (int) $zone['id']; ?>"
                            <?php selected($zoneId, (int) $zone['id']); ?>>
                            <?php echo esc_html($zone['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p class="submit">
            <button type="button" id="pifc-save-shipping" class="button button-primary">
                <?php esc_html_e('Save & Sync to FluentCart', 'printful-for-fluentcart'); ?>
            </button>
            <span id="pifc-shipping-status"></span>
        </p>
    </div>

    <div class="pifc-card">
        <h2><?php esc_html_e('How Live Rates Work', 'printful-for-fluentcart'); ?></h2>
        <ol>
            <li><?php esc_html_e('Enable the services above and click Save.', 'printful-for-fluentcart'); ?></li>
            <li><?php esc_html_e('FluentCart shipping methods are created for each enabled service.', 'printful-for-fluentcart'); ?></li>
            <li><?php esc_html_e('At checkout, when the customer enters their address, live rates are fetched from Printful.', 'printful-for-fluentcart'); ?></li>
            <li><?php esc_html_e('If the customer selects a specific Printful method, that service\'s rate is used. Otherwise the cheapest rate is applied automatically.', 'printful-for-fluentcart'); ?></li>
        </ol>
    </div>

    <?php include __DIR__ . '/partials/layout-end.php'; ?>
</div>

<script type="text/javascript">
jQuery(function ($) {

    function buildPayload() {
        var services = {};
        $('.pifc-svc-enabled').each(function () {
            var code = $(this).data('code');
            services[code] = {
                enabled: $(this).is(':checked') ? 1 : 0,
                title:   $('[data-code="' + code + '"].pifc-svc-title').val(),
                zone_id: $('[data-code="' + code + '"].pifc-svc-zone').val()
            };
        });
        return JSON.stringify(services);
    }

    $('#pifc-save-shipping').on('click', function () {
        var $btn    = $(this).prop('disabled', true).text(pifcAdmin.i18n.saving);
        var $status = $('#pifc-shipping-status').removeClass('pifc-ok pifc-err').text('');

        $.post(pifcAdmin.ajaxUrl, {
            action:   'pifc_save_shipping_services',
            nonce:    pifcAdmin.nonce,
            services: buildPayload()
        })
        .done(function (res) {
            $status.addClass(res.success ? 'pifc-ok' : 'pifc-err').text(res.data.message);
        })
        .fail(function () {
            $status.addClass('pifc-err').text('<?php echo esc_js(__('Request failed.', 'printful-for-fluentcart')); ?>');
        })
        .always(function () {
            $btn.prop('disabled', false).text('<?php echo esc_js(__('Save & Sync to FluentCart', 'printful-for-fluentcart')); ?>');
        });
    });
});
</script>
