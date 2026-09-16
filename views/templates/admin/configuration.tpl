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
      {if $wm_is_super_admin}
      <section class="card wm-configuration-card" id="wm-profile-permissions">
        <div class="card-header">
          <h3><i class="material-icons">admin_panel_settings</i> {l s='Permissions by profile' mod='wilden_manager'}</h3>
        </div>
        <div class="card-body">
          <div class="alert alert-info">
            {l s='Permissions are enforced both in the interface and on the server. SuperAdmin always retains full access.' mod='wilden_manager'}
          </div>
          <form method="post" action="{$wm_permissions_action|escape:'htmlall':'UTF-8'}">
            <div class="wm-permissions-table-wrap">
              <table class="table wm-permissions-table">
                <thead>
                  <tr>
                    <th>{l s='Profile' mod='wilden_manager'}</th>
                    <th>{l s='CSV/XLSX exports' mod='wilden_manager'}</th>
                    <th>{l s='Diagnostics and audit' mod='wilden_manager'}</th>
                  </tr>
                </thead>
                <tbody>
                  {foreach from=$wm_profile_permissions item=profile}
                  <tr>
                    <td>
                      {$profile.name|escape:'htmlall':'UTF-8'}
                      {if $profile.is_super_admin}<span class="badge badge-primary">SuperAdmin</span>{/if}
                    </td>
                    <td><input type="checkbox" name="wm_export_profiles[]" value="{$profile.id_profile|intval}"{if $profile.can_export} checked{/if}{if $profile.is_super_admin} disabled{/if}></td>
                    <td><input type="checkbox" name="wm_diagnostic_profiles[]" value="{$profile.id_profile|intval}"{if $profile.can_view_diagnostics} checked{/if}{if $profile.is_super_admin} disabled{/if}></td>
                  </tr>
                  {/foreach}
                </tbody>
              </table>
            </div>
            <button type="submit" name="submitWmProfilePermissions" value="1" class="btn btn-primary">
              <i class="material-icons">save</i> {l s='Save permissions' mod='wilden_manager'}
            </button>
          </form>
        </div>
      </section>
      {/if}

      {if $wm_is_super_admin}
      <section class="card wm-configuration-card" id="wm-saved-views-admin">
        <div class="card-header">
          <h3><i class="material-icons">bookmarks</i> {l s='Saved order views' mod='wilden_manager'}</h3>
        </div>
        <div class="card-body">
          <div class="alert alert-info">
            {l s='Employees create and edit their own views from the native Orders list. SuperAdmin can review and remove obsolete views here.' mod='wilden_manager'}
          </div>
          {if $wm_saved_views_admin|count}
          <div class="wm-permissions-table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>{l s='View' mod='wilden_manager'}</th>
                  <th>{l s='Employee' mod='wilden_manager'}</th>
                  <th>{l s='Store' mod='wilden_manager'}</th>
                  <th>{l s='Default' mod='wilden_manager'}</th>
                  <th>{l s='Updated' mod='wilden_manager'}</th>
                  <th>{l s='Actions' mod='wilden_manager'}</th>
                </tr>
              </thead>
              <tbody>
                {foreach from=$wm_saved_views_admin item=view}
                <tr>
                  <td>{$view.name|escape:'htmlall':'UTF-8'}</td>
                  <td>{$view.employee_name|escape:'htmlall':'UTF-8'}</td>
                  <td>{$view.shop_name|escape:'htmlall':'UTF-8'}</td>
                  <td>{if $view.is_default}{l s='Yes' mod='wilden_manager'}{else}{l s='No' mod='wilden_manager'}{/if}</td>
                  <td>{$view.date_upd|escape:'htmlall':'UTF-8'}</td>
                  <td>
                    <form method="post" action="{$wm_permissions_action|escape:'htmlall':'UTF-8'}" onsubmit="return confirm('{l s='Delete this saved view?' mod='wilden_manager' js=1}');">
                      <input type="hidden" name="id_view" value="{$view.id_wilden_manager_saved_view|intval}">
                      <button type="submit" name="submitWmDeleteSavedView" value="1" class="btn btn-sm btn-outline-danger">
                        <i class="material-icons">delete</i> {l s='Delete' mod='wilden_manager'}
                      </button>
                    </form>
                  </td>
                </tr>
                {/foreach}
              </tbody>
            </table>
          </div>
          {else}
          <p class="text-muted">{l s='No native order views have been saved yet.' mod='wilden_manager'}</p>
          {/if}
        </div>
      </section>
      {/if}

      {if $wm_can_view_diagnostics}
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

      <section class="card wm-configuration-card" id="wm-stock-integrity-dashboard">
        <div class="card-header">
          <h3><i class="material-icons">inventory_2</i> {l s='Stock, cancellation and refund diagnostics' mod='wilden_manager'}</h3>
        </div>
        <div class="card-body">
          <div class="alert alert-info">
            {l s='This analysis is read-only. High severity is reserved for impossible quantities. Informational rows identify cases that need human interpretation and are not proof of an incorrect stock change.' mod='wilden_manager'}
          </div>

          <div class="wm-stock-summary">
            <span class="badge badge-danger wm-stock-high"></span>
            <span class="badge badge-warning wm-stock-medium"></span>
            <span class="badge badge-info wm-stock-info"></span>
          </div>

          <div class="wm-stock-filters">
            <div class="form-group">
              <label for="wm-stock-issue">{l s='Issue type' mod='wilden_manager'}</label>
              <select id="wm-stock-issue" class="form-control wm-stock-issue"><option value=""></option></select>
            </div>
            <div class="form-group">
              <label for="wm-stock-severity">{l s='Severity' mod='wilden_manager'}</label>
              <select id="wm-stock-severity" class="form-control wm-stock-severity">
                <option value=""></option>
                <option value="high"></option>
                <option value="medium"></option>
                <option value="info"></option>
              </select>
            </div>
            <div class="form-group">
              <label for="wm-stock-kind">{l s='Product type' mod='wilden_manager'}</label>
              <select id="wm-stock-kind" class="form-control wm-stock-kind">
                <option value=""></option>
                <option value="standard"></option>
                <option value="pack"></option>
                <option value="custom"></option>
                <option value="order"></option>
              </select>
            </div>
            <div class="form-group wm-stock-filter-actions">
              <button type="button" class="btn btn-outline-primary wm-stock-refresh">
                <i class="material-icons">refresh</i> {l s='Refresh analysis' mod='wilden_manager'}
              </button>
            </div>
          </div>

          <div class="alert wm-stock-message" hidden></div>
          <div class="alert alert-success wm-stock-empty" hidden></div>
          <div class="wm-stock-table-wrap" hidden>
            <table class="wm-stock-table">
              <thead>
                <tr>
                  <th>{l s='Severity' mod='wilden_manager'}</th>
                  <th>{l s='Issue' mod='wilden_manager'}</th>
                  <th>{l s='Type' mod='wilden_manager'}</th>
                  <th>{l s='Order' mod='wilden_manager'}</th>
                  <th>{l s='Product' mod='wilden_manager'}</th>
                  <th>{l s='Date' mod='wilden_manager'}</th>
                  <th>{l s='Detail' mod='wilden_manager'}</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
          <div class="wm-stock-pagination" hidden>
            <button type="button" class="btn btn-sm btn-outline-secondary wm-stock-previous"></button>
            <span></span>
            <button type="button" class="btn btn-sm btn-outline-secondary wm-stock-next"></button>
          </div>
          <div class="wm-stock-footer">
            <button type="button" class="btn btn-primary wm-stock-export">
              <i class="material-icons">download</i> {l s='Export filtered CSV' mod='wilden_manager'}
            </button>
          </div>
        </div>
      </section>

      <section class="card wm-configuration-card" id="wm-audit-dashboard">
        <div class="card-header">
          <h3><i class="material-icons">history</i> {l s='Audit history' mod='wilden_manager'}</h3>
        </div>
        <div class="card-body">
          <div class="alert alert-info">
            {l s='This append-only history records actions performed through Wilden Manager. Viewing it never modifies shop data.' mod='wilden_manager'}
          </div>

          <div class="wm-audit-filters">
            <div class="form-group">
              <label for="wm-audit-action">{l s='Action' mod='wilden_manager'}</label>
              <select id="wm-audit-action" class="form-control wm-audit-action"><option value=""></option></select>
            </div>
            <div class="form-group">
              <label for="wm-audit-employee">{l s='Employee' mod='wilden_manager'}</label>
              <select id="wm-audit-employee" class="form-control wm-audit-employee"><option value=""></option></select>
            </div>
            <div class="form-group">
              <label for="wm-audit-order">{l s='Order ID' mod='wilden_manager'}</label>
              <input id="wm-audit-order" class="form-control wm-audit-order" type="number" min="1" step="1">
            </div>
            <div class="form-group">
              <label for="wm-audit-date-from">{l s='From' mod='wilden_manager'}</label>
              <input id="wm-audit-date-from" class="form-control wm-audit-date-from" type="date">
            </div>
            <div class="form-group">
              <label for="wm-audit-date-to">{l s='To' mod='wilden_manager'}</label>
              <input id="wm-audit-date-to" class="form-control wm-audit-date-to" type="date">
            </div>
            <div class="form-group wm-audit-filter-actions">
              <button type="button" class="btn btn-outline-primary wm-audit-apply">
                <i class="material-icons">filter_alt</i> {l s='Apply filters' mod='wilden_manager'}
              </button>
              <button type="button" class="btn btn-outline-secondary wm-audit-reset">
                {l s='Clear' mod='wilden_manager'}
              </button>
            </div>
          </div>

          <div class="alert wm-audit-message" hidden></div>
          <div class="alert alert-secondary wm-audit-empty" hidden></div>
          <div class="wm-audit-table-wrap" hidden>
            <table class="wm-audit-table">
              <thead>
                <tr>
                  <th>{l s='Date' mod='wilden_manager'}</th>
                  <th>{l s='Employee' mod='wilden_manager'}</th>
                  <th>{l s='Action' mod='wilden_manager'}</th>
                  <th>{l s='Order' mod='wilden_manager'}</th>
                  <th>{l s='Store' mod='wilden_manager'}</th>
                  <th>{l s='Details' mod='wilden_manager'}</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
          <div class="wm-audit-pagination" hidden>
            <button type="button" class="btn btn-sm btn-outline-secondary wm-audit-previous"></button>
            <span></span>
            <button type="button" class="btn btn-sm btn-outline-secondary wm-audit-next"></button>
          </div>
        </div>
      </section>
      {else}
      <div class="alert alert-warning">
        {l s='Your employee profile does not have permission to access diagnostics or audit history.' mod='wilden_manager'}
      </div>
      {/if}
    </main>

    <aside class="wm-configuration-sidebar">
      <section class="card wm-configuration-card">
        <div class="card-header"><h3>{l s='Module areas' mod='wilden_manager'}</h3></div>
        <div class="card-body">
          <div class="wm-area {if $wm_can_view_diagnostics}wm-area-active{else}wm-area-future{/if}">
            <i class="material-icons">health_and_safety</i>
            <div><strong>{l s='Diagnostics' mod='wilden_manager'}</strong><span>{l s='Integrity checks and reports' mod='wilden_manager'}</span></div>
          </div>
          <div class="wm-area {if $wm_can_view_diagnostics}wm-area-active{else}wm-area-future{/if}">
            <i class="material-icons">history</i>
            <div><strong>{l s='Audit' mod='wilden_manager'}</strong><span>{l s='Module activity history' mod='wilden_manager'}</span></div>
          </div>
          <div class="wm-area {if $wm_can_view_diagnostics}wm-area-active{else}wm-area-future{/if}">
            <i class="material-icons">inventory_2</i>
            <div><strong>{l s='Stock diagnostics' mod='wilden_manager'}</strong><span>{l s='Cancellations, refunds and stock consistency' mod='wilden_manager'}</span></div>
          </div>
          <div class="wm-area {if $wm_is_super_admin}wm-area-active{else}wm-area-future{/if}">
            <i class="material-icons">admin_panel_settings</i>
            <div><strong>{l s='Permissions' mod='wilden_manager'}</strong><span>{l s='Access by employee profile' mod='wilden_manager'}</span></div>
          </div>
        </div>
      </section>

      <a class="btn btn-outline-secondary wm-orders-link" href="{$wm_orders_url|escape:'htmlall':'UTF-8'}">
        <i class="material-icons">list_alt</i> {l s='Open native Orders list' mod='wilden_manager'}
      </a>
    </aside>
  </div>
</div>
