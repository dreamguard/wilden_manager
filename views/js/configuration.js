/**
 * Wilden Manager configuration and diagnostics.
 */
(function ($) {
  'use strict';

  $(function () {
    var config = window.wildenManagerConfiguration;
    var $dashboard = $('#wm-integrity-dashboard');
    if (!config || !$dashboard.length) {
      return;
    }
    if ($dashboard.data('wm-integrity-initialized')) {
      return;
    }
    $dashboard.data('wm-integrity-initialized', true);

    function activateTab(name) {
      var $target = $('[data-wm-panel="' + name + '"]');
      if (!$target.length) { return; }
      $('.wm-tab').removeClass('is-active').attr('aria-selected', 'false');
      $('.wm-tab[data-wm-tab="' + name + '"]').addClass('is-active').attr('aria-selected', 'true');
      $('.wm-tab-panel').prop('hidden', true);
      $target.prop('hidden', false);
      window.location.hash = 'wm-' + name;
    }

    $('.wm-tabs').on('click', '.wm-tab', function () { activateTab($(this).data('wm-tab')); });
    var requestedTab = String(window.location.hash || '').replace('#wm-', '');
    if (requestedTab && $('[data-wm-panel="' + requestedTab + '"]').length) {
      activateTab(requestedTab);
    }

    function reviewStatusLabel(cfg, status) {
      if (status === 'reviewed') { return cfg.reviewed; }
      if (status === 'justified') { return cfg.justified; }
      if (status === 'confirmed') { return cfg.confirmed; }
      return cfg.pendingReview;
    }

    function renderReviewCell($cell, issue, cfg, includeRepair) {
      $cell.empty();
      if (includeRepair && issue.repair_classification) {
        var classificationLabel = issue.repair_classification === 'safe' ? cfg.safeRepair :
          (issue.repair_classification === 'review' ? cfg.needsReview : cfg.doNotRepair);
        var classificationClass = issue.repair_classification === 'safe' ? 'badge-success' :
          (issue.repair_classification === 'review' ? 'badge-warning' : 'badge-secondary');
        $('<span class="badge wm-repair-classification"></span>')
          .addClass(classificationClass).text(classificationLabel).appendTo($cell);
      }
      var status = issue.review_status || '';
      var badgeClass = status === 'confirmed' ? 'badge-danger' :
        (status === 'justified' ? 'badge-success' : (status === 'reviewed' ? 'badge-info' : 'badge-secondary'));
      $('<span class="badge wm-review-badge"></span>').addClass(badgeClass)
        .text(reviewStatusLabel(cfg, status)).appendTo($cell);
      var $details = $('<details class="wm-review-editor"><summary></summary><div class="wm-review-fields"></div></details>').appendTo($cell);
      $details.find('summary').text(cfg.review);
      var $fields = $details.find('.wm-review-fields');
      var $select = $('<select class="form-control form-control-sm wm-review-status"></select>').appendTo($fields);
      $('<option></option>').val('reviewed').text(cfg.reviewed).appendTo($select);
      $('<option></option>').val('justified').text(cfg.justified).appendTo($select);
      $('<option></option>').val('confirmed').text(cfg.confirmed).appendTo($select);
      $select.val(status || 'reviewed');
      $('<textarea class="form-control form-control-sm wm-review-note" rows="3"></textarea>')
        .attr('placeholder', cfg.reviewNote).val(issue.review_note || '').appendTo($fields);
      $('<button type="button" class="btn btn-sm btn-primary wm-review-save"></button>')
        .text(cfg.saveReview).appendTo($fields);
      if (issue.review_employee || issue.review_date) {
        $('<small class="wm-review-meta"></small>')
          .text([issue.review_employee || '', issue.review_date || ''].filter(Boolean).join(' · '))
          .appendTo($cell);
      }
      if (includeRepair && issue.repair_eligible) {
        $('<button type="button" class="btn btn-sm btn-outline-danger wm-stock-repair"></button>')
          .prop('disabled', status !== 'confirmed')
          .text(cfg.repair).appendTo($cell);
      }
    }

    function bindReviewActions($container, cfg, scope, reload, setPanelMessage) {
      $container.on('click', '.wm-review-save', function () {
        var $row = $(this).closest('tr');
        var issue = $row.data('wm-issue');
        var status = $row.find('.wm-review-status').val();
        var note = $.trim($row.find('.wm-review-note').val() || '');
        if ((status === 'justified' || status === 'confirmed') && !note) {
          setPanelMessage('warning', cfg.noteRequired);
          return;
        }
        var $button = $(this).prop('disabled', true);
        $.ajax({
          url: cfg.reviewUrl,
          method: 'POST',
          dataType: 'json',
          data: {
            scope: scope, issue_key: issue.review_key, issue_type: issue.issue_type,
            status: status, note: note, snapshot_hash: issue.snapshot_hash, id_shop: issue.id_shop
          }
        }).done(function (response) {
          if (!response || !response.success) {
            setPanelMessage('danger', (response && response.error) || cfg.error);
            return;
          }
          setPanelMessage('success', cfg.reviewSaved);
          reload();
        }).fail(function (xhr) {
          setPanelMessage('danger', (xhr.responseJSON && xhr.responseJSON.error) || cfg.error);
        }).always(function () { $button.prop('disabled', false); });
      });
    }

    var $issue = $dashboard.find('.wm-integrity-issue');
    var $severity = $dashboard.find('.wm-integrity-severity');
    var activeRequest = null;
    var requestSequence = 0;
    $issue.find('option').first().text(config.allIssues);
    Object.keys(config.issueTypes || {}).forEach(function (id) {
      $('<option></option>').val(id).text(config.issueTypes[id]).appendTo($issue);
    });
    $severity.find('option[value=""]').text(config.allSeverities);
    $severity.find('option[value="high"]').text(config.high);
    $severity.find('option[value="medium"]').text(config.medium);
    $severity.find('option[value="info"]').text(config.info);
    $severity.val('high');
    $dashboard.find('.wm-integrity-previous').text(config.previous);
    $dashboard.find('.wm-integrity-next').text(config.next);

    function setMessage(type, message) {
      $dashboard.find('.wm-integrity-message')
        .removeClass('alert-info alert-success alert-danger alert-warning')
        .addClass('alert-' + type)
        .prop('hidden', !message)
        .text(message || '');
    }

    function renderReport(report) {
      var summary = report.summary || {};
      $dashboard.find('.wm-integrity-high').text(config.high + ': ' + (summary.high || 0));
      $dashboard.find('.wm-integrity-medium').text(config.medium + ': ' + (summary.medium || 0));
      $dashboard.find('.wm-integrity-info').text(config.info + ': ' + (summary.info || 0));

      var issues = Array.isArray(report.issues) ? report.issues : [];
      var $empty = $dashboard.find('.wm-integrity-empty');
      var $tableWrap = $dashboard.find('.wm-integrity-table-wrap');
      var $tbody = $dashboard.find('.wm-integrity-report-table tbody').empty();
      if (!issues.length) {
        $tableWrap.prop('hidden', true);
        $empty.text(config.noIssues).prop('hidden', false);
      } else {
        issues.forEach(function (issue) {
          var $row = $('<tr><td><span class="badge"></span></td><td></td><td><a target="_blank" rel="noopener"></a></td><td></td><td></td><td></td><td></td></tr>');
          var severityLabel = issue.severity === 'high' ? config.high : (issue.severity === 'medium' ? config.medium : config.info);
          var badgeClass = issue.severity === 'high' ? 'badge-danger' : (issue.severity === 'medium' ? 'badge-warning' : 'badge-info');
          $row.find('.badge').addClass(badgeClass).text(severityLabel);
          $row.children().eq(1).text(issue.label || issue.issue_type);
          $row.children().eq(2).find('a').attr('href', issue.order_url).text('#' + parseInt(issue.id_order, 10));
          $row.children().eq(3).text(issue.reference || '');
          $row.children().eq(4).text(issue.order_date || '');
          $row.children().eq(5).text(issue.detail || '');
          $row.data('wm-issue', issue);
          renderReviewCell($row.children().eq(6), issue, config, false);
          $tbody.append($row);
        });
        $empty.prop('hidden', true);
        $tableWrap.prop('hidden', false);
      }

      var page = parseInt(report.page, 10) || 1;
      var pages = parseInt(report.pages, 10) || 1;
      var $pagination = $dashboard.find('.wm-integrity-pagination').prop('hidden', pages <= 1);
      $pagination.find('span').text(config.page + ' ' + page + ' ' + config.of + ' ' + pages);
      $pagination.find('.wm-integrity-previous').prop('disabled', page <= 1);
      $pagination.find('.wm-integrity-next').prop('disabled', page >= pages);
      $dashboard.data('page', page).data('pages', pages);
    }

    function loadReport(page) {
      page = Math.max(1, parseInt(page, 10) || 1);
      requestSequence += 1;
      var currentSequence = requestSequence;
      if (activeRequest && activeRequest.readyState !== 4) {
        activeRequest.abort();
      }
      $dashboard.find('.wm-integrity-refresh').prop('disabled', true);
      setMessage('info', config.loading);
      activeRequest = $.ajax({
        url: config.scanUrl,
        method: 'POST',
        dataType: 'json',
        data: { issue_type: $issue.val(), severity: $severity.val(), page: page, limit: 50 }
      }).done(function (response) {
        if (currentSequence !== requestSequence) {
          return;
        }
        if (!response || !response.success || !response.report) {
          setMessage('danger', (response && response.error) || config.error);
          return;
        }
        setMessage('info', '');
        renderReport(response.report);
      }).fail(function (xhr, textStatus) {
        if (textStatus === 'abort' || currentSequence !== requestSequence) {
          return;
        }
        var response = xhr.responseJSON || {};
        setMessage('danger', response.error || config.error);
      }).always(function () {
        if (currentSequence === requestSequence) {
          $dashboard.find('.wm-integrity-refresh').prop('disabled', false);
          activeRequest = null;
        }
      });
    }

    $dashboard.on('click', '.wm-integrity-refresh', function () { loadReport(1); });
    $dashboard.on('change', '.wm-integrity-issue', function () {
      var expectedSeverity = (config.issueSeverities || {})[$issue.val()];
      if (expectedSeverity) {
        $severity.val(expectedSeverity);
      }
      loadReport(1);
    });
    $dashboard.on('change', '.wm-integrity-severity', function () { loadReport(1); });
    $dashboard.on('click', '.wm-integrity-previous', function () { loadReport(($dashboard.data('page') || 1) - 1); });
    $dashboard.on('click', '.wm-integrity-next', function () { loadReport(($dashboard.data('page') || 1) + 1); });
    $dashboard.on('click', '.wm-integrity-export', function () {
      var $form = $('<form method="post" hidden></form>').attr('action', config.exportUrl).appendTo('body');
      $('<input type="hidden" name="issue_type">').val($issue.val()).appendTo($form);
      $('<input type="hidden" name="severity">').val($severity.val()).appendTo($form);
      $form.trigger('submit');
      window.setTimeout(function () { $form.remove(); }, 1000);
    });
    bindReviewActions($dashboard, config, 'order_integrity', function () {
      loadReport($dashboard.data('page') || 1);
    }, setMessage);

    function initStockIntegrity() {
      var stockConfig = config.stock;
      var $stock = $('#wm-stock-integrity-dashboard');
      if (!stockConfig || !$stock.length || $stock.data('wm-stock-initialized')) {
        return;
      }
      $stock.data('wm-stock-initialized', true);

      var $stockIssue = $stock.find('.wm-stock-issue');
      var $stockSeverity = $stock.find('.wm-stock-severity');
      var $stockKind = $stock.find('.wm-stock-kind');
      var activeStockRequest = null;
      var stockSequence = 0;

      $stockIssue.find('option').first().text(stockConfig.allIssues);
      Object.keys(stockConfig.issueTypes || {}).forEach(function (id) {
        $('<option></option>').val(id).text(stockConfig.issueTypes[id]).appendTo($stockIssue);
      });
      $stockSeverity.find('option[value=""]').text(stockConfig.allSeverities);
      $stockSeverity.find('option[value="high"]').text(stockConfig.high);
      $stockSeverity.find('option[value="medium"]').text(stockConfig.medium);
      $stockSeverity.find('option[value="info"]').text(stockConfig.info);
      $stockKind.find('option[value=""]').text(stockConfig.allKinds);
      $stockKind.find('option[value="standard"]').text(stockConfig.standard);
      $stockKind.find('option[value="pack"]').text(stockConfig.pack);
      $stockKind.find('option[value="custom"]').text(stockConfig.custom);
      $stockKind.find('option[value="order"]').text(stockConfig.orderLevel);
      $stockSeverity.val('high');
      $stock.find('.wm-stock-previous').text(stockConfig.previous);
      $stock.find('.wm-stock-next').text(stockConfig.next);

      function stockKindLabel(kind) {
        if (kind === 'pack') { return stockConfig.pack; }
        if (kind === 'custom') { return stockConfig.custom; }
        if (kind === 'order') { return stockConfig.orderLevel; }
        return stockConfig.standard;
      }

      function setStockMessage(type, message) {
        $stock.find('.wm-stock-message')
          .removeClass('alert-info alert-success alert-danger alert-warning')
          .addClass('alert-' + type)
          .prop('hidden', !message)
          .text(message || '');
      }

      function renderStockReport(report) {
        var summary = report.summary || {};
        $stock.find('.wm-stock-high').text(stockConfig.high + ': ' + (summary.high || 0));
        $stock.find('.wm-stock-medium').text(stockConfig.medium + ': ' + (summary.medium || 0));
        $stock.find('.wm-stock-info').text(stockConfig.info + ': ' + (summary.info || 0));

        var issues = Array.isArray(report.issues) ? report.issues : [];
        var $tbody = $stock.find('.wm-stock-table tbody').empty();
        var $empty = $stock.find('.wm-stock-empty');
        var $table = $stock.find('.wm-stock-table-wrap');
        if (!issues.length) {
          $table.prop('hidden', true);
          $empty.text(stockConfig.noIssues).prop('hidden', false);
        } else {
          issues.forEach(function (issue) {
            var $row = $('<tr><td><span class="badge"></span></td><td></td><td><span class="badge badge-secondary"></span></td><td></td><td><div></div><small></small></td><td></td><td></td><td></td></tr>');
            var severityLabel = issue.severity === 'high' ? stockConfig.high : (issue.severity === 'medium' ? stockConfig.medium : stockConfig.info);
            var badgeClass = issue.severity === 'high' ? 'badge-danger' : (issue.severity === 'medium' ? 'badge-warning' : 'badge-info');
            $row.children().eq(0).find('.badge').addClass(badgeClass).text(severityLabel);
            $row.children().eq(1).text(issue.label || issue.issue_type || '');
            $row.children().eq(2).find('.badge').text(stockKindLabel(issue.product_kind));
            if (issue.id_order && issue.order_url) {
              $('<a target="_blank" rel="noopener"></a>')
                .attr('href', issue.order_url)
                .text('#' + parseInt(issue.id_order, 10))
                .appendTo($row.children().eq(3));
              if (issue.reference) {
                $('<small></small>').text(issue.reference).appendTo($row.children().eq(3));
              }
            } else {
              $row.children().eq(3).text('—');
            }
            if (issue.id_product && issue.product_url) {
              $('<a target="_blank" rel="noopener"></a>')
                .attr('href', issue.product_url)
                .text(issue.product_name || ('#' + parseInt(issue.id_product, 10)))
                .appendTo($row.children().eq(4).find('div'));
              $row.children().eq(4).find('small').text(
                '#' + parseInt(issue.id_product, 10) +
                (parseInt(issue.id_product_attribute, 10) ? ' / attr #' + parseInt(issue.id_product_attribute, 10) : '')
              );
            } else {
              $row.children().eq(4).find('div').text(issue.product_name || '—');
            }
            $row.children().eq(5).text(issue.order_date || '');
            $row.children().eq(6).text(issue.detail || '');
            $row.data('wm-issue', issue);
            renderReviewCell($row.children().eq(7), issue, stockConfig, true);
            $tbody.append($row);
          });
          $empty.prop('hidden', true);
          $table.prop('hidden', false);
        }

        var page = parseInt(report.page, 10) || 1;
        var pages = parseInt(report.pages, 10) || 1;
        var $pagination = $stock.find('.wm-stock-pagination').prop('hidden', pages <= 1);
        $pagination.find('span').text(stockConfig.page + ' ' + page + ' ' + stockConfig.of + ' ' + pages);
        $pagination.find('.wm-stock-previous').prop('disabled', page <= 1);
        $pagination.find('.wm-stock-next').prop('disabled', page >= pages);
        $stock.data('page', page).data('pages', pages);
      }

      function loadStockReport(page) {
        page = Math.max(1, parseInt(page, 10) || 1);
        stockSequence += 1;
        var currentSequence = stockSequence;
        if (activeStockRequest && activeStockRequest.readyState !== 4) {
          activeStockRequest.abort();
        }
        $stock.find('.wm-stock-refresh').prop('disabled', true);
        setStockMessage('info', stockConfig.loading);
        activeStockRequest = $.ajax({
          url: stockConfig.scanUrl,
          method: 'POST',
          dataType: 'json',
          data: {
            issue_type: $stockIssue.val(),
            severity: $stockSeverity.val(),
            product_kind: $stockKind.val(),
            page: page,
            limit: 50
          }
        }).done(function (response) {
          if (currentSequence !== stockSequence) { return; }
          if (!response || !response.success || !response.report) {
            setStockMessage('danger', (response && response.error) || stockConfig.error);
            return;
          }
          setStockMessage('info', '');
          renderStockReport(response.report);
        }).fail(function (xhr, textStatus) {
          if (textStatus === 'abort' || currentSequence !== stockSequence) { return; }
          var response = xhr.responseJSON || {};
          setStockMessage('danger', response.error || stockConfig.error);
        }).always(function () {
          if (currentSequence === stockSequence) {
            $stock.find('.wm-stock-refresh').prop('disabled', false);
            activeStockRequest = null;
          }
        });
      }

      $stock.on('click', '.wm-stock-refresh', function () { loadStockReport(1); });
      $stock.on('change', '.wm-stock-issue', function () {
        var expectedSeverity = (stockConfig.issueSeverities || {})[$stockIssue.val()];
        if (expectedSeverity) { $stockSeverity.val(expectedSeverity); }
        loadStockReport(1);
      });
      $stock.on('change', '.wm-stock-severity, .wm-stock-kind', function () { loadStockReport(1); });
      $stock.on('click', '.wm-stock-previous', function () { loadStockReport(($stock.data('page') || 1) - 1); });
      $stock.on('click', '.wm-stock-next', function () { loadStockReport(($stock.data('page') || 1) + 1); });
      $stock.on('click', '.wm-stock-export', function () {
        var $form = $('<form method="post" hidden></form>').attr('action', stockConfig.exportUrl).appendTo('body');
        $('<input type="hidden" name="issue_type">').val($stockIssue.val()).appendTo($form);
        $('<input type="hidden" name="severity">').val($stockSeverity.val()).appendTo($form);
        $('<input type="hidden" name="product_kind">').val($stockKind.val()).appendTo($form);
        $form.trigger('submit');
        window.setTimeout(function () { $form.remove(); }, 1000);
      });
      bindReviewActions($stock, stockConfig, 'stock_integrity', function () {
        loadStockReport($stock.data('page') || 1);
      }, setStockMessage);
      $stock.on('click', '.wm-stock-repair', function () {
        var $row = $(this).closest('tr');
        var issue = $row.data('wm-issue');
        if (!window.confirm(stockConfig.repairConfirm)) { return; }
        var $button = $(this).prop('disabled', true);
        $.ajax({
          url: stockConfig.repairUrl,
          method: 'POST',
          dataType: 'json',
          data: { issue_key: issue.review_key, snapshot_hash: issue.snapshot_hash }
        }).done(function (response) {
          if (!response || !response.success) {
            setStockMessage('danger', (response && response.error) || stockConfig.error);
            return;
          }
          setStockMessage('success', stockConfig.repairDone);
          loadStockReport($stock.data('page') || 1);
        }).fail(function (xhr) {
          setStockMessage('danger', (xhr.responseJSON && xhr.responseJSON.error) || stockConfig.error);
        }).always(function () { $button.prop('disabled', false); });
      });

      loadStockReport(1);
    }

    function initAudit() {
      var auditConfig = config.audit;
      var $audit = $('#wm-audit-dashboard');
      if (!auditConfig || !$audit.length || $audit.data('wm-audit-initialized')) {
        return;
      }
      $audit.data('wm-audit-initialized', true);

      var $action = $audit.find('.wm-audit-action');
      var $employee = $audit.find('.wm-audit-employee');
      var activeAuditRequest = null;
      var auditSequence = 0;
      $action.find('option').first().text(auditConfig.allActions);
      $employee.find('option').first().text(auditConfig.allEmployees);
      (auditConfig.actions || []).forEach(function (item) {
        $('<option></option>').val(item.id).text(item.label).appendTo($action);
      });
      (auditConfig.employees || []).forEach(function (item) {
        $('<option></option>').val(item.id).text(item.label).appendTo($employee);
      });
      $audit.find('.wm-audit-previous').text(auditConfig.previous);
      $audit.find('.wm-audit-next').text(auditConfig.next);

      function setAuditMessage(type, message) {
        $audit.find('.wm-audit-message')
          .removeClass('alert-info alert-success alert-danger alert-warning')
          .addClass('alert-' + type)
          .prop('hidden', !message)
          .text(message || '');
      }

      function renderAudit(report) {
        var rows = Array.isArray(report.rows) ? report.rows : [];
        var $tbody = $audit.find('.wm-audit-table tbody').empty();
        var $empty = $audit.find('.wm-audit-empty');
        var $table = $audit.find('.wm-audit-table-wrap');
        if (!rows.length) {
          $table.prop('hidden', true);
          $empty.text(auditConfig.empty).prop('hidden', false);
        } else {
          rows.forEach(function (row) {
            var $tr = $('<tr><td></td><td></td><td><div></div><code></code></td><td></td><td></td><td><details class="wm-audit-details"><summary></summary><pre></pre></details></td></tr>');
            $tr.children().eq(0).text(row.date_add || '');
            $tr.children().eq(1).text(row.employee_name || auditConfig.system);
            $tr.children().eq(2).find('div').text(row.action_label || row.action || '');
            $tr.children().eq(2).find('code').text(row.action || '');
            if (row.id_order && row.order_url) {
              $('<a target="_blank" rel="noopener"></a>')
                .attr('href', row.order_url)
                .text('#' + parseInt(row.id_order, 10))
                .appendTo($tr.children().eq(3));
            } else {
              $tr.children().eq(3).text('—');
            }
            $tr.children().eq(4).text(row.shop_name || '—');
            $tr.children().eq(5).find('summary').text(auditConfig.details);
            $tr.children().eq(5).find('pre').text(JSON.stringify(row.details || {}, null, 2));
            $tbody.append($tr);
          });
          $empty.prop('hidden', true);
          $table.prop('hidden', false);
        }

        var page = parseInt(report.page, 10) || 1;
        var pages = parseInt(report.pages, 10) || 1;
        var $pagination = $audit.find('.wm-audit-pagination').prop('hidden', pages <= 1);
        $pagination.find('span').text(auditConfig.page + ' ' + page + ' ' + auditConfig.of + ' ' + pages);
        $pagination.find('.wm-audit-previous').prop('disabled', page <= 1);
        $pagination.find('.wm-audit-next').prop('disabled', page >= pages);
        $audit.data('page', page).data('pages', pages);
      }

      function loadAudit(page) {
        page = Math.max(1, parseInt(page, 10) || 1);
        auditSequence += 1;
        var currentSequence = auditSequence;
        if (activeAuditRequest && activeAuditRequest.readyState !== 4) {
          activeAuditRequest.abort();
        }
        $audit.find('.wm-audit-apply, .wm-audit-reset').prop('disabled', true);
        setAuditMessage('info', auditConfig.loading);
        activeAuditRequest = $.ajax({
          url: auditConfig.url,
          method: 'GET',
          dataType: 'json',
          data: {
            audit_action: $action.val(),
            audit_employee: $employee.val(),
            audit_order: $audit.find('.wm-audit-order').val(),
            audit_date_from: $audit.find('.wm-audit-date-from').val(),
            audit_date_to: $audit.find('.wm-audit-date-to').val(),
            page: page,
            limit: 50
          }
        }).done(function (response) {
          if (currentSequence !== auditSequence) {
            return;
          }
          if (!response || !response.success || !response.report) {
            setAuditMessage('danger', (response && response.error) || auditConfig.error);
            return;
          }
          setAuditMessage('info', '');
          renderAudit(response.report);
        }).fail(function (xhr, textStatus) {
          if (textStatus === 'abort' || currentSequence !== auditSequence) {
            return;
          }
          var response = xhr.responseJSON || {};
          setAuditMessage('danger', response.error || auditConfig.error);
        }).always(function () {
          if (currentSequence === auditSequence) {
            $audit.find('.wm-audit-apply, .wm-audit-reset').prop('disabled', false);
            activeAuditRequest = null;
          }
        });
      }

      $audit.on('click', '.wm-audit-apply', function () { loadAudit(1); });
      $audit.on('click', '.wm-audit-reset', function () {
        $action.val('');
        $employee.val('');
        $audit.find('.wm-audit-order, .wm-audit-date-from, .wm-audit-date-to').val('');
        loadAudit(1);
      });
      $audit.on('click', '.wm-audit-previous', function () { loadAudit(($audit.data('page') || 1) - 1); });
      $audit.on('click', '.wm-audit-next', function () { loadAudit(($audit.data('page') || 1) + 1); });
      $audit.on('keydown', 'input', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          loadAudit(1);
        }
      });

      loadAudit(1);
    }

    loadReport(1);
    initStockIntegrity();
    initAudit();
  });
})(window.jQuery);
