/**
 * Wilden Manager - configurable native PrestaShop order grid.
 */
(function ($) {
  'use strict';

  $(function () {
    var config = window.wildenManagerNativeColumns;
    if (!config || !Array.isArray(config.columns)) {
      return;
    }

    var $panel = $('#order_grid_panel');
    if (!$panel.length) {
      $panel = $('[id*="order_grid"]').first();
    }
    if (!$panel.length) {
      return;
    }

    function getSelectedColumns() {
      if (config.storageKey && window.localStorage) {
        try {
          var stored = JSON.parse(window.localStorage.getItem(config.storageKey));
          if (Array.isArray(stored) && stored.length) {
            return stored;
          }
        } catch (error) {
          window.localStorage.removeItem(config.storageKey);
        }
      }

      return Array.isArray(config.selected) ? config.selected : [];
    }

    function applyColumns(ids) {
      var $table = $('#order_grid_table');
      if (!$table.length) {
        return;
      }

      var managed = config.columns.map(function (column) { return column.id; });
      managed.forEach(function (id) {
        var visible = ids.indexOf(id) !== -1;
        $table.find('thead [data-column-id="' + id + '"]').toggle(visible);
        $table.find('tbody .column-' + id).toggle(visible);
      });

      var currentIds = [];
      $table.find('thead tr.column-headers [data-column-id]').each(function () {
        currentIds.push($(this).data('column-id'));
      });
      var orderedIds = [];
      if (currentIds.indexOf('orders_bulk') !== -1) {
        orderedIds.push('orders_bulk');
      }
      ids.forEach(function (id) {
        if (currentIds.indexOf(id) !== -1 && orderedIds.indexOf(id) === -1) {
          orderedIds.push(id);
        }
      });
      currentIds.forEach(function (id) {
        if (managed.indexOf(id) === -1 && id !== 'orders_bulk' && id !== 'actions') {
          orderedIds.push(id);
        }
      });
      if (currentIds.indexOf('actions') !== -1) {
        orderedIds.push('actions');
      }

      $table.find('tr').each(function () {
        var $row = $(this);
        orderedIds.forEach(function (id) {
          var $cell = $row.children('[data-column-id="' + id + '"], .column-' + id).first();
          if ($cell.length) {
            $row.append($cell);
          }
        });
      });
    }

    config.selected = getSelectedColumns();
    applyColumns(config.selected);

    function getSelectedOrderIds() {
      var ids = [];
      $('#order_grid_table tbody input[name="order_orders_bulk[]"]:checked, ' +
        '#order_grid_table tbody input[name$="[orders_bulk][]"]:checked, ' +
        '#order_grid_table tbody .js-bulk-action-checkbox:checked').each(function () {
        var id = parseInt($(this).val(), 10);
        if (id > 0 && ids.indexOf(id) === -1) {
          ids.push(id);
        }
      });
      ids.sort(function (a, b) { return a - b; });
      return ids;
    }

    function setBulkMessage($modal, type, message) {
      $modal.find('.wm-bulk-message')
        .removeClass('alert-info alert-success alert-danger alert-warning')
        .addClass('alert-' + type)
        .prop('hidden', !message)
        .text(message || '');
    }

    function appendResultList($container, title, items, key) {
      if (!Array.isArray(items) || !items.length) {
        return;
      }
      var $section = $('<div class="wm-bulk-result-section"><strong></strong><ul></ul></div>');
      $section.find('strong').text(title + ': ' + items.length);
      items.forEach(function (item) {
        var message = '#' + parseInt(item.id_order, 10);
        if (item[key]) {
          message += ' — ' + item[key];
        }
        $('<li></li>').text(message).appendTo($section.find('ul'));
      });
      $container.append($section);
    }

    var modalId = 'wm-native-columns-modal';
    var $modal = $('#' + modalId);
    if (!$modal.length) {
      $modal = $(
        '<div class="modal fade" id="' + modalId + '" tabindex="-1" role="dialog" aria-hidden="true">' +
          '<div class="modal-dialog" role="document"><div class="modal-content">' +
            '<div class="modal-header"><h4 class="modal-title"></h4><button type="button" class="close" data-dismiss="modal">&times;</button></div>' +
            '<div class="modal-body"><div class="wm-native-columns-list"></div><div class="alert alert-danger wm-native-columns-error" hidden></div></div>' +
            '<div class="modal-footer"><button type="button" class="btn btn-outline-secondary wm-native-columns-reset"></button><button type="button" class="btn btn-outline-secondary" data-dismiss="modal"></button><button type="button" class="btn btn-primary wm-native-columns-save"></button></div>' +
          '</div></div>' +
        '</div>'
      ).appendTo('body');
      $modal.find('.modal-title').text(config.title);
      $modal.find('.wm-native-columns-reset').text(config.reset);
      $modal.find('[data-dismiss="modal"]').last().text(config.cancel);
      $modal.find('.wm-native-columns-save').text(config.save);
    }

    function renderColumns(ids) {
      var selected = Array.isArray(ids) ? ids : [];
      var ordered = [];
      selected.forEach(function (id) {
        var match = config.columns.find(function (column) { return column.id === id; });
        if (match) {
          ordered.push(match);
        }
      });
      config.columns.forEach(function (column) {
        if (selected.indexOf(column.id) === -1) {
          ordered.push(column);
        }
      });

      var $list = $modal.find('.wm-native-columns-list').empty();
      ordered.forEach(function (column) {
        var checked = selected.indexOf(column.id) !== -1 ? ' checked' : '';
        $('<div class="wm-native-column-row" data-column-id="' + column.id + '">' +
            '<label><input type="checkbox"' + checked + '> <span></span></label>' +
            '<span class="wm-native-column-order"><button type="button" class="btn btn-sm btn-outline-secondary wm-column-up" title="Up"><i class="material-icons">arrow_upward</i></button><button type="button" class="btn btn-sm btn-outline-secondary wm-column-down" title="Down"><i class="material-icons">arrow_downward</i></button></span>' +
          '</div>')
          .find('label span').text(column.label).end()
          .appendTo($list);
      });
    }

    var $button = $('<button type="button" class="btn btn-outline-secondary wm-native-columns-button"><i class="material-icons">view_column</i> <span></span></button>');
    $button.find('span').text(config.button);
    var $actions = $panel.find('.grid-actions').first();
    if ($actions.length) {
      $actions.prepend($button);
    } else {
      $panel.find('.card-header').first().append($button);
    }

    var bulkModalId = 'wm-bulk-status-modal';
    var $bulkModal = $('#' + bulkModalId);
    if (!$bulkModal.length) {
      $bulkModal = $(
        '<div class="modal fade" id="' + bulkModalId + '" tabindex="-1" role="dialog" aria-hidden="true">' +
          '<div class="modal-dialog modal-lg" role="document"><div class="modal-content">' +
            '<div class="modal-header"><h4 class="modal-title"></h4><button type="button" class="close" data-dismiss="modal">&times;</button></div>' +
            '<div class="modal-body">' +
              '<p class="wm-bulk-selection"></p>' +
              '<div class="form-group"><label class="wm-bulk-state-label"></label><select class="form-control wm-bulk-state"><option value=""></option></select></div>' +
              '<div class="form-check"><label><input type="checkbox" class="wm-bulk-email"> <span class="wm-bulk-email-label"></span></label></div>' +
              '<p class="text-muted wm-bulk-help"></p>' +
              '<div class="alert wm-bulk-message" hidden></div>' +
              '<div class="wm-bulk-preview-table"></div>' +
              '<div class="wm-bulk-results"></div>' +
            '</div>' +
            '<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal"></button><button type="button" class="btn btn-outline-primary wm-bulk-preview"></button><button type="button" class="btn btn-primary wm-bulk-execute" disabled></button></div>' +
          '</div></div>' +
        '</div>'
      ).appendTo('body');
      $bulkModal.find('.modal-title').text(config.bulkTitle);
      $bulkModal.find('.wm-bulk-state-label').text(config.bulkState);
      $bulkModal.find('.wm-bulk-state option').first().text(config.bulkSelectState);
      $bulkModal.find('.wm-bulk-email-label').text(config.bulkSendEmail);
      $bulkModal.find('.wm-bulk-help').text(config.bulkPreviewHelp);
      $bulkModal.find('[data-dismiss="modal"]').last().text(config.cancel);
      $bulkModal.find('.wm-bulk-preview').text(config.bulkPreview);
      $bulkModal.find('.wm-bulk-execute').text(config.bulkExecute);
      (config.orderStates || []).forEach(function (state) {
        $('<option></option>').val(state.id_order_state).text(state.name).appendTo($bulkModal.find('.wm-bulk-state'));
      });
    }

    var $bulkButton = $('<button type="button" class="btn btn-outline-secondary wm-bulk-status-button"><i class="material-icons">published_with_changes</i> <span></span></button>');
    $bulkButton.find('span').text(config.bulkButton);
    if ($actions.length) {
      $actions.prepend($bulkButton);
    } else {
      $panel.find('.card-header').first().append($bulkButton);
    }

    var exportModalId = 'wm-export-modal';
    var $exportModal = $('#' + exportModalId);
    if (!$exportModal.length) {
      $exportModal = $(
        '<div class="modal fade" id="' + exportModalId + '" tabindex="-1" role="dialog" aria-hidden="true">' +
          '<div class="modal-dialog" role="document"><div class="modal-content">' +
            '<div class="modal-header"><h4 class="modal-title"></h4><button type="button" class="close" data-dismiss="modal">&times;</button></div>' +
            '<div class="modal-body">' +
              '<p class="wm-export-selection"></p>' +
              '<div class="form-group"><label class="wm-export-format-label"></label><select class="form-control wm-export-format"><option value="csv"></option></select></div>' +
              '<div class="form-group"><label class="wm-export-fields-label"></label><div class="wm-export-columns"></div></div>' +
              '<div class="alert alert-danger wm-export-error" hidden></div>' +
            '</div>' +
            '<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal"></button><button type="button" class="btn btn-primary wm-export-download"></button></div>' +
          '</div></div>' +
        '</div>'
      ).appendTo('body');
      $exportModal.find('.modal-title').text(config.exportTitle);
      $exportModal.find('.wm-export-format-label').text(config.exportFormat);
      $exportModal.find('.wm-export-fields-label').text(config.exportFields);
      $exportModal.find('.wm-export-format option[value="csv"]').text(config.exportCsv);
      if (config.xlsxAvailable) {
        $('<option value="xlsx"></option>').text(config.exportXlsx).appendTo($exportModal.find('.wm-export-format'));
      }
      $exportModal.find('[data-dismiss="modal"]').last().text(config.cancel);
      $exportModal.find('.wm-export-download').text(config.exportDownload);
    }

    function getSavedExportColumns() {
      var defaults = ['id_order', 'reference', 'date_add', 'customer', 'email', 'total_paid_tax_incl', 'state_name', 'carrier_name'];
      if (config.exportStorageKey && window.localStorage) {
        try {
          var stored = JSON.parse(window.localStorage.getItem(config.exportStorageKey));
          if (Array.isArray(stored) && stored.length) {
            return stored;
          }
        } catch (error) {
          window.localStorage.removeItem(config.exportStorageKey);
        }
      }
      return defaults;
    }

    function renderExportColumns() {
      var selected = getSavedExportColumns();
      var $list = $exportModal.find('.wm-export-columns').empty();
      (config.exportColumns || []).forEach(function (column) {
        var $label = $('<label class="wm-export-column"><input type="checkbox"> <span></span></label>');
        $label.find('input').val(column.id).prop('checked', selected.indexOf(column.id) !== -1);
        $label.find('span').text(column.label);
        $list.append($label);
      });
    }

    var $exportButton = $('<button type="button" class="btn btn-outline-secondary wm-export-button"><i class="material-icons">download</i> <span></span></button>');
    $exportButton.find('span').text(config.exportButton);
    if ($actions.length) {
      $actions.prepend($exportButton);
    } else {
      $panel.find('.card-header').first().append($exportButton);
    }

    $exportButton.on('click', function () {
      var ids = getSelectedOrderIds();
      if (!ids.length) {
        window.alert(config.bulkNoSelection);
        return;
      }
      renderExportColumns();
      $exportModal.data('order-ids', ids);
      $exportModal.find('.wm-export-selection').text(ids.length + ' ' + config.bulkSelected);
      $exportModal.find('.wm-export-error').prop('hidden', true).text('');
      $exportModal.modal('show');
    });

    $exportModal.on('click', '.wm-export-download', function () {
      var columns = [];
      $exportModal.find('.wm-export-columns input:checked').each(function () {
        columns.push($(this).val());
      });
      if (!columns.length) {
        $exportModal.find('.wm-export-error').prop('hidden', false).text(config.exportNoColumns);
        return;
      }
      if (config.exportStorageKey && window.localStorage) {
        window.localStorage.setItem(config.exportStorageKey, JSON.stringify(columns));
      }

      var $form = $('<form method="post" hidden></form>').attr('action', config.exportUrl).appendTo('body');
      ($exportModal.data('order-ids') || []).forEach(function (id) {
        $('<input type="hidden" name="order_ids[]">').val(id).appendTo($form);
      });
      columns.forEach(function (column) {
        $('<input type="hidden" name="export_columns[]">').val(column).appendTo($form);
      });
      $('<input type="hidden" name="export_format">').val($exportModal.find('.wm-export-format').val()).appendTo($form);
      $form.trigger('submit');
      window.setTimeout(function () { $form.remove(); }, 1000);
      $exportModal.modal('hide');
    });

    var documentModalId = 'wm-document-modal';
    var $documentModal = $('#' + documentModalId);
    if (!$documentModal.length) {
      $documentModal = $(
        '<div class="modal fade" id="' + documentModalId + '" tabindex="-1" role="dialog" aria-hidden="true">' +
          '<div class="modal-dialog modal-lg" role="document"><div class="modal-content">' +
            '<div class="modal-header"><h4 class="modal-title"></h4><button type="button" class="close" data-dismiss="modal">&times;</button></div>' +
            '<div class="modal-body"><p class="wm-document-selection"></p><div class="alert wm-document-message" hidden></div><div class="wm-document-table"></div>' +
              '<div class="form-group wm-document-type-group" hidden><label></label><select class="form-control wm-document-type"><option value="invoice"></option><option value="delivery"></option><option value="both"></option></select></div>' +
            '</div>' +
            '<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal"></button><button type="button" class="btn btn-primary wm-document-download" disabled></button></div>' +
          '</div></div>' +
        '</div>'
      ).appendTo('body');
      $documentModal.find('.modal-title').text(config.documentTitle);
      $documentModal.find('.wm-document-type-group label').text(config.documentType);
      $documentModal.find('.wm-document-type option[value="invoice"]').text(config.documentInvoice);
      $documentModal.find('.wm-document-type option[value="delivery"]').text(config.documentDelivery);
      $documentModal.find('.wm-document-type option[value="both"]').text(config.documentBoth);
      $documentModal.find('[data-dismiss="modal"]').last().text(config.cancel);
      $documentModal.find('.wm-document-download').text(config.documentDownload);
    }

    function setDocumentMessage(type, message) {
      $documentModal.find('.wm-document-message')
        .removeClass('alert-info alert-success alert-danger alert-warning')
        .addClass('alert-' + type)
        .prop('hidden', !message)
        .text(message || '');
    }

    var $documentButton = $('<button type="button" class="btn btn-outline-secondary wm-document-button"><i class="material-icons">picture_as_pdf</i> <span></span></button>');
    $documentButton.find('span').text(config.documentButton);
    if ($actions.length) {
      $actions.prepend($documentButton);
    } else {
      $panel.find('.card-header').first().append($documentButton);
    }

    $documentButton.on('click', function () {
      var ids = getSelectedOrderIds();
      if (!ids.length) {
        window.alert(config.bulkNoSelection);
        return;
      }
      if (ids.length > parseInt(config.maxBulk, 10)) {
        window.alert(config.bulkTooMany + ' ' + config.maxBulk + '.');
        return;
      }

      $documentModal.data('order-ids', ids);
      $documentModal.find('.wm-document-selection').text(ids.length + ' ' + config.bulkSelected);
      $documentModal.find('.wm-document-table').empty();
      $documentModal.find('.wm-document-type-group').prop('hidden', true);
      $documentModal.find('.wm-document-download').prop('disabled', true);
      setDocumentMessage('info', config.documentPreview + '…');
      $documentModal.modal('show');

      $.ajax({
        url: config.documentPreviewUrl,
        method: 'POST',
        dataType: 'json',
        data: { order_ids: ids }
      }).done(function (response) {
        if (!response || !response.success || !response.preview) {
          setDocumentMessage('danger', (response && response.error) || config.documentError);
          return;
        }
        var preview = response.preview;
        var $table = $('<div class="table-responsive"><table class="table"><thead><tr><th>ID</th><th></th><th></th><th></th></tr></thead><tbody></tbody></table></div>');
        var $headers = $table.find('th');
        $headers.eq(1).text(config.documentReference);
        $headers.eq(2).text(config.documentInvoice);
        $headers.eq(3).text(config.documentDelivery);
        (preview.orders || []).forEach(function (order) {
          var $row = $('<tr><td></td><td></td><td></td><td></td></tr>');
          $row.children().eq(0).text(order.id_order);
          $row.children().eq(1).text(order.reference);
          $row.children().eq(2).text(order.has_invoice ? config.documentAvailable : config.documentMissing).toggleClass('text-success', order.has_invoice).toggleClass('text-muted', !order.has_invoice);
          $row.children().eq(3).text(order.has_delivery ? config.documentAvailable : config.documentMissing).toggleClass('text-success', order.has_delivery).toggleClass('text-muted', !order.has_delivery);
          $table.find('tbody').append($row);
        });
        $documentModal.find('.wm-document-table').append($table);
        var $type = $documentModal.find('.wm-document-type');
        $type.find('option[value="invoice"]').prop('disabled', preview.invoice_count < 1);
        $type.find('option[value="delivery"]').prop('disabled', preview.delivery_count < 1);
        $type.find('option[value="both"]').prop('disabled', !config.documentZipAvailable || preview.invoice_count < 1 || preview.delivery_count < 1);
        var firstType = preview.invoice_count > 0 ? 'invoice' : (preview.delivery_count > 0 ? 'delivery' : '');
        $type.val(firstType);
        $documentModal.find('.wm-document-type-group').prop('hidden', !firstType);
        $documentModal.find('.wm-document-download').prop('disabled', !firstType);
        setDocumentMessage(firstType ? 'success' : 'warning', config.documentInvoicesCount + ': ' + preview.invoice_count + '. ' + config.documentDeliveriesCount + ': ' + preview.delivery_count + '.');
      }).fail(function (xhr) {
        var response = xhr.responseJSON || {};
        setDocumentMessage('danger', response.error || config.documentError);
      });
    });

    $documentModal.on('click', '.wm-document-download', function () {
      var $form = $('<form method="post" hidden></form>').attr('action', config.documentDownloadUrl).appendTo('body');
      ($documentModal.data('order-ids') || []).forEach(function (id) {
        $('<input type="hidden" name="order_ids[]">').val(id).appendTo($form);
      });
      $('<input type="hidden" name="document_type">').val($documentModal.find('.wm-document-type').val()).appendTo($form);
      $form.trigger('submit');
      window.setTimeout(function () { $form.remove(); }, 1000);
      $documentModal.modal('hide');
    });

    function resetBulkPreview() {
      $bulkModal.removeData('preview').removeData('completed');
      $bulkModal.find('.wm-bulk-execute').prop('disabled', true).text(config.bulkExecute);
      $bulkModal.find('.wm-bulk-preview').prop('disabled', false);
      $bulkModal.find('.wm-bulk-preview-table, .wm-bulk-results').empty();
      setBulkMessage($bulkModal, 'info', '');
    }

    $bulkButton.on('click', function () {
      var ids = getSelectedOrderIds();
      if (!ids.length) {
        window.alert(config.bulkNoSelection);
        return;
      }
      if (ids.length > parseInt(config.maxBulk, 10)) {
        window.alert(config.bulkTooMany + ' ' + config.maxBulk + '.');
        return;
      }
      resetBulkPreview();
      $bulkModal.data('order-ids', ids);
      $bulkModal.find('.wm-bulk-selection').text(ids.length + ' ' + config.bulkSelected);
      $bulkModal.modal('show');
    });

    $bulkModal.on('change', '.wm-bulk-state, .wm-bulk-email', resetBulkPreview);

    $bulkModal.on('click', '.wm-bulk-preview', function () {
      var ids = $bulkModal.data('order-ids') || [];
      var targetState = parseInt($bulkModal.find('.wm-bulk-state').val(), 10);
      if (!targetState) {
        setBulkMessage($bulkModal, 'danger', config.bulkSelectState);
        return;
      }

      var $previewButton = $(this).prop('disabled', true);
      setBulkMessage($bulkModal, 'info', config.bulkPreview + '…');
      $.ajax({
        url: config.bulkPreviewUrl,
        method: 'POST',
        dataType: 'json',
        data: { order_ids: ids, target_state: targetState }
      }).done(function (response) {
        if (!response || !response.success || !response.preview) {
          setBulkMessage($bulkModal, 'danger', (response && response.error) || config.bulkError);
          return;
        }

        var preview = response.preview;
        var $table = $('<div class="table-responsive"><table class="table"><thead><tr><th>ID</th><th>Reference</th><th>Customer</th><th>Current status</th><th>New status</th></tr></thead><tbody></tbody></table></div>');
        (preview.orders || []).forEach(function (order) {
          var $row = $('<tr><td></td><td></td><td></td><td></td><td></td></tr>');
          var values = [order.id_order, order.reference, order.customer, order.state_name || '', preview.target_state.name];
          $row.children().each(function (index) { $(this).text(values[index]); });
          $table.find('tbody').append($row);
        });
        $bulkModal.find('.wm-bulk-preview-table').empty().append($table);
        $bulkModal.data('preview', preview);
        $bulkModal.find('.wm-bulk-execute').prop('disabled', false);
        setBulkMessage($bulkModal, 'success', config.bulkPreviewReady);
      }).fail(function (xhr) {
        var response = xhr.responseJSON || {};
        setBulkMessage($bulkModal, 'danger', response.error || config.bulkError);
      }).always(function () {
        $previewButton.prop('disabled', false);
      });
    });

    $bulkModal.on('click', '.wm-bulk-execute', function () {
      if ($bulkModal.data('completed')) {
        window.location.reload();
        return;
      }

      var preview = $bulkModal.data('preview');
      if (!preview) {
        return;
      }
      var $execute = $(this).prop('disabled', true);
      setBulkMessage($bulkModal, 'info', config.bulkExecute + '…');
      $.ajax({
        url: config.bulkExecuteUrl,
        method: 'POST',
        dataType: 'json',
        data: {
          order_ids: preview.ids,
          target_state: preview.target_state.id,
          preview_timestamp: preview.timestamp,
          preview_snapshot: preview.snapshot,
          preview_signature: preview.signature,
          send_email: $bulkModal.find('.wm-bulk-email').prop('checked') ? 1 : 0
        }
      }).done(function (response) {
        if (!response || !response.success || !response.results) {
          setBulkMessage($bulkModal, 'danger', (response && response.error) || config.bulkError);
          $execute.prop('disabled', false);
          return;
        }
        var results = response.results;
        var $results = $bulkModal.find('.wm-bulk-results').empty();
        var applied = results.success.filter(function (item) { return !item.skipped; });
        var skipped = results.success.filter(function (item) { return item.skipped; });
        appendResultList($results, config.bulkSkipped, skipped, 'message');
        appendResultList($results, config.bulkWarnings, results.warnings, 'warning');
        appendResultList($results, config.bulkErrors, results.errors, 'error');
        setBulkMessage($bulkModal, results.errors.length ? 'warning' : 'success', config.bulkSuccess + ': ' + applied.length + '.');
        $bulkModal.data('completed', true);
        $bulkModal.find('.wm-bulk-preview').prop('disabled', true);
        $execute.prop('disabled', false).text(config.bulkReload);
      }).fail(function (xhr) {
        var response = xhr.responseJSON || {};
        setBulkMessage($bulkModal, 'danger', response.error || config.bulkError);
        $execute.prop('disabled', false);
      });
    });

    $button.on('click', function () {
      renderColumns(config.selected);
      $modal.find('.wm-native-columns-error').prop('hidden', true).text('');
      $modal.modal('show');
    });

    $modal.on('click', '.wm-column-up, .wm-column-down', function () {
      var $row = $(this).closest('.wm-native-column-row');
      if ($(this).hasClass('wm-column-up')) {
        $row.prev().before($row);
      } else {
        $row.next().after($row);
      }
    });

    $modal.on('click', '.wm-native-columns-reset', function () {
      renderColumns(config.columns.map(function (column) { return column.id; }));
    });

    $modal.on('click', '.wm-native-columns-save', function () {
      var columns = [];
      $modal.find('.wm-native-column-row').each(function () {
        if ($(this).find('input[type="checkbox"]').prop('checked')) {
          columns.push($(this).data('column-id'));
        }
      });

      var $save = $(this).prop('disabled', true);
      $.ajax({
        url: config.saveUrl,
        method: 'POST',
        dataType: 'json',
        data: { columns: columns }
      }).done(function (response) {
        if (response && response.success) {
          config.selected = Array.isArray(response.columns) ? response.columns : columns;
          if (config.storageKey && window.localStorage) {
            window.localStorage.setItem(config.storageKey, JSON.stringify(config.selected));
          }
          applyColumns(config.selected);
          window.location.reload();
          return;
        }
        $modal.find('.wm-native-columns-error').prop('hidden', false).text((response && response.error) || config.error);
      }).fail(function () {
        $modal.find('.wm-native-columns-error').prop('hidden', false).text(config.error);
      }).always(function () {
        $save.prop('disabled', false);
      });
    });
  });
})(window.jQuery);
