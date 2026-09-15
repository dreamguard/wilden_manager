{* Wilden Manager control centre. *}
<div id="wm-configuration" class="wm-configuration">
  <div class="wm-configuration-header">
    <div>
      <h2>{l s='Wilden Manager control centre' mod='wilden_manager'}</h2>
      <p>{l s='Diagnostics and future module settings are managed here, away from the daily Orders workspace.' mod='wilden_manager'}</p>
    </div>
    <span class="wm-version">v{$wm_module_version|escape:'htmlall':'UTF-8'}</span>
  </div>

  <div class="wm-configuration-layout">
    <main class="wm-configuration-main">
      <section class="card wm-configuration-card" id="wm-integrity-dashboard">
        <div class="card-header">
          <h3><i class="material-icons">fact_check</i> {l s='Order integrity diagnostics' mod='wilden_manager'}</h3>
        </div>
        <div class="card-body">
          <div class="alert alert-info">
            {l s='This analysis is read-only. It never repairs or modifies orders, customers, addresses, histories or documents.' mod='wilden_manager'}
          </div>

          <div class="wm-integrity-summary">
            <span class="badge badge-danger wm-integrity-high"></span>
            <span class="badge badge-warning wm-integrity-medium"></span>
            <span class="badge badge-info wm-integrity-info"></span>
          </div>

          <div class="form-row wm-integrity-filters">
            <div class="form-group col-lg-5">
              <label for="wm-integrity-issue">{l s='Issue type' mod='wilden_manager'}</label>
              <select id="wm-integrity-issue" class="form-control wm-integrity-issue"><option value=""></option></select>
            </div>
            <div class="form-group col-lg-4">
              <label for="wm-integrity-severity">{l s='Severity' mod='wilden_manager'}</label>
              <select id="wm-integrity-severity" class="form-control wm-integrity-severity">
                <option value=""></option>
                <option value="high"></option>
                <option value="medium"></option>
                <option value="info"></option>
              </select>
            </div>
            <div class="form-group col-lg-3 wm-integrity-filter-actions">
              <button type="button" class="btn btn-outline-primary wm-integrity-refresh">
                <i class="material-icons">refresh</i> {l s='Refresh analysis' mod='wilden_manager'}
              </button>
            </div>
          </div>

          <div class="alert wm-integrity-message" hidden></div>
          <div class="wm-integrity-results">
            <div class="alert alert-success wm-integrity-empty" hidden></div>
            <div class="wm-integrity-table-wrap" hidden>
              <table class="wm-integrity-report-table">
                <thead>
                  <tr>
                    <th>{l s='Severity' mod='wilden_manager'}</th>
                    <th>{l s='Issue' mod='wilden_manager'}</th>
                    <th>{l s='Order' mod='wilden_manager'}</th>
                    <th>{l s='Reference' mod='wilden_manager'}</th>
                    <th>{l s='Date' mod='wilden_manager'}</th>
                    <th>{l s='Detail' mod='wilden_manager'}</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
          <div class="wm-integrity-pagination" hidden>
            <button type="button" class="btn btn-sm btn-outline-secondary wm-integrity-previous"></button>
            <span></span>
            <button type="button" class="btn btn-sm btn-outline-secondary wm-integrity-next"></button>
          </div>

          <div class="wm-integrity-footer">
            <button type="button" class="btn btn-primary wm-integrity-export">
              <i class="material-icons">download</i> {l s='Export filtered CSV' mod='wilden_manager'}
            </button>
          </div>
        </div>
      </section>
    </main>

    <aside class="wm-configuration-sidebar">
      <section class="card wm-configuration-card">
        <div class="card-header"><h3>{l s='Module areas' mod='wilden_manager'}</h3></div>
        <div class="card-body">
          <div class="wm-area wm-area-active">
            <i class="material-icons">health_and_safety</i>
            <div><strong>{l s='Diagnostics' mod='wilden_manager'}</strong><span>{l s='Integrity checks and reports' mod='wilden_manager'}</span></div>
          </div>
          <div class="wm-area wm-area-future">
            <i class="material-icons">settings</i>
            <div><strong>{l s='Settings' mod='wilden_manager'}</strong><span>{l s='Future module options will be added here' mod='wilden_manager'}</span></div>
          </div>
        </div>
      </section>

      <a class="btn btn-outline-secondary wm-orders-link" href="{$wm_orders_url|escape:'htmlall':'UTF-8'}">
        <i class="material-icons">list_alt</i> {l s='Open native Orders list' mod='wilden_manager'}
      </a>
    </aside>
  </div>
</div>
