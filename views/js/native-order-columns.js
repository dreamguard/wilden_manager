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

    var deleteModalId = 'wm-delete-order-modal';
    var $deleteModal = $('#' + deleteModalId);

    function initializeDeleteModal() {
      if (!config.canDeleteOrders || $deleteModal.length) {
        return;
      }
      $deleteModal = $(
        '<div class="modal fade" id="' + deleteModalId + '" tabindex="-1" role="dialog" aria-hidden="true">' +
          '<div class="modal-dialog" role="document"><div class="modal-content">' +
            '<div class="modal-header"><h4 class="modal-title"></h4><button type="button" class="close" data-dismiss="modal">&times;</button></div>' +
            '<div class="modal-body"><div class="alert alert-danger wm-delete-warning"></div>' +
              '<p class="wm-delete-order-summary"></p>' +
              '<div class="form-group"><label class="wm-delete-confirm-label"></label><input type="text" inputmode="numeric" autocomplete="off" class="form-control wm-delete-confirm-input"></div>' +
              '<div class="alert alert-danger wm-delete-error" hidden></div>' +
            '</div>' +
            '<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal"></button><button type="button" class="btn btn-danger wm-delete-confirm-button" disabled></button></div>' +
          '</div></div>' +
        '</div>'
      ).appendTo('body');
      $deleteModal.find('.modal-title').text(config.deleteTitle);
      $deleteModal.find('.wm-delete-warning').text(config.deleteWarning);
      $deleteModal.find('.wm-delete-confirm-label').text(config.deletePrompt);
      $deleteModal.find('[data-dismiss="modal"]').last().text(config.cancel);
      $deleteModal.find('.wm-delete-confirm-button').text(config.deleteConfirm);
    }

    initializeDeleteModal();

    // The order-page button is matched by its data attributes too, so it keeps working even if
    // the core template drops CSS classes passed by the module.
    $(document).on('click', '.wm-delete-order-trigger, [data-wm-delete-order], a[data-order-id][data-order-reference]', function (event) {
      event.preventDefault();
      var $trigger = $(this);
      if ($trigger.hasClass('disabled') || $trigger.attr('aria-disabled') === 'true') {
        window.alert($trigger.attr('title') || config.deleteInvoiceBlocked);
        return;
      }
      initializeDeleteModal();
      var id = parseInt($trigger.attr('data-order-id'), 10);
      if (!id || !$deleteModal.length) {
        return;
      }
      var reference = $trigger.attr('data-order-reference') || '';
      $deleteModal.data('order-id', id);
      $deleteModal.find('.wm-delete-order-summary').text('#' + id + (reference ? ' — ' + reference : ''));
      $deleteModal.find('.wm-delete-confirm-input').val('');
      $deleteModal.find('.wm-delete-confirm-button').prop('disabled', true);
      $deleteModal.find('.wm-delete-error').prop('hidden', true).text('');
      $deleteModal.modal('show');
      window.setTimeout(function () { $deleteModal.find('.wm-delete-confirm-input').trigger('focus'); }, 250);
    });

    $deleteModal.on('input', '.wm-delete-confirm-input', function () {
      var expected = String($deleteModal.data('order-id') || '');
      $deleteModal.find('.wm-delete-confirm-button').prop('disabled', $.trim($(this).val()) !== expected);
    });

    $deleteModal.on('click', '.wm-delete-confirm-button', function () {
      var $button = $(this).prop('disabled', true);
      var id = parseInt($deleteModal.data('order-id'), 10);
      $.ajax({
        url: config.deleteUrl,
        method: 'POST',
        dataType: 'json',
        data: { id_order: id, confirmation: $.trim($deleteModal.find('.wm-delete-confirm-input').val()) }
      }).done(function (response) {
        if (response && response.success) {
          var message = config.deleteSuccess;
          if (response.stock_action === 'reinject') {
            message += '\n' + config.deleteStockReinjected + ' ' + (parseInt(response.stock_units_reinjected, 10) || 0);
          } else if (response.stock_action === 'already') {
            message += '\n' + config.deleteStockAlready;
          } else if (response.stock_action === 'disabled') {
            message += '\n' + config.deleteStockDisabled;
          }
          window.alert(message);
          window.location.href = config.ordersUrl;
          return;
        }
        $deleteModal.find('.wm-delete-error').prop('hidden', false).text((response && response.error) || config.deleteError);
      }).fail(function (xhr) {
        var response = xhr.responseJSON || {};
        $deleteModal.find('.wm-delete-error').prop('hidden', false).text(response.error || config.deleteError);
      }).always(function () {
        $button.prop('disabled', false);
      });
    });

    initHistoryEditor();

    /**
     * Status history editor on the order page: Edit (date/time and employee) on every
     * entry, Delete on every entry except the most recent one.
     */
    function initHistoryEditor() {
      var match = window.location.pathname.match(/\/sell\/orders\/(\d+)\/view/);
      if (!config.canEditHistory || !config.historyListUrl || !match) {
        return;
      }
      var idOrder = parseInt(match[1], 10);
      var history = null;
      var $historyModal = null;

      $.ajax({
        url: config.historyListUrl,
        method: 'GET',
        dataType: 'json',
        data: { id_order: idOrder }
      }).done(function (response) {
        if (!response || !response.success || !response.history || !response.history.entries.length) {
          return;
        }
        history = response.history;
        var $rows = findHistoryRows(history.entries);
        if (!$rows) {
          return;
        }
        $rows.each(function (index) {
          addHistoryButtons($(this), history.entries[index]);
        });
      });

      // The history table is identified by its content: same number of rows and each row
      // showing the expected status, in PrestaShop's order. Without an exact match nothing
      // is added, so a button can never point at the wrong entry.
      function findHistoryRows(entries) {
        var found = null;
        var $tables = $('#historyTabContent table');
        if (!$tables.length) {
          $tables = $('table');
        }
        $tables.each(function () {
          var $rows = $(this).children('tbody').children('tr');
          if ($rows.length !== entries.length) {
            return;
          }
          var matches = true;
          $rows.each(function (index) {
            if ($(this).text().indexOf(entries[index].state_name) === -1) {
              matches = false;
              return false;
            }
          });
          if (matches) {
            found = $rows;
            return false;
          }
        });
        return found;
      }

      function addHistoryButtons($row, entry) {
        var $cell = $('<td class="text-right text-nowrap wm-history-actions"></td>');
        $('<button type="button" class="btn btn-sm btn-outline-secondary wm-history-edit"><i class="material-icons">edit</i> </button>')
          .append(document.createTextNode(config.historyEdit))
          .on('click', function () { openEditModal(entry); })
          .appendTo($cell);
        if (entry.can_delete) {
          $('<button type="button" class="btn btn-sm btn-outline-secondary wm-history-delete"><i class="material-icons">delete</i> </button>')
            .append(document.createTextNode(config.historyDelete))
            .on('click', function () { deleteEntry(entry); })
            .appendTo($cell);
        }
        $row.append($cell);
      }

      function buildEditModal() {
        if ($historyModal) {
          return $historyModal;
        }
        $historyModal = $(
          '<div class="modal fade" id="wm-history-modal" tabindex="-1" role="dialog" aria-hidden="true">' +
            '<div class="modal-dialog" role="document"><div class="modal-content">' +
              '<div class="modal-header"><h4 class="modal-title"></h4><button type="button" class="close" data-dismiss="modal">&times;</button></div>' +
              '<div class="modal-body">' +
                '<p class="wm-history-state font-weight-bold"></p>' +
                '<div class="alert alert-info wm-history-help"></div>' +
                '<div class="form-group"><label class="wm-history-date-label" for="wm-history-date"></label>' +
                  '<input type="datetime-local" step="1" class="form-control" id="wm-history-date"></div>' +
                '<div class="form-group"><label class="wm-history-employee-label" for="wm-history-employee"></label>' +
                  '<select class="form-control" id="wm-history-employee"></select></div>' +
                '<div class="alert alert-danger wm-history-error" hidden></div>' +
              '</div>' +
              '<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal"></button>' +
                '<button type="button" class="btn btn-primary wm-history-save"></button></div>' +
            '</div></div>' +
          '</div>'
        ).appendTo('body');
        $historyModal.find('.modal-title').text(config.historyEditTitle);
        $historyModal.find('.wm-history-help').text(config.historyEditHelp);
        $historyModal.find('.wm-history-date-label').text(config.historyDate);
        $historyModal.find('.wm-history-employee-label').text(config.historyEmployee);
        $historyModal.find('[data-dismiss="modal"]').last().text(config.cancel);
        $historyModal.find('.wm-history-save').text(config.historySave);

        var $select = $historyModal.find('#wm-history-employee');
        $('<option value="0"></option>').text(config.historyNoEmployee).appendTo($select);
        history.employees.forEach(function (employee) {
          $('<option></option>')
            .attr('value', employee.id_employee)
            .text(employee.name + (employee.active ? '' : ' (' + config.historyInactive + ')'))
            .appendTo($select);
        });

        $historyModal.on('click', '.wm-history-save', function () {
          var $button = $(this).prop('disabled', true);
          $.ajax({
            url: config.historyUpdateUrl,
            method: 'POST',
            dataType: 'json',
            data: {
              id_order: idOrder,
              id_order_history: $historyModal.data('entry-id'),
              date_add: $historyModal.find('#wm-history-date').val(),
              id_employee: $historyModal.find('#wm-history-employee').val()
            }
          }).done(function (response) {
            if (response && response.success) {
              window.location.reload();
              return;
            }
            showModalError((response && response.error) || config.historyError);
          }).fail(function (xhr) {
            showModalError((xhr.responseJSON && xhr.responseJSON.error) || config.historyError);
          }).always(function () {
            $button.prop('disabled', false);
          });
        });

        return $historyModal;
      }

      function showModalError(message) {
        $historyModal.find('.wm-history-error').prop('hidden', false).text(message);
      }

      function openEditModal(entry) {
        var $modal = buildEditModal();
        $modal.data('entry-id', entry.id_order_history);
        $modal.find('.wm-history-state').text(entry.state_name);
        $modal.find('#wm-history-date').val(String(entry.date_add).replace(' ', 'T'));
        $modal.find('#wm-history-employee').val(String(entry.id_employee));
        $modal.find('.wm-history-error').prop('hidden', true).text('');
        $modal.modal('show');
      }

      function deleteEntry(entry) {
        if (!window.confirm(config.historyDeleteConfirm + '\n\n' + entry.state_name + ' — ' + entry.date_add)) {
          return;
        }
        $.ajax({
          url: config.historyDeleteUrl,
          method: 'POST',
          dataType: 'json',
          data: { id_order: idOrder, id_order_history: entry.id_order_history }
        }).done(function (response) {
          if (response && response.success) {
            window.location.reload();
            return;
          }
          window.alert((response && response.error) || config.historyError);
        }).fail(function (xhr) {
          window.alert((xhr.responseJSON && xhr.responseJSON.error) || config.historyError);
        });
      }
    }

    var $panel = $('#order_grid_panel');
    if (!$panel.length) {
      $panel = $('[id*="order_grid"]').first();
    }
    if (!$panel.length) {
      return;
    }

    function addDeleteActions() {
      if (!config.canDeleteOrders || !config.deleteEligibilityUrl) {
        return;
      }
      var ids = [];
      $('#order_grid_table tbody tr').each(function () {
        var id = parseInt($(this).find('input[name="order_orders_bulk[]"], input[name$="[orders_bulk][]"], .js-bulk-action-checkbox').first().val(), 10);
        if (id > 0 && ids.indexOf(id) === -1) {
          ids.push(id);
          $(this).attr('data-wm-order-id', id);
        }
      });
      if (!ids.length) {
        return;
      }
      $.ajax({
        url: config.deleteEligibilityUrl,
        method: 'POST',
        dataType: 'json',
        data: { order_ids: ids }
      }).done(function (response) {
        if (!response || !response.success || !response.orders) {
          return;
        }
        ids.forEach(function (id) {
          var info = response.orders[id] || response.orders[String(id)];
          var $row = $('#order_grid_table tbody tr[data-wm-order-id="' + id + '"]');
          var $cell = $row.find('.column-actions, [data-column-id="actions"]').last();
          if (!info || !$cell.length || $cell.find('.wm-delete-order-trigger').length) {
            return;
          }
          var disabled = !info.eligible;
          var $action = $('<a href="#" class="btn btn-link wm-delete-order-trigger" aria-label=""><i class="material-icons">delete</i></a>')
            .attr('data-order-id', id)
            .attr('data-order-reference', info.reference || '')
            .attr('aria-label', config.deleteActionLabel)
            .attr('title', disabled ? (info.reason || config.deleteInvoiceBlocked) : config.deleteActionLabel)
            .toggleClass('disabled', disabled)
            .attr('aria-disabled', disabled ? 'true' : 'false');
          $cell.append($action);
        });
      });
    }

    addDeleteActions();

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

    var $columnsMenuItem = $panel.find('.js-wm_columns-grid-action').first();

    if (config.canExport) {
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

      var $exportButton = $panel.find('.wm-export-button').first();

      $exportButton.on('click', function () {
      var ids = getSelectedOrderIds();
      if (!ids.length) {
        window.alert(config.selectionRequired);
        return;
      }
      renderExportColumns();
      $exportModal.data('order-ids', ids);
      $exportModal.find('.wm-export-selection').text(ids.length + ' ' + config.selectedOrders);
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
    }

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

    var $documentButton = $panel.find('.wm-document-button').first();

    $documentButton.on('click', function () {
      var ids = getSelectedOrderIds();
      if (!ids.length) {
        window.alert(config.selectionRequired);
        return;
      }
      if (ids.length > parseInt(config.maxDocuments, 10)) {
        window.alert(config.tooManyDocuments + ' ' + config.maxDocuments + '.');
        return;
      }

      $documentModal.data('order-ids', ids);
      $documentModal.find('.wm-document-selection').text(ids.length + ' ' + config.selectedOrders);
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

    $columnsMenuItem.on('click', function () {
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
