/* global pifcAdmin, jQuery */
(function ($) {
    'use strict';

    function setStatus($el, message, isError) {
        if (!$el || !$el.length) {
            return;
        }

        $el.removeClass('pifc-ok pifc-err')
            .addClass(isError ? 'pifc-err' : 'pifc-ok')
            .text(message || '');
    }

    function toggleSpinner($el, isActive) {
        if ($el && $el.length) {
            $el.toggleClass('is-active', !!isActive);
        }
    }

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function parseHashQuery(hash) {
        var index = hash.indexOf('?');
        var query = {};

        if (index === -1) {
            return query;
        }

        hash.substring(index + 1).split('&').forEach(function (part) {
            if (!part) {
                return;
            }

            var pair = part.split('=');
            var key = decodeURIComponent(pair[0] || '');
            var value = decodeURIComponent(pair[1] || '');

            if (key) {
                query[key] = value;
            }
        });

        return query;
    }

    function isNativePrintfulRoute() {
        if (!pifcAdmin || pifcAdmin.currentPage !== 'toplevel_page_fluent-cart') {
            return false;
        }

        return /^#\/integrations\/printful(?:\?|$)/.test(window.location.hash || '');
    }

    function getNativeView() {
        if (!isNativePrintfulRoute()) {
            return null;
        }

        var query = parseHashQuery(window.location.hash || '');
        return query.view || 'settings';
    }

    function getNativeContainer() {
        return $('.fct-settings-nav-content-inner').first().length
            ? $('.fct-settings-nav-content-inner').first()
            : $('.fct-integration-setting-container .single-page-body').first();
    }

    function getNativeSettingsRoot() {
        var $container = getNativeContainer();
        var $root;

        if (!$container.length) {
            return $();
        }

        $root = $container.children().not('#pifc-native-route-app').first();

        if (!$root.length) {
            $root = $container.find('.fct-card').first();
        }

        if ($root.length) {
            $root.attr('data-pifc-native-settings-root', '1');
        }

        return $root;
    }

    function getTabConfig() {
        return [
            { key: 'settings', label: 'Native Settings', href: pifcAdmin.routes.settings },
            { key: 'advanced', label: 'Advanced', href: pifcAdmin.routes.advanced },
            { key: 'sync', label: 'Product Sync', href: pifcAdmin.routes.sync },
            { key: 'orders', label: 'Orders', href: pifcAdmin.routes.orders },
            { key: 'bulk', label: 'Bulk Fulfill', href: pifcAdmin.routes.bulk },
            { key: 'catalog', label: 'Catalog', href: pifcAdmin.routes.catalog },
            { key: 'shipping', label: 'Shipping', href: pifcAdmin.routes.shipping }
        ];
    }

    function renderNativeToolsShell(view) {
        var $container = getNativeContainer();
        var $existing = $('#pifc-native-route-app');
        var $settingsRoot = getNativeSettingsRoot();

        if (!$container.length || !$settingsRoot.length) {
            return $();
        }

        if (!$existing.length) {
            var tabs = getTabConfig().map(function (tab) {
                return '' +
                    '<a class="pifc-native-tab" data-panel="' + escHtml(tab.key) + '" href="' + escHtml(tab.href) + '">' +
                        escHtml(tab.label) +
                    '</a>';
            }).join('');

            $existing = $(
                '<div id="pifc-native-route-app" class="pifc-native-route-app">' +
                    '<div class="pifc-native-tabs">' + tabs + '</div>' +
                    '<div id="pifc-native-tools-panel" class="pifc-native-tools-panel"></div>' +
                '</div>'
            );

            $existing.insertAfter($settingsRoot);
        }

        $existing.find('.pifc-native-tab').removeClass('is-active')
            .filter('[data-panel="' + view + '"]').addClass('is-active');

        if (view === 'settings') {
            $settingsRoot.show();
            $('#pifc-native-tools-panel').hide().empty();
        } else {
            $settingsRoot.hide();
            $('#pifc-native-tools-panel').show();
        }

        return $existing;
    }

    function loadNativePanel(panel) {
        var $panel = $('#pifc-native-tools-panel');

        if (!$panel.length || panel === 'settings') {
            return;
        }

        if ($panel.data('panel') === panel && $panel.data('loaded')) {
            return;
        }

        $panel.data('panel', panel).data('loaded', false).html(
            '<div class="pifc-card"><p>' + escHtml(pifcAdmin.i18n.loadingPanel || 'Loading panel...') + '</p></div>'
        );

        $.post(pifcAdmin.ajaxUrl, {
            action: 'pifc_get_native_panel',
            nonce: pifcAdmin.nonce,
            panel: panel
        }).done(function (res) {
            if (!res || !res.success || !res.data || !res.data.html) {
                $panel.html('<div class="pifc-card"><p class="pifc-log-error">' + escHtml((res && res.data && res.data.message) || 'Failed to load panel.') + '</p></div>');
                return;
            }

            $panel.html(res.data.html);
            $panel.data('loaded', true);
            initPanel(panel);
        }).fail(function () {
            $panel.html('<div class="pifc-card"><p class="pifc-log-error">Failed to load panel.</p></div>');
        });
    }

    function syncNativeRoute() {
        var view;

        if (!isNativePrintfulRoute()) {
            $('#pifc-native-route-app').remove();
            $('[data-pifc-native-settings-root="1"]').show();
            return;
        }

        view = getNativeView();

        if (!renderNativeToolsShell(view).length) {
            return;
        }

        if (view !== 'settings') {
            loadNativePanel(view);
        }
    }

    function initSettingsPage() {
        $(document)
            .off('click.pifcSettings', '#pifc-test-connection')
            .on('click.pifcSettings', '#pifc-test-connection', function () {
                var $btn = $(this);
                var $status = $('#pifc-connection-status');
                var $spinner = $('#pifc-test-spinner');
                var apiKey = $('#pifc_api_key').val();

                $btn.prop('disabled', true).text(pifcAdmin.i18n.testing);
                toggleSpinner($spinner, true);
                $status.removeClass('pifc-ok pifc-err').text('');

                $.post(pifcAdmin.ajaxUrl, {
                    action: 'pifc_test_connection',
                    nonce: pifcAdmin.nonce,
                    api_key: apiKey
                }).done(function (res) {
                    setStatus($status, res && res.data ? res.data.message : 'Request failed.', !(res && res.success));
                }).fail(function () {
                    setStatus($status, 'Request failed.', true);
                }).always(function () {
                    toggleSpinner($spinner, false);
                    $btn.prop('disabled', false).text('Test Connection');
                });
            });

        $(document)
            .off('click.pifcSettingsSave', '#pifc-save-settings')
            .on('click.pifcSettingsSave', '#pifc-save-settings', function () {
                var $btn = $(this);
                var $status = $('#pifc-save-status');
                var $spinner = $('#pifc-save-spinner');

                $btn.prop('disabled', true).text(pifcAdmin.i18n.saving);
                toggleSpinner($spinner, true);
                $status.removeClass('pifc-ok pifc-err').text('');

                $.post(pifcAdmin.ajaxUrl, {
                    action: 'pifc_save_settings',
                    nonce: pifcAdmin.nonce,
                    api_key: $('#pifc_api_key').val(),
                    auto_fulfill: $('input[name="auto_fulfill"]').is(':checked') ? 1 : '',
                    auto_confirm: $('input[name="auto_confirm"]').is(':checked') ? 1 : '',
                    test_mode: $('input[name="test_mode"]').is(':checked') ? 1 : '',
                    sync_on_import: $('input[name="sync_on_import"]').is(':checked') ? 1 : '',
                    sync_product_costs: $('input[name="sync_product_costs"]').is(':checked') ? 1 : '',
                    disable_shipping_email: $('input[name="disable_shipping_email"]').is(':checked') ? 1 : '',
                    disable_auto_cancel_on_refund: $('input[name="disable_auto_cancel_on_refund"]').is(':checked') ? 1 : '',
                    auto_retry_failed: $('input[name="auto_retry_failed"]').is(':checked') ? 1 : ''
                }).done(function (res) {
                    setStatus($status, res && res.data ? res.data.message : 'Request failed.', !(res && res.success));
                }).fail(function () {
                    setStatus($status, 'Request failed.', true);
                }).always(function () {
                    toggleSpinner($spinner, false);
                    $btn.prop('disabled', false).text('Save Settings');
                });
            });
    }

    function initProductSyncPage() {
        function appendLog(msg, isError) {
            var $log = $('#pifc-sync-log');
            var $line = $('<p>').text(msg);

            if (isError) {
                $line.addClass('pifc-log-error');
            }

            $log.append($line).show();
        }

        $(document)
            .off('click.pifcSyncAll', '#pifc-sync-all')
            .on('click.pifcSyncAll', '#pifc-sync-all', function () {
                var $btn = $(this);
                var $spinner = $('#pifc-sync-all-spinner');
                var $log = $('#pifc-sync-log');

                $btn.prop('disabled', true).text(pifcAdmin.i18n.syncing);
                toggleSpinner($spinner, true);
                $log.empty().hide();

                $.post(pifcAdmin.ajaxUrl, {
                    action: 'pifc_sync_all_products',
                    nonce: pifcAdmin.nonce
                }).done(function (res) {
                    if (res && res.success) {
                        appendLog(res.data.message, false);
                        $.each((res.data.errors || []), function (_, err) {
                            appendLog(err, true);
                        });
                    } else {
                        appendLog(res && res.data ? res.data.message : 'Request failed.', true);
                    }
                }).fail(function () {
                    appendLog('Request failed.', true);
                }).always(function () {
                    toggleSpinner($spinner, false);
                    $btn.prop('disabled', false).text('Sync All Products from Printful');
                });
            });

        $(document)
            .off('click.pifcSyncSingle', '#pifc-sync-single')
            .on('click.pifcSyncSingle', '#pifc-sync-single', function () {
                var $btn = $(this);
                var $status = $('#pifc-single-sync-status');
                var $spinner = $('#pifc-sync-single-spinner');
                var id = parseInt($('#pifc-single-product-id').val(), 10);

                if (!id) {
                    setStatus($status, 'Enter a valid Printful product ID.', true);
                    return;
                }

                $btn.prop('disabled', true).text(pifcAdmin.i18n.syncing);
                toggleSpinner($spinner, true);
                $status.removeClass('pifc-ok pifc-err').text('');

                $.post(pifcAdmin.ajaxUrl, {
                    action: 'pifc_sync_single_product',
                    nonce: pifcAdmin.nonce,
                    printful_product_id: id
                }).done(function (res) {
                    setStatus($status, res && res.data ? res.data.message : 'Request failed.', !(res && res.success));
                }).fail(function () {
                    setStatus($status, 'Request failed.', true);
                }).always(function () {
                    toggleSpinner($spinner, false);
                    $btn.prop('disabled', false).text('Sync Product');
                });
            });
    }

    function detailItem(label, value) {
        return '<div class="pifc-detail-item"><label>' + escHtml(label) + '</label><span>' + value + '</span></div>';
    }

    function badgeClass(status) {
        var map = {
            pending: 'pifc-badge-pending',
            draft: 'pifc-badge-draft',
            fulfilled: 'pifc-badge-fulfilled',
            shipped: 'pifc-badge-shipped',
            failed: 'pifc-badge-failed',
            canceled: 'pifc-badge-canceled',
            returned: 'pifc-badge-returned'
        };

        return map[status] || 'pifc-badge-none';
    }

    function bindOrderDetailActions(orderId) {
        $(document)
            .off('click.pifcFulfillOrder', '#pifc-fulfill-btn')
            .on('click.pifcFulfillOrder', '#pifc-fulfill-btn', function () {
                var $btn = $(this);

                if (!window.confirm(pifcAdmin.i18n.confirmFulfill)) {
                    return;
                }

                $btn.prop('disabled', true).text(pifcAdmin.i18n.fulfilling);

                $.post(pifcAdmin.ajaxUrl, {
                    action: 'pifc_fulfill_order',
                    nonce: pifcAdmin.nonce,
                    order_id: orderId
                }).done(function (res) {
                    window.alert(res && res.data ? res.data.message : 'Request failed.');
                    if (res && res.success) {
                        loadOrderDetail(orderId, '#pifc-order-detail');
                    }
                }).fail(function () {
                    window.alert('Request failed.');
                }).always(function () {
                    $btn.prop('disabled', false).text('Send to Printful');
                });
            });

        $(document)
            .off('click.pifcCancelOrder', '#pifc-cancel-btn')
            .on('click.pifcCancelOrder', '#pifc-cancel-btn', function () {
                var $btn = $(this);

                if (!window.confirm(pifcAdmin.i18n.confirmCancel)) {
                    return;
                }

                $btn.prop('disabled', true).text(pifcAdmin.i18n.canceling);

                $.post(pifcAdmin.ajaxUrl, {
                    action: 'pifc_cancel_fulfillment',
                    nonce: pifcAdmin.nonce,
                    order_id: orderId
                }).done(function (res) {
                    window.alert(res && res.data ? res.data.message : 'Request failed.');
                    if (res && res.success) {
                        loadOrderDetail(orderId, '#pifc-order-detail');
                    }
                }).fail(function () {
                    window.alert('Request failed.');
                }).always(function () {
                    $btn.prop('disabled', false).text('Cancel Fulfillment');
                });
            });
    }

    function loadOrderDetail(orderId, targetSelector) {
        var $detail = $(targetSelector);

        $detail.addClass('pifc-loading-panel').show().html('<p>' + escHtml(pifcAdmin.i18n.loading) + '</p>');

        $.post(pifcAdmin.ajaxUrl, {
            action: 'pifc_get_order_detail',
            nonce: pifcAdmin.nonce,
            order_id: orderId
        }).done(function (res) {
            var d;
            var html;

            if (!res || !res.success) {
                $detail.html('<p class="pifc-log-error">' + escHtml(res && res.data ? res.data.message : 'Request failed.') + '</p>');
                return;
            }

            d = res.data || {};

            html = '' +
                '<div class="pifc-card">' +
                    '<h2>Printful Order Detail - FC #' + escHtml(orderId) + '</h2>' +
                    '<div class="pifc-detail-grid">' +
                        detailItem('Printful Order ID', d.printful_order_id ? '#' + escHtml(d.printful_order_id) : '—') +
                        detailItem('Printful Status', escHtml(d.printful_status || '—')) +
                        detailItem('FC Order Status', escHtml(d.order_status || '—')) +
                        detailItem('FC Shipping Status', escHtml(d.shipping_status || '—')) +
                        detailItem('Carrier', escHtml(d.carrier || '—')) +
                        detailItem('Tracking #', d.tracking_number
                            ? (d.tracking_url
                                ? '<a href="' + escHtml(d.tracking_url) + '" target="_blank" rel="noopener">' + escHtml(d.tracking_number) + '</a>'
                                : escHtml(d.tracking_number))
                            : '—') +
                        detailItem('Ship Date', escHtml(d.ship_date || '—')) +
                    '</div>' +
                    '<div class="pifc-actions">';

            if (!d.printful_order_id) {
                html += '<button class="button button-primary" id="pifc-fulfill-btn">Send to Printful</button>';
            }

            if (d.printful_order_id && d.printful_status !== 'canceled') {
                html += '<button class="button button-secondary" id="pifc-cancel-btn">Cancel Fulfillment</button>';
            }

            if (d.fulfillment_error) {
                html += '<p class="description pifc-log-error">Last error: ' + escHtml(d.fulfillment_error) + '</p>';
            }

            html += '</div></div>';

            $detail.html(html);
            bindOrderDetailActions(orderId);
        }).fail(function () {
            $detail.html('<p class="pifc-log-error">Request failed.</p>');
        }).always(function () {
            $detail.removeClass('pifc-loading-panel');
        });
    }

    function initOrdersPage() {
        var currentPage = 1;
        var perPage = 20;

        function renderPagination(total, page) {
            var pages = Math.ceil(total / perPage) || 1;
            var $paging = $('#pifc-pagination');
            var html = 'Page ' + page + ' of ' + pages + ' &nbsp; ';

            html += '<button class="button button-small" id="pifc-prev-page"' + (page <= 1 ? ' disabled' : '') + '>« Prev</button> ';
            html += '<button class="button button-small" id="pifc-next-page"' + (page >= pages ? ' disabled' : '') + '>Next »</button>';

            $paging.html(html);
        }

        function loadOrders(page) {
            var $tbody = $('#pifc-orders-tbody');
            var $tableWrap = $('#pifc-orders-table-wrap');

            if (!$tbody.length) {
                return;
            }

            $tbody.html('<tr><td colspan="7">' + escHtml(pifcAdmin.i18n.loading) + '</td></tr>');
            $tableWrap.addClass('pifc-loading-panel');
            $('#pifc-order-detail').hide().empty();

            $.post(pifcAdmin.ajaxUrl, {
                action: 'pifc_get_orders',
                nonce: pifcAdmin.nonce,
                page: page,
                per_page: perPage
            }).done(function (res) {
                var orders;
                var total;
                var rows = '';

                if (!res || !res.success) {
                    $tbody.html('<tr><td colspan="7">' + escHtml(res && res.data ? res.data.message : 'Request failed.') + '</td></tr>');
                    return;
                }

                orders = res.data.orders || [];
                total = res.data.total || 0;

                if (!orders.length) {
                    $tbody.html('<tr><td colspan="7">No orders with Printful fulfillment data found.</td></tr>');
                    renderPagination(0, page);
                    return;
                }

                $.each(orders, function (_, order) {
                    var printfulStatus = order.printful_status || 'none';
                    rows += '' +
                        '<tr>' +
                            '<td><a href="#" class="pifc-view-order" data-id="' + escHtml(order.id) + '">#' + escHtml(order.id) + '</a></td>' +
                            '<td>' + escHtml(order.customer_name || '—') + '</td>' +
                            '<td>' + escHtml(order.order_status || '—') + '</td>' +
                            '<td><span class="pifc-badge ' + escHtml(badgeClass(printfulStatus)) + '">' + escHtml(printfulStatus) + '</span></td>' +
                            '<td>' + escHtml(order.printful_order_id ? '#' + order.printful_order_id : '—') + '</td>' +
                            '<td>' + escHtml(order.tracking_number || '—') + '</td>' +
                            '<td><button class="button button-small pifc-view-order" data-id="' + escHtml(order.id) + '">Details</button></td>' +
                        '</tr>';
                });

                $tbody.html(rows);
                renderPagination(total, page);
            }).fail(function () {
                $tbody.html('<tr><td colspan="7">Request failed.</td></tr>');
            }).always(function () {
                $tableWrap.removeClass('pifc-loading-panel');
            });
        }

        $(document)
            .off('click.pifcViewOrder', '.pifc-view-order')
            .on('click.pifcViewOrder', '.pifc-view-order', function (e) {
                e.preventDefault();
                loadOrderDetail($(this).data('id'), '#pifc-order-detail');
            });

        $(document)
            .off('click.pifcManualLookup', '#pifc-manual-lookup')
            .on('click.pifcManualLookup', '#pifc-manual-lookup', function () {
                var $btn = $(this);
                var $spinner = $('#pifc-manual-lookup-spinner');
                var $status = $('#pifc-manual-status');
                var orderId = parseInt($('#pifc-manual-order-id').val(), 10);

                if (!orderId) {
                    setStatus($status, 'Enter a valid FluentCart order ID.', true);
                    return;
                }

                $btn.prop('disabled', true).text(pifcAdmin.i18n.loading);
                toggleSpinner($spinner, true);
                $status.text('');
                loadOrderDetail(orderId, '#pifc-manual-detail');
                setStatus($status, 'Order loaded.', false);

                window.setTimeout(function () {
                    $btn.prop('disabled', false).text('Load Order');
                    toggleSpinner($spinner, false);
                }, 300);
            });

        $(document)
            .off('click.pifcPrev', '#pifc-prev-page')
            .on('click.pifcPrev', '#pifc-prev-page', function () {
                if (currentPage > 1) {
                    currentPage -= 1;
                    loadOrders(currentPage);
                }
            });

        $(document)
            .off('click.pifcNext', '#pifc-next-page')
            .on('click.pifcNext', '#pifc-next-page', function () {
                currentPage += 1;
                loadOrders(currentPage);
            });

        if ($('#pifc-orders-container').length) {
            loadOrders(currentPage);
        }
    }

    function initBulkFulfillPage() {
        function renderOrders(orders) {
            var rows = '';
            var $wrap = $('#pifc-unfulfilled-wrap');
            var $tbody = $('#pifc-unfulfilled-tbody');

            if (!orders.length) {
                $('#pifc-no-orders').show();
                $wrap.show();
                $tbody.empty();
                $('#pifc-bulk-actions, #pifc-select-all, #pifc-deselect-all').hide();
                return;
            }

            $.each(orders, function (_, order) {
                rows += '' +
                    '<tr>' +
                        '<td><input type="checkbox" class="pifc-order-check" value="' + escHtml(order.id) + '"></td>' +
                        '<td>#' + escHtml(order.id) + '</td>' +
                        '<td>' + escHtml(order.customer_name) + '</td>' +
                        '<td>' + escHtml(order.total) + ' ' + escHtml(order.currency) + '</td>' +
                        '<td>' + escHtml(order.date) + '</td>' +
                        '<td>' + escHtml(order.order_status) + '</td>' +
                    '</tr>';
            });

            $tbody.html(rows);
            $wrap.show();
            $('#pifc-no-orders').hide();
            $('#pifc-select-all, #pifc-deselect-all').show();
            updateBulkSelectionState();
        }

        function updateBulkSelectionState() {
            var count = $('.pifc-order-check:checked').length;
            $('#pifc-bulk-selected-count').text(count ? (count + ' selected') : '');
            $('#pifc-bulk-actions').toggle(count > 0);
        }

        $(document)
            .off('click.pifcLoadUnfulfilled', '#pifc-load-unfulfilled')
            .on('click.pifcLoadUnfulfilled', '#pifc-load-unfulfilled', function () {
                var $btn = $(this);
                var $spinner = $('#pifc-load-unfulfilled-spinner');

                $btn.prop('disabled', true).text(pifcAdmin.i18n.loading);
                toggleSpinner($spinner, true);
                $('#pifc-bulk-log').hide().empty();

                $.post(pifcAdmin.ajaxUrl, {
                    action: 'pifc_get_unfulfilled_orders',
                    nonce: pifcAdmin.nonce
                }).done(function (res) {
                    if (!res || !res.success) {
                        $('#pifc-bulk-log').show().html('<p class="pifc-log-error">' + escHtml(res && res.data ? res.data.message : 'Request failed.') + '</p>');
                        return;
                    }

                    renderOrders(res.data.orders || []);
                }).fail(function () {
                    $('#pifc-bulk-log').show().html('<p class="pifc-log-error">Request failed.</p>');
                }).always(function () {
                    $btn.prop('disabled', false).text('Load Unfulfilled Orders');
                    toggleSpinner($spinner, false);
                });
            });

        $(document)
            .off('click.pifcSelectAll', '#pifc-select-all')
            .on('click.pifcSelectAll', '#pifc-select-all', function () {
                $('.pifc-order-check, #pifc-check-all').prop('checked', true);
                updateBulkSelectionState();
            });

        $(document)
            .off('click.pifcDeselectAll', '#pifc-deselect-all')
            .on('click.pifcDeselectAll', '#pifc-deselect-all', function () {
                $('.pifc-order-check, #pifc-check-all').prop('checked', false);
                updateBulkSelectionState();
            });

        $(document)
            .off('change.pifcCheckAll', '#pifc-check-all')
            .on('change.pifcCheckAll', '#pifc-check-all', function () {
                $('.pifc-order-check').prop('checked', $(this).is(':checked'));
                updateBulkSelectionState();
            });

        $(document)
            .off('change.pifcOrderCheck', '.pifc-order-check')
            .on('change.pifcOrderCheck', '.pifc-order-check', updateBulkSelectionState);

        $(document)
            .off('click.pifcBulkFulfill', '#pifc-bulk-fulfill-btn')
            .on('click.pifcBulkFulfill', '#pifc-bulk-fulfill-btn', function () {
                var $btn = $(this);
                var $spinner = $('#pifc-bulk-fulfill-spinner');
                var ids = $('.pifc-order-check:checked').map(function () {
                    return $(this).val();
                }).get();

                if (!ids.length) {
                    return;
                }

                $btn.prop('disabled', true).text(pifcAdmin.i18n.fulfilling);
                toggleSpinner($spinner, true);
                $('#pifc-bulk-log').show().empty();

                $.post(pifcAdmin.ajaxUrl, {
                    action: 'pifc_bulk_fulfill',
                    nonce: pifcAdmin.nonce,
                    order_ids: ids.join(',')
                }).done(function (res) {
                    var html = '';

                    if (!res || !res.success) {
                        $('#pifc-bulk-log').html('<p class="pifc-log-error">' + escHtml(res && res.data ? res.data.message : 'Request failed.') + '</p>');
                        return;
                    }

                    html += '<p>' + escHtml(res.data.message) + '</p>';

                    $.each((res.data.results && res.data.results.fulfilled) || [], function (_, item) {
                        html += '<p>Order #' + escHtml(item.id) + ' sent to Printful.</p>';
                    });

                    $.each((res.data.results && res.data.results.failed) || [], function (_, item) {
                        html += '<p class="pifc-log-error">Order #' + escHtml(item.id) + ': ' + escHtml(item.reason) + '</p>';
                    });

                    $('#pifc-bulk-log').html(html);
                    $('#pifc-load-unfulfilled').trigger('click');
                }).fail(function () {
                    $('#pifc-bulk-log').html('<p class="pifc-log-error">Request failed.</p>');
                }).always(function () {
                    $btn.prop('disabled', false).text('Send Selected to Printful');
                    toggleSpinner($spinner, false);
                });
            });
    }

    function initCatalogPage() {
        function renderProducts(products) {
            var html = '';

            $.each(products, function (_, product) {
                html += '' +
                    '<button type="button" class="pifc-catalog-product" data-id="' + escHtml(product.id) + '">' +
                        (product.image ? '<img src="' + escHtml(product.image) + '" alt="' + escHtml(product.model) + '">' : '') +
                        '<strong>' + escHtml(product.model) + '</strong>' +
                        '<span>' + escHtml(product.brand || '') + '</span>' +
                        '<span>' + escHtml(product.variant_count || 0) + ' variants</span>' +
                    '</button>';
            });

            $('#pifc-product-grid').html(html);
            $('#pifc-catalog-products').show();
        }

        function loadCategories() {
            var $button = $('#pifc-load-categories');
            var $select = $('#pifc-category-select');

            $button.prop('disabled', true).text(pifcAdmin.i18n.loading);

            $.post(pifcAdmin.ajaxUrl, {
                action: 'pifc_get_catalog_categories',
                nonce: pifcAdmin.nonce
            }).done(function (res) {
                var options = '<option value="">Select a category</option>';

                if (!res || !res.success) {
                    options = '<option value="">' + escHtml(res && res.data ? res.data.message : 'Failed to load categories.') + '</option>';
                    $select.html(options).prop('disabled', true);
                    return;
                }

                $.each(res.data.categories || [], function (_, category) {
                    options += '<option value="' + escHtml(category.id) + '">' + escHtml(category.title) + '</option>';
                });

                $select.html(options).prop('disabled', false);
            }).fail(function () {
                $select.html('<option value="">Failed to load categories.</option>').prop('disabled', true);
            }).always(function () {
                $button.prop('disabled', false).text('Load Categories');
            });
        }

        function loadProducts(categoryId) {
            $('#pifc-product-grid').html('<p>' + escHtml(pifcAdmin.i18n.loading) + '</p>');
            $('#pifc-catalog-products').show();

            $.post(pifcAdmin.ajaxUrl, {
                action: 'pifc_get_catalog_products',
                nonce: pifcAdmin.nonce,
                category_id: categoryId
            }).done(function (res) {
                if (!res || !res.success) {
                    $('#pifc-product-grid').html('<p class="pifc-log-error">' + escHtml(res && res.data ? res.data.message : 'Failed to load products.') + '</p>');
                    return;
                }

                renderProducts(res.data.products || []);
            }).fail(function () {
                $('#pifc-product-grid').html('<p class="pifc-log-error">Failed to load products.</p>');
            });
        }

        function loadProductDetail(productId) {
            $('#pifc-catalog-detail').show().addClass('pifc-loading-panel');

            $.post(pifcAdmin.ajaxUrl, {
                action: 'pifc_get_catalog_product',
                nonce: pifcAdmin.nonce,
                product_id: productId
            }).done(function (res) {
                var product;
                var variantsHtml = '';

                if (!res || !res.success) {
                    $('#pifc-catalog-detail').html('<p class="pifc-log-error">' + escHtml(res && res.data ? res.data.message : 'Failed to load product.') + '</p>');
                    return;
                }

                product = res.data.product || {};

                $('#pifc-detail-title').text(product.model || '');
                $('#pifc-detail-image').attr('src', product.image || '').attr('alt', product.model || '');
                $('#pifc-detail-desc').text(product.description || '');
                $('#pifc-detail-brand').text(product.brand || '');
                $('#pifc-detail-type').text(product.type_name || '');
                $('#pifc-detail-synced-note').toggle(!!res.data.already_synced);
                $('#pifc-open-printful').attr('href', 'https://www.printful.com/dashboard');
                $('#pifc-variant-count').text((res.data.variants || []).length);

                $.each(res.data.variants || [], function (_, variant) {
                    variantsHtml += '' +
                        '<tr>' +
                            '<td>' + escHtml(variant.name || '—') + '</td>' +
                            '<td>' + escHtml(variant.size || '—') + '</td>' +
                            '<td>' + escHtml(variant.color || '—') + '</td>' +
                            '<td>' + escHtml(variant.price || '0.00') + '</td>' +
                            '<td>' + (variant.in_stock ? 'Yes' : 'No') + '</td>' +
                        '</tr>';
                });

                $('#pifc-variants-tbody').html(variantsHtml || '<tr><td colspan="5">No variants found.</td></tr>');
            }).fail(function () {
                $('#pifc-catalog-detail').html('<p class="pifc-log-error">Failed to load product.</p>');
            }).always(function () {
                $('#pifc-catalog-detail').removeClass('pifc-loading-panel');
            });
        }

        $(document)
            .off('click.pifcLoadCategories', '#pifc-load-categories')
            .on('click.pifcLoadCategories', '#pifc-load-categories', loadCategories);

        $(document)
            .off('change.pifcCategorySelect', '#pifc-category-select')
            .on('change.pifcCategorySelect', '#pifc-category-select', function () {
                var id = parseInt($(this).val(), 10);

                if (!id) {
                    return;
                }

                $('#pifc-catalog-category-title').text($('#pifc-category-select option:selected').text());
                loadProducts(id);
            });

        $(document)
            .off('click.pifcCatalogProduct', '.pifc-catalog-product')
            .on('click.pifcCatalogProduct', '.pifc-catalog-product', function () {
                loadProductDetail($(this).data('id'));
            });
    }

    function initShippingPage() {
        $(document)
            .off('click.pifcSaveShipping', '#pifc-save-shipping')
            .on('click.pifcSaveShipping', '#pifc-save-shipping', function () {
                var $btn = $(this);
                var $status = $('#pifc-shipping-status');
                var services = {};

                $('.pifc-svc-enabled').each(function () {
                    var code = $(this).data('code');
                    services[code] = {
                        enabled: $(this).is(':checked'),
                        title: $('.pifc-svc-title[data-code="' + code + '"]').val(),
                        zone_id: $('.pifc-svc-zone[data-code="' + code + '"]').val()
                    };
                });

                $btn.prop('disabled', true).text(pifcAdmin.i18n.saving);
                setStatus($status, '', false);

                $.post(pifcAdmin.ajaxUrl, {
                    action: 'pifc_save_shipping_services',
                    nonce: pifcAdmin.nonce,
                    services: JSON.stringify(services)
                }).done(function (res) {
                    setStatus($status, res && res.data ? res.data.message : 'Request failed.', !(res && res.success));
                }).fail(function () {
                    setStatus($status, 'Request failed.', true);
                }).always(function () {
                    $btn.prop('disabled', false).text('Save & Sync to FluentCart');
                });
            });
    }

    function initPanel(panel) {
        if (!panel) {
            return;
        }

        if (panel === 'advanced') {
            initSettingsPage();
            return;
        }

        if (panel === 'sync') {
            initProductSyncPage();
            return;
        }

        if (panel === 'orders') {
            initOrdersPage();
            return;
        }

        if (panel === 'bulk') {
            initBulkFulfillPage();
            return;
        }

        if (panel === 'catalog') {
            initCatalogPage();
            return;
        }

        if (panel === 'shipping') {
            initShippingPage();
        }
    }

    function bootNativeBridge() {
        if (pifcAdmin.currentPage !== 'toplevel_page_fluent-cart') {
            return;
        }

        syncNativeRoute();
        $(window).off('hashchange.pifcNative').on('hashchange.pifcNative', syncNativeRoute);

        window.setInterval(function () {
            if (isNativePrintfulRoute()) {
                syncNativeRoute();
            }
        }, 1200);
    }

    window.pifcAdminInitPanel = initPanel;

    $(function () {
        initSettingsPage();
        initProductSyncPage();
        initOrdersPage();
        initBulkFulfillPage();
        initCatalogPage();
        initShippingPage();
        bootNativeBridge();
    });
})(jQuery);
