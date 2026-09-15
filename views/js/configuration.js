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

    var $issue = $dashboard.find('.wm-integrity-issue');
    var $severity = $dashboard.find('.wm-integrity-severity');
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

      var $results = $dashboard.find('.wm-integrity-results').empty();
      if (!Array.isArray(report.issues) || !report.issues.length) {
        $('<div class="alert alert-success"></div>').text(config.noIssues).appendTo($results);
      } else {
        var $table = $('<div class="table-responsive"><table class="table table-striped"><thead><tr><th></th><th></th><th></th><th></th><th></th><th></th></tr></thead><tbody></tbody></table></div>');
        var headers = [config.severity, config.issue, config.order, config.reference, config.date, config.detail];
        $table.find('th').each(function (index) { $(this).text(headers[index]); });

        report.issues.forEach(function (issue) {
          var $row = $('<tr><td><span class="badge"></span></td><td></td><td><a target="_blank" rel="noopener"></a></td><td></td><td></td><td></td></tr>');
          var severityLabel = issue.severity === 'high' ? config.high : (issue.severity === 'medium' ? config.medium : config.info);
          var badgeClass = issue.severity === 'high' ? 'badge-danger' : (issue.severity === 'medium' ? 'badge-warning' : 'badge-info');
          $row.find('.badge').addClass(badgeClass).text(severityLabel);
          $row.children().eq(1).text(issue.label || issue.issue_type);
          $row.children().eq(2).find('a').attr('href', issue.order_url).text('#' + parseInt(issue.id_order, 10));
          $row.children().eq(3).text(issue.reference || '');
          $row.children().eq(4).text(issue.order_date || '');
          $row.children().eq(5).text(issue.detail || '');
          $table.find('tbody').append($row);
        });
        $results.append($table);
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
      $dashboard.find('.wm-integrity-refresh').prop('disabled', true);
      setMessage('info', config.loading);
      $.ajax({
        url: config.scanUrl,
        method: 'POST',
        dataType: 'json',
        data: { issue_type: $issue.val(), severity: $severity.val(), page: page, limit: 50 }
      }).done(function (response) {
        if (!response || !response.success || !response.report) {
          setMessage('danger', (response && response.error) || config.error);
          return;
        }
        setMessage('info', '');
        renderReport(response.report);
      }).fail(function (xhr) {
        var response = xhr.responseJSON || {};
        setMessage('danger', response.error || config.error);
      }).always(function () {
        $dashboard.find('.wm-integrity-refresh').prop('disabled', false);
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

    loadReport(1);
  });
})(window.jQuery);
