/* global pifcAdmin, jQuery */
(function ($) {
    'use strict';

    // ─── Helpers ────────────────────────────────────────────────────────────────

    function setStatus($el, message, isError) {
        $el.removeClass('pifc-ok pifc-err')
           .addClass(isError ? 'pifc-err' : 'pifc-ok')
           .text(message);
    }

    function toggleSpinner($el, isActive) {
        if ($el.length) {
            $el.toggleClass('is-active', !!isActive);
        }
    }

    function noop() {}

    // ─── Settings Page ───────────────────────────────────────────────────────────

    function initSettingsPage() {
        if (!$('#pifc-save-settings').length) return;

        // Test connection
        $('#pifc-test-connection').on('click', function () {
            var $btn    = $(this);
            var $status = $('#pifc-connection-status');
            var $spinner = $('#pifc-test-spinner');
            var apiKey  = $('#pifc_api_key').val();

            $btn.prop('disabled', true).text(pifcAdmin.i18n.testing);
            toggleSpinner($spinner, true);
            $status.removeClass('pifc-ok pifc-err').text('');

            $.post(pifcAdmin.ajaxUrl, {
                action:  'pifc_test_connection',
                nonce:   pifcAdmin.nonce,
                api_key: apiKey
            })
            .done(function (res) {
                setStatus($status, res.data.message, !res.success);
            })
            .fail(function () {
                setStatus($status, pifcAdmin.i18n.failed || 'Request failed.', true);
            })
            .always(function () {
                toggleSpinner($spinner, false);
                $btn.prop('disabled', false).text('Test Connection');
            });
        });

        // Save settings
        $('#pifc-save-settings').on('click', function () {
            var $btn    = $(this);
            var $status = $('#pifc-save-status');
            var $spinner = $('#pifc-save-spinner');

            $btn.prop('disabled', true).text(pifcAdmin.i18n.saving);
            toggleSpinner($spinner, true);
            $status.removeClass('pifc-ok pifc-err').text('');

            $.post(pifcAdmin.ajaxUrl, {
                action:        'pifc_save_settings',
                nonce:         pifcAdmin.nonce,
                api_key:       $('#pifc_api_key').val(),
                auto_fulfill:  $('input[name="auto_fulfill"]').is(':checked') ? 1 : '',
                auto_confirm:  $('input[name="auto_confirm"]').is(':checked') ? 1 : '',
                test_mode:     $('input[name="test_mode"]').is(':checked') ? 1 : '',
                sync_on_import:$('input[name="sync_on_import"]').is(':checked') ? 1 : '',
                sync_product_costs: $('input[name="sync_product_costs"]').is(':checked') ? 1 : '',
                disable_shipping_email: $('input[name="disable_shipping_email"]').is(':checked') ? 1 : '',
                disable_auto_cancel_on_refund: $('input[name="disable_auto_cancel_on_refund"]').is(':checked') ? 1 : '',
                auto_retry_failed: $('input[name="auto_retry_failed"]').is(':checked') ? 1 : ''
            })
            .done(function (res) {
                setStatus($status, res.data.message, !res.success);
            })
            .fail(function () {
                setStatus($status, 'Request failed.', true);
            })
            .always(function () {
                toggleSpinner($spinner, false);
                $btn.prop('disabled', false).text('Save Settings');
            });
        });
    }

    // ─── Product Sync Page ───────────────────────────────────────────────────────

    function initProductSyncPage() {
        if (!$('#pifc-sync-all').length) return;

        var $log = $('#pifc-sync-log');

        function appendLog(msg, isError) {
            var $p = $('<p>').text(msg);
            if (isError) $p.addClass('pifc-log-error');
            $log.append($p).show().scrollTop($log[0].scrollHeight);
        }

        // Sync all
        $('#pifc-sync-all').on('click', function () {
            var $btn = $(this);
            var $spinner = $('#pifc-sync-all-spinner');
            $btn.prop('disabled', true).text(pifcAdmin.i18n.syncing);
            toggleSpinner($spinner, true);
            $log.empty().hide();

            $.post(pifcAdmin.ajaxUrl, {
                action: 'pifc_sync_all_products',
                nonce:  pifcAdmin.nonce
            })
            .done(function (res) {
                if (res.success) {
                    appendLog(res.data.message);
                    if (res.data.errors && res.data.errors.length) {
                        $.each(res.data.errors, function (i, err) {
                            appendLog('⚠ ' + err, true);
                        });
                    }
                } else {
                    appendLog(res.data.message, true);
                }
            })
            .fail(function () {
                appendLog('Request failed.', true);
            })
            .always(function () {
                toggleSpinner($spinner, false);
                $btn.prop('disabled', false).text('Sync All Products from Printful');
            });
        });

        // Sync single
        $('#pifc-sync-single').on('click', function () {
            var $btn  = $(this);
            var $span = $('#pifc-single-sync-status');
            var $spinner = $('#pifc-sync-single-spinner');
            var id    = parseInt($('#pifc-single-product-id').val(), 10);

            if (!id) {
                setStatus($span, 'Enter a valid Printful product ID.', true);
                return;
            }

            $btn.prop('disabled', true).text(pifcAdmin.i18n.syncing);
            toggleSpinner($spinner, true);
            $span.removeClass('pifc-ok pifc-err').text('');

            $.post(pifcAdmin.ajaxUrl, {
                action:              'pifc_sync_single_product',
                nonce:               pifcAdmin.nonce,
                printful_product_id: id
            })
            .done(function (res) {
                setStatus($span, res.data.message, !res.success);
            })
            .fail(function () {
                setStatus($span, 'Request failed.', true);
            })
            .always(function () {
                toggleSpinner($spinner, false);
                $btn.prop('disabled', false).text('Sync Product');
            });
        });
    }

    // ─── Orders Page ─────────────────────────────────────────────────────────────

    function initOrdersPage() {
        if (!$('#pifc-orders-container').length) return;

        var currentPage  = 1;
        var perPage      = 20;
        var currentOrder = null;

        function badgeClass(status) {
            var map = {
                pending:   'pifc-badge-pending',
                draft:     'pifc-badge-draft',
                fulfilled: 'pifc-badge-fulfilled',
                shipped:   'pifc-badge-shipped',
                failed:    'pifc-badge-failed',
                canceled:  'pifc-badge-canceled',
                returned:  'pifc-badge-returned'
            };
            return map[status] || 'pifc-badge-none';
        }

        function loadOrders(page) {
            var $tbody = $('#pifc-orders-tbody');
            var $tableWrap = $('#pifc-orders-table-wrap');
            $tbody.html('<tr><td colspan="7">' + pifcAdmin.i18n.loading + '</td></tr>');
            $tableWrap.addClass('pifc-loading-panel');
            $('#pifc-order-detail').hide();

            $.post(pifcAdmin.ajaxUrl, {
                action: 'pifc_get_orders',
                nonce:  pifcAdmin.nonce,
                page:   page,
                per_page: perPage
            })
            .done(function (res) {
                if (!res.success) {
                    $tbody.html('<tr><td colspan="7">' + res.data.message + '</td></tr>');
                    return;
                }

                var orders = res.data.orders || [];
                var total  = res.data.total  || 0;

                if (!orders.length) {
                    $tbody.html('<tr><td colspan="7">No orders with Printful fulfillment data found.</td></tr>');
                    renderPagination(0, page);
                    return;
                }

                var rows = '';
                $.each(orders, function (i, o) {
                    var pStatus = o.printful_status || 'none';
                    rows +=
                        '<tr>' +
                        '<td><a href="#" class="pifc-view-order" data-id="' + o.id + '">#' + o.id + '</a></td>' +
                        '<td>' + escHtml(o.customer_name || '—') + '</td>' +
                        '<td>' + escHtml(o.order_status || '—') + '</td>' +
                        '<td><span class="pifc-badge ' + badgeClass(pStatus) + '">' + escHtml(pStatus) + '</span></td>' +
                        '<td>' + escHtml(o.printful_order_id ? '#' + o.printful_order_id : '—') + '</td>' +
                        '<td>' + escHtml(o.tracking_number || '—') + '</td>' +
                        '<td>' +
                            '<button class="button button-small pifc-view-order" data-id="' + o.id + '">Details</button>' +
                        '</td>' +
                        '</tr>';
                });

                $tbody.html(rows);
                renderPagination(total, page);
            })
            .fail(function () {
                $tbody.html('<tr><td colspan="7">Request failed.</td></tr>');
            })
            .always(function () {
                $tableWrap.removeClass('pifc-loading-panel');
            });
        }

        function renderPagination(total, page) {
            var pages   = Math.ceil(total / perPage) || 1;
            var $paging = $('#pifc-pagination');
            var html    = 'Page ' + page + ' of ' + pages + ' &nbsp; ';

            html += '<button class="button button-small" id="pifc-prev-page"' + (page <= 1 ? ' disabled' : '') + '>« Prev</button> ';
            html += '<button class="button button-small" id="pifc-next-page"' + (page >= pages ? ' disabled' : '') + '>Next »</button>';
            $paging.html(html);

            $('#pifc-prev-page').on('click', function () { currentPage--; loadOrders(currentPage); });
            $('#pifc-next-page').on('click', function () { currentPage++; loadOrders(currentPage); });
        }

        function loadOrderDetail(orderId) {
            var $detail = $('#pifc-order-detail');
            $detail.addClass('pifc-loading-panel');
            $detail.show().html('<p>' + pifcAdmin.i18n.loading + '</p>');

            $.post(pifcAdmin.ajaxUrl, {
                action:   'pifc_get_order_detail',
                nonce:    pifcAdmin.nonce,
                order_id: orderId
            })
            .done(function (res) {
                if (!res.success) {
                    $detail.html('<p class="pifc-log-error">' + res.data.message + '</p>');
                    return;
                }

                var d   = res.data;
                currentOrder = orderId;

                var html =
                    '<div class="pifc-card">' +
                    '<h2>Printful Order Detail — FC #' + orderId + '</h2>' +
                    '<div class="pifc-detail-grid">' +
                    detailItem('Printful Order ID', d.printful_order_id ? '#' + d.printful_order_id : '—') +
                    detailItem('Printful Status', d.printful_status || '—') +
                    detailItem('FC Order Status', d.order_status || '—') +
                    detailItem('FC Shipping Status', d.shipping_status || '—') +
                    detailItem('Carrier', d.carrier || '—') +
                    detailItem('Tracking #', d.tracking_number
                        ? (d.tracking_url
                            ? '<a href="' + escHtml(d.tracking_url) + '" target="_blank" rel="noopener">' + escHtml(d.tracking_number) + '</a>'
                            : escHtml(d.tracking_number))
                        : '—') +
                    detailItem('Ship Date', d.ship_date || '—') +
                    '</div>' +
                    '<div class="pifc-actions">';

                if (!d.printful_order_id) {
                    html += '<button class="button button-primary" id="pifc-fulfill-btn" data-id="' + orderId + '">' +
                            'Send to Printful</button>';
                }

                if (d.printful_order_id && d.printful_status !== 'canceled') {
                    html += '<button class="button button-secondary" id="pifc-cancel-btn" data-id="' + orderId + '">' +
                            'Cancel Fulfillment</button>';
                }

                if (d.fulfillment_error) {
                    html += '<p class="description pifc-log-error">Last error: ' + escHtml(d.fulfillment_error) + '</p>';
                }

                html += '</div></div>';
                $detail.html(html);

                bindDetailButtons();
            })
            .fail(function () {
                $detail.html('<p class="pifc-log-error">Request failed.</p>');
            })
            .always(function () {
                $detail.removeClass('pifc-loading-panel');
            });
        }

        function detailItem(label, value) {
            return '<div class="pifc-detail-item"><label>' + label + '</label><span>' + value + '</span></div>';
        }

        function bindDetailButtons() {
            $('#pifc-fulfill-btn').on('click', function () {
                if (!confirm(pifcAdmin.i18n.confirmFulfill)) return;
                var $btn = $(this).prop('disabled', true).text(pifcAdmin.i18n.fulfilling);
                $.post(pifcAdmin.ajaxUrl, {
                    action:   'pifc_fulfill_order',
                    nonce:    pifcAdmin.nonce,
                    order_id: currentOrder
                })
                .done(function (res) {
                    alert(res.data.message);
                    if (res.success) loadOrderDetail(currentOrder);
                })
                .fail(function () { alert('Request failed.'); })
                .always(function () { $btn.prop('disabled', false); });
            });

            $('#pifc-cancel-btn').on('click', function () {
                if (!confirm(pifcAdmin.i18n.confirmCancel)) return;
                var $btn = $(this).prop('disabled', true).text(pifcAdmin.i18n.canceling);
                $.post(pifcAdmin.ajaxUrl, {
                    action:   'pifc_cancel_fulfillment',
                    nonce:    pifcAdmin.nonce,
                    order_id: currentOrder
                })
                .done(function (res) {
                    alert(res.data.message);
                    if (res.success) loadOrderDetail(currentOrder);
                })
                .fail(function () { alert('Request failed.'); })
                .always(function () { $btn.prop('disabled', false); });
            });
        }

        // Event delegation for "view order" links/buttons
        $(document).on('click', '.pifc-view-order', function (e) {
            e.preventDefault();
            loadOrderDetail($(this).data('id'));
        });

        // Initial load
        loadOrders(currentPage);
    }

    // ─── Utility ─────────────────────────────────────────────────────────────────

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ─── Boot ─────────────────────────────────────────────────────────────────────

    $(function () {
        initSettingsPage();
        initProductSyncPage();
        initOrdersPage();
    });

})(jQuery);
