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
