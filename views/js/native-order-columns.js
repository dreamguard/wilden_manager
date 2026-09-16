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

    var $filterForm = $('#order_filter_form');
    if (!$filterForm.length) {
      $filterForm = $panel.find('form').first();
    }

    function getGridPrefix() {
      var prefix = $panel.find('.ps-sortable-column[data-sort-prefix]').first().attr('data-sort-prefix');
      if (prefix) {
        return prefix;
      }
      var inputName = $filterForm.find(':input[name]').first().attr('name') || '';
      var match = inputName.match(/^([^[]+)\[/);

      return match ? match[1] : 'order';
    }

    function collectViewState() {
      var prefix = getGridPrefix();
      var grouped = {};
      $filterForm.find(':input[name]').each(function () {
        var $input = $(this);
        var type = String($input.attr('type') || '').toLowerCase();
        var name = String($input.attr('name') || '');
        if (!name || ['submit', 'reset', 'button', 'hidden'].indexOf(type) !== -1) {
          return;
        }
        if ((type === 'checkbox' || type === 'radio') && !$input.prop('checked')) {
          return;
        }
        var segments = [];
        name.replace(/\[([^\]]*)\]/g, function (whole, segment) {
          if (segment) {
            segments.push(segment);
          }
          return whole;
        });
        if (name.indexOf(prefix + '[') !== 0 || !segments.length) {
          return;
        }
        if (segments[0] === 'filters') {
          segments.shift();
        }
        if (!segments.length || segments[0] === 'actions' || segments[0].charAt(0) === '_') {
          return;
        }
        var values = $input.val();
        values = Array.isArray(values) ? values : [values];
        values = values.map(function (value) { return String(value || '').trim(); }).filter(Boolean);
        if (!values.length) {
          return;
        }
        var path = segments.join('.');
        if (!grouped[path]) {
          grouped[path] = { path: path, values: [], multiple: false };
        }
        grouped[path].multiple = grouped[path].multiple || $input.is('[multiple]') || /\[\]$/.test(name);
        values.forEach(function (value) {
          if (grouped[path].values.indexOf(value) === -1) {
            grouped[path].values.push(value);
          }
        });
      });

      var $currentSort = $panel.find('.ps-sortable-column[data-sort-is-current="true"]').first();
      var limit = parseInt($('#order_grid_table').attr('data-limit'), 10) || 50;

      return {
        native_grid: 1,
        filters: Object.keys(grouped).map(function (key) { return grouped[key]; }),
        order_by: $currentSort.attr('data-sort-col-name') || 'date_add',
        sort_order: $currentSort.attr('data-sort-direction') === 'asc' ? 'asc' : 'desc',
        limit: limit
      };
    }

    function buildViewUrl(view) {
      var url = new URL(config.ordersUrl, window.location.origin);
      var prefix = getGridPrefix();
      var state = view.state || {};
      var availableColumns = (config.columns || []).map(function (column) { return column.id; });
      (state.filters || []).forEach(function (filter) {
        var path = String(filter.path || '').split('.').filter(Boolean);
        if (!path.length || availableColumns.indexOf(path[0]) === -1) {
          return;
        }
        var parameter = prefix + '[filters]' + path.map(function (part) { return '[' + part + ']'; }).join('');
        if (filter.multiple) {
          parameter += '[]';
        }
        (filter.values || []).forEach(function (value) {
          url.searchParams.append(parameter, value);
        });
      });
      var orderBy = availableColumns.indexOf(state.order_by) !== -1 ? state.order_by : 'date_add';
      url.searchParams.set(prefix + '[orderBy]', orderBy);
      url.searchParams.set(prefix + '[sortOrder]', state.sort_order === 'asc' ? 'asc' : 'desc');
      url.searchParams.set(prefix + '[limit]', parseInt(state.limit, 10) || 50);
      url.searchParams.set(prefix + '[offset]', 0);
      url.searchParams.set('wm_saved_view', view.id);

      return url.toString();
    }

    function applySavedView(view) {
      if (!view) {
        return;
      }
      if (window.sessionStorage && config.viewStorageKey) {
        window.sessionStorage.setItem(config.viewStorageKey + '_default_applied', String(view.id));
      }
      $.ajax({
        url: config.saveUrl,
        method: 'POST',
        dataType: 'json',
        data: { columns: view.columns || [] }
      }).done(function (response) {
        if (!response || !response.success) {
          window.alert((response && response.error) || config.error);
          return;
        }
        if (window.localStorage && config.storageKey) {
          window.localStorage.setItem(config.storageKey, JSON.stringify(view.columns || []));
        }
        window.location.href = buildViewUrl(view);
      }).fail(function () {
        window.alert(config.error);
      });
    }

    var viewsModalId = 'wm-saved-view-modal';
    var $viewsModal = $('#' + viewsModalId);
    if (!$viewsModal.length) {
      $viewsModal = $(
        '<div class="modal fade" id="' + viewsModalId + '" tabindex="-1" role="dialog" aria-hidden="true">' +
          '<div class="modal-dialog" role="document"><div class="modal-content">' +
            '<div class="modal-header"><h4 class="modal-title"></h4><button type="button" class="close" data-dismiss="modal">&times;</button></div>' +
            '<div class="modal-body">' +
              '<div class="form-group"><label class="wm-view-name-label"></label><input type="text" maxlength="128" class="form-control wm-view-name"></div>' +
              '<div class="form-check"><label><input type="checkbox" class="wm-view-default"> <span></span></label></div>' +
              '<div class="form-check wm-view-replace-group"><label><input type="checkbox" class="wm-view-replace"> <span></span></label></div>' +
              '<div class="alert alert-danger wm-view-error" hidden></div>' +
            '</div>' +
            '<div class="modal-footer"><button type="button" class="btn btn-outline-danger wm-view-delete"></button><button type="button" class="btn btn-outline-secondary" data-dismiss="modal"></button><button type="button" class="btn btn-primary wm-view-save"></button></div>' +
          '</div></div>' +
        '</div>'
      ).appendTo('body');
      $viewsModal.find('.wm-view-name-label').text(config.viewsName);
      $viewsModal.find('.wm-view-default + span').text(config.viewsDefault);
      $viewsModal.find('.wm-view-replace + span').text(config.viewsReplace);
      $viewsModal.find('.wm-view-delete').text(config.viewsDelete);
      $viewsModal.find('[data-dismiss="modal"]').last().text(config.cancel);
      $viewsModal.find('.wm-view-save').text(config.viewsSave);
    }

    var $viewsControl = $(
      '<div class="wm-saved-views-control">' +
        '<label class="sr-only"></label>' +
        '<select class="form-control wm-saved-views-select"></select>' +
        '<button type="button" class="btn btn-outline-secondary wm-view-apply"></button>' +
        '<button type="button" class="btn btn-outline-secondary wm-view-edit"><i class="material-icons">edit</i></button>' +
        '<button type="button" class="btn btn-outline-secondary wm-view-new"><i class="material-icons">add</i> <span></span></button>' +
      '</div>'
    );
    $viewsControl.find('label').text(config.viewsLabel);
    $viewsControl.find('.wm-view-apply').text(config.viewsApply);
    $viewsControl.find('.wm-view-new span').text(config.viewsNew);
    if ($actions.length) {
      $actions.prepend($viewsControl);
    } else {
      $panel.find('.card-header').first().append($viewsControl);
    }

    function findSavedView(id) {
      id = parseInt(id, 10);

      return (config.savedViews || []).find(function (view) { return parseInt(view.id, 10) === id; });
    }

    function renderSavedViews(selectedId) {
      var $select = $viewsControl.find('.wm-saved-views-select').empty();
      $('<option value=""></option>').text(config.viewsNone).appendTo($select);
      (config.savedViews || []).forEach(function (view) {
        var label = view.name + (view.is_default ? ' (' + config.viewsDefaultSuffix + ')' : '');
        $('<option></option>').val(view.id).text(label).appendTo($select);
      });
      if (selectedId) {
        $select.val(String(selectedId));
      }
      var hasSelection = !!$select.val();
      $viewsControl.find('.wm-view-apply, .wm-view-edit').prop('disabled', !hasSelection);
    }

    var currentViewId = parseInt(new URL(window.location.href).searchParams.get('wm_saved_view'), 10) || 0;
    renderSavedViews(currentViewId);
    $viewsControl.on('change', '.wm-saved-views-select', function () {
      var enabled = !!$(this).val();
      $viewsControl.find('.wm-view-apply, .wm-view-edit').prop('disabled', !enabled);
    });
    $viewsControl.on('click', '.wm-view-apply', function () {
      applySavedView(findSavedView($viewsControl.find('.wm-saved-views-select').val()));
    });
    $viewsControl.on('click', '.wm-view-new', function () {
      $viewsModal.data('view-id', 0);
      $viewsModal.find('.modal-title').text(config.viewsTitleNew);
      $viewsModal.find('.wm-view-name').val('');
      $viewsModal.find('.wm-view-default').prop('checked', false);
      $viewsModal.find('.wm-view-replace').prop('checked', true);
      $viewsModal.find('.wm-view-replace-group').prop('hidden', true);
      $viewsModal.find('.wm-view-delete').prop('hidden', true);
      $viewsModal.find('.wm-view-error').prop('hidden', true).text('');
      $viewsModal.modal('show');
    });
    $viewsControl.on('click', '.wm-view-edit', function () {
      var view = findSavedView($viewsControl.find('.wm-saved-views-select').val());
      if (!view) {
        return;
      }
      $viewsModal.data('view-id', view.id);
      $viewsModal.find('.modal-title').text(config.viewsTitleEdit);
      $viewsModal.find('.wm-view-name').val(view.name);
      $viewsModal.find('.wm-view-default').prop('checked', !!view.is_default);
      $viewsModal.find('.wm-view-replace').prop('checked', false);
      $viewsModal.find('.wm-view-replace-group').prop('hidden', false);
      $viewsModal.find('.wm-view-delete').prop('hidden', false);
      $viewsModal.find('.wm-view-error').prop('hidden', true).text('');
      $viewsModal.modal('show');
    });
    $viewsModal.on('click', '.wm-view-save', function () {
      var idView = parseInt($viewsModal.data('view-id'), 10) || 0;
      var $save = $(this).prop('disabled', true);
      $.ajax({
        url: config.saveViewUrl,
        method: 'POST',
        dataType: 'json',
        data: {
          id_view: idView,
          name: $viewsModal.find('.wm-view-name').val(),
          is_default: $viewsModal.find('.wm-view-default').prop('checked') ? 1 : 0,
          replace_state: $viewsModal.find('.wm-view-replace').prop('checked') ? 1 : 0,
          state: JSON.stringify(collectViewState()),
          columns: getSelectedColumns()
        }
      }).done(function (response) {
        if (!response || !response.success) {
          $viewsModal.find('.wm-view-error').prop('hidden', false).text((response && response.error) || config.viewsError);
          return;
        }
        config.savedViews = response.views || [];
        renderSavedViews(response.id_view);
        $viewsModal.modal('hide');
      }).fail(function () {
        $viewsModal.find('.wm-view-error').prop('hidden', false).text(config.viewsError);
      }).always(function () {
        $save.prop('disabled', false);
      });
    });
    $viewsModal.on('click', '.wm-view-delete', function () {
      var idView = parseInt($viewsModal.data('view-id'), 10) || 0;
      if (!idView || !window.confirm(config.viewsDeleteConfirm)) {
        return;
      }
      $.ajax({ url: config.deleteViewUrl, method: 'POST', dataType: 'json', data: { id_view: idView } })
        .done(function (response) {
          if (!response || !response.success) {
            $viewsModal.find('.wm-view-error').prop('hidden', false).text((response && response.error) || config.viewsError);
            return;
          }
          config.savedViews = response.views || [];
          renderSavedViews(0);
          $viewsModal.modal('hide');
        }).fail(function () {
          $viewsModal.find('.wm-view-error').prop('hidden', false).text(config.viewsError);
        });
    });

    var defaultView = (config.savedViews || []).find(function (view) { return !!view.is_default; });
    var defaultAppliedKey = config.viewStorageKey ? config.viewStorageKey + '_default_applied' : '';
    var defaultAlreadyApplied = window.sessionStorage && defaultAppliedKey
      ? window.sessionStorage.getItem(defaultAppliedKey)
      : '1';
    if (!currentViewId && defaultView && defaultAlreadyApplied !== String(defaultView.id)) {
      applySavedView(defaultView);
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
