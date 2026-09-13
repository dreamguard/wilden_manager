/**
 * Wilden Manager
 * Copyright 2026 Wilden Militaria S.L.
 */
(function ($) {
  'use strict';

  function updateSelectionCount() {
    var count = $('.wm-order-checkbox:checked').length;
    $('.wm-selected-count').each(function () {
      var current = $(this).text();
      $(this).text(current.replace(/^\d+/, count));
    });
  }

  $(document).on('change', '#wm-select-all', function () {
    $('.wm-order-checkbox').prop('checked', this.checked);
    updateSelectionCount();
  });

  $(document).on('change', '.wm-order-checkbox', function () {
    var all = $('.wm-order-checkbox').length;
    var checked = $('.wm-order-checkbox:checked').length;
    $('#wm-select-all').prop('checked', all > 0 && checked === all);
    updateSelectionCount();
  });

  $(document).on('click', '.wm-delete-view, .wm-confirm-action', function (event) {
    var message = $(this).data('confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  });

  $(document).on('click', '.wm-bulk-preview-button', function (event) {
    var $form = $(this).closest('form');
    var selected = $('.wm-order-checkbox:checked').length;
    var state = parseInt($form.find('select[name="bulk_state"]').val(), 10) || 0;
    var config = window.wildenManagerConfig || {};
    var max = parseInt(config.maxBulk, 10) || 100;

    if (!selected || !state || selected > max) {
      event.preventDefault();
      window.alert(!selected ? config.selectOrderText : (!state ? config.selectStateText : config.tooManyText));
    }
  });

  $(document).on('click', '.wm-column-menu', function (event) {
    if (!$(event.target).closest('.wm-apply-columns').length) {
      event.stopPropagation();
    }
  });

  $(document).on('click', '.wm-quick-view', function () {
    var idOrder = parseInt($(this).data('order-id'), 10);
    var $modal = $('#wm-quick-view-modal');
    var $body = $modal.find('.modal-body');
    var config = window.wildenManagerConfig || {};

    $body.html('<div class="wm-loading"><i class="icon-refresh icon-spin"></i> ' + (config.loadingText || 'Loading…') + '</div>');
    $modal.modal('show');

    $.ajax({
      url: config.ajaxUrl,
      method: 'GET',
      dataType: 'json',
      data: { action: 'quickView', id_order: idOrder }
    }).done(function (response) {
      if (response && response.success) {
        $body.html(response.html);
      } else {
        $body.html('<div class="alert alert-danger">' + ((response && response.error) || config.errorText) + '</div>');
      }
    }).fail(function () {
      $body.html('<div class="alert alert-danger">' + (config.errorText || 'The order could not be loaded.') + '</div>');
    });
  });

  $(updateSelectionCount);
})(window.jQuery);
