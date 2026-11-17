{* Wilden Manager control centre. *}
<div id="wm-configuration" class="wm-configuration">
  <div class="wm-configuration-header">
    <div>
      <h2>{l s='Wilden Manager control centre' d='Modules.Wildenmanager.Admin'}</h2>
      <p>{l s='Diagnostics and future module settings are managed here, away from the daily Orders workspace.' d='Modules.Wildenmanager.Admin'}</p>
    </div>
    <span class="wm-version">v{$wm_module_version|escape:'htmlall':'UTF-8'}</span>
  </div>

  <nav class="wm-tabs" aria-label="{l s='Module configuration areas' d='Modules.Wildenmanager.Admin'}">
    {if $wm_can_view_diagnostics}
    <button type="button" class="wm-tab is-active" data-wm-tab="orders"><i class="material-icons">fact_check</i> {l s='Order integrity' d='Modules.Wildenmanager.Admin'}</button>
    <button type="button" class="wm-tab" data-wm-tab="stock"><i class="material-icons">inventory_2</i> {l s='Stock and refunds' d='Modules.Wildenmanager.Admin'}</button>
    <button type="button" class="wm-tab" data-wm-tab="audit"><i class="material-icons">history</i> {l s='Audit' d='Modules.Wildenmanager.Admin'}</button>
    {/if}
    {if $wm_is_super_admin}
    <button type="button" class="wm-tab{if !$wm_can_view_diagnostics} is-active{/if}" data-wm-tab="admin"><i class="material-icons">admin_panel_settings</i> {l s='Administration' d='Modules.Wildenmanager.Admin'}</button>
    {/if}
    <a class="btn btn-outline-secondary wm-orders-link" href="{$wm_orders_url|escape:'htmlall':'UTF-8'}">
      <i class="material-icons">list_alt</i> {l s='Open Orders' d='Modules.Wildenmanager.Admin'}
    </a>
  </nav>

  <div class="wm-configuration-layout">
    <main class="wm-configuration-main">
      <div class="wm-tab-panel" data-wm-panel="admin"{if $wm_can_view_diagnostics} hidden{/if}>
      {if $wm_is_super_admin}
      <section class="card wm-configuration-card" id="wm-profile-permissions">
        <div class="card-header">
          <h3><i class="material-icons">admin_panel_settings</i> {l s='Permissions by profile' d='Modules.Wildenmanager.Admin'}</h3>
        </div>
        <div class="card-body">
          <div class="alert alert-info">
            {l s='Permissions are enforced both in the interface and on the server. SuperAdmin always retains full access.' d='Modules.Wildenmanager.Admin'}
          </div>
          <form method="post" action="{$wm_permissions_action|escape:'htmlall':'UTF-8'}">
            <div class="wm-permissions-table-wrap">
              <table class="table wm-permissions-table">
                <thead>
                  <tr>
                    <th>{l s='Profile' d='Modules.Wildenmanager.Admin'}</th>
                    <th>{l s='CSV/XLSX exports' d='Modules.Wildenmanager.Admin'}</th>
                    <th>{l s='Diagnostics and audit' d='Modules.Wildenmanager.Admin'}</th>
                    <th>{l s='Edit status history' d='Modules.Wildenmanager.Admin'}</th>
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
                    <td><input type="checkbox" name="wm_history_profiles[]" value="{$profile.id_profile|intval}"{if $profile.can_edit_history} checked{/if}{if $profile.is_super_admin} disabled{/if}></td>
                  </tr>
                  {/foreach}
                </tbody>
              </table>
            </div>
            <button type="submit" name="submitWmProfilePermissions" value="1" class="btn btn-primary">
              <i class="material-icons">save</i> {l s='Save permissions' d='Modules.Wildenmanager.Admin'}
            </button>
          </form>
        </div>
      </section>

      <section class="card wm-configuration-card" id="wm-order-deletion-settings">
        <div class="card-header">
          <h3><i class="material-icons">delete_forever</i> {l s='Permanent order deletion' d='Modules.Wildenmanager.Admin'}</h3>
        </div>
        <div class="card-body">
          <div class="alert alert-info">
            {l s='Only SuperAdmin can delete orders. Orders with an issued invoice or a credit slip are always protected. Orders with payments but no numbered invoice can be deleted; their payment records are removed with the order.' d='Modules.Wildenmanager.Admin'}
          </div>
          {if !$wm_stock_management_enabled}
          <div class="alert alert-warning">
            {l s='Stock management is disabled in this shop, so stock will not be changed even if this option is enabled.' d='Modules.Wildenmanager.Admin'}
          </div>
          {/if}
          <form method="post" action="{$wm_permissions_action|escape:'htmlall':'UTF-8'}">
            <div class="form-check">
              <input type="hidden" name="wm_delete_reinject_stock" value="0">
              <input class="form-check-input" type="checkbox" id="wm_delete_reinject_stock" name="wm_delete_reinject_stock" value="1"{if $wm_delete_reinject_stock} checked{/if}>
              <label class="form-check-label" for="wm_delete_reinject_stock">
                {l s='Return ordered quantities to stock when an order is deleted' d='Modules.Wildenmanager.Admin'}
              </label>
            </div>
            <p class="help-block text-muted">
              {l s='Quantities already reinjected by a refund are not added again. Cancelled orders and orders in error are skipped because PrestaShop already returned their stock. Reserved and physical quantities are always recalculated.' d='Modules.Wildenmanager.Admin'}
            </p>
            <button type="submit" name="submitWmDeletionSettings" value="1" class="btn btn-primary">
              <i class="material-icons">save</i> {l s='Save deletion settings' d='Modules.Wildenmanager.Admin'}
            </button>
          </form>
        </div>
      </section>
      {/if}

      </div>

      {if $wm_can_view_diagnostics}
      <div class="wm-tab-panel" data-wm-panel="orders">
      <section class="card wm-configuration-card" id="wm-integrity-dashboard">
        <div class="card-header">
          <h3><i class="material-icons">fact_check</i> {l s='Order integrity diagnostics' d='Modules.Wildenmanager.Admin'}</h3>
        </div>
        <div class="card-body">
          <div class="alert alert-info">
            {l s='This analysis is read-only. It never repairs or modifies orders, customers, addresses, histories or documents.' d='Modules.Wildenmanager.Admin'}
          </div>

          <div class="wm-integrity-summary">
            <span class="badge badge-danger wm-integrity-high"></span>
            <span class="badge badge-warning wm-integrity-medium"></span>
            <span class="badge badge-info wm-integrity-info"></span>
          </div>

          <div class="form-row wm-integrity-filters">
            <div class="form-group col-lg-5">
              <label for="wm-integrity-issue">{l s='Issue type' d='Modules.Wildenmanager.Admin'}</label>
              <select id="wm-integrity-issue" class="form-control wm-integrity-issue"><option value=""></option></select>
            </div>
            <div class="form-group col-lg-4">
              <label for="wm-integrity-severity">{l s='Severity' d='Modules.Wildenmanager.Admin'}</label>
              <select id="wm-integrity-severity" class="form-control wm-integrity-severity">
                <option value=""></option>
                <option value="high"></option>
                <option value="medium"></option>
                <option value="info"></option>
              </select>
            </div>
            <div class="form-group col-lg-3 wm-integrity-filter-actions">
              <button type="button" class="btn btn-outline-primary wm-integrity-refresh">
                <i class="material-icons">refresh</i> {l s='Refresh analysis' d='Modules.Wildenmanager.Admin'}
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
                    <th>{l s='Severity' d='Modules.Wildenmanager.Admin'}</th>
                    <th>{l s='Issue' d='Modules.Wildenmanager.Admin'}</th>
                    <th>{l s='Order' d='Modules.Wildenmanager.Admin'}</th>
                    <th>{l s='Reference' d='Modules.Wildenmanager.Admin'}</th>
                    <th>{l s='Date' d='Modules.Wildenmanager.Admin'}</th>
                    <th>{l s='Detail' d='Modules.Wildenmanager.Admin'}</th>
                    <th>{l s='Review' d='Modules.Wildenmanager.Admin'}</th>
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
              <i class="material-icons">download</i> {l s='Export filtered CSV' d='Modules.Wildenmanager.Admin'}
            </button>
          </div>
        </div>
      </section>
      </div>

      <div class="wm-tab-panel" data-wm-panel="stock" hidden>
      <section class="card wm-configuration-card" id="wm-stock-integrity-dashboard">
        <div class="card-header">
          <h3><i class="material-icons">inventory_2</i> {l s='Stock, cancellation and refund diagnostics' d='Modules.Wildenmanager.Admin'}</h3>
        </div>
        <div class="card-body">
          <div class="alert alert-info">
            {l s='Analysis and review states do not modify shop data. Only SuperAdmin can repair an individually confirmed physical-cache mismatch; sellable and reserved quantities remain unchanged.' d='Modules.Wildenmanager.Admin'}
          </div>

          <div class="wm-stock-summary">
            <span class="badge badge-danger wm-stock-high"></span>
            <span class="badge badge-warning wm-stock-medium"></span>
            <span class="badge badge-info wm-stock-info"></span>
          </div>

          <div class="wm-stock-filters">
            <div class="form-group">
              <label for="wm-stock-issue">{l s='Issue type' d='Modules.Wildenmanager.Admin'}</label>
              <select id="wm-stock-issue" class="form-control wm-stock-issue"><option value=""></option></select>
            </div>
            <div class="form-group">
              <label for="wm-stock-severity">{l s='Severity' d='Modules.Wildenmanager.Admin'}</label>
              <select id="wm-stock-severity" class="form-control wm-stock-severity">
                <option value=""></option>
                <option value="high"></option>
                <option value="medium"></option>
                <option value="info"></option>
              </select>
            </div>
            <div class="form-group">
              <label for="wm-stock-kind">{l s='Product type' d='Modules.Wildenmanager.Admin'}</label>
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
                <i class="material-icons">refresh</i> {l s='Refresh analysis' d='Modules.Wildenmanager.Admin'}
              </button>
            </div>
          </div>

          <div class="alert wm-stock-message" hidden></div>
          <div class="alert alert-success wm-stock-empty" hidden></div>
          <div class="wm-stock-table-wrap" hidden>
            <table class="wm-stock-table">
              <thead>
                <tr>
                  <th>{l s='Severity' d='Modules.Wildenmanager.Admin'}</th>
                  <th>{l s='Issue' d='Modules.Wildenmanager.Admin'}</th>
                  <th>{l s='Type' d='Modules.Wildenmanager.Admin'}</th>
                  <th>{l s='Order' d='Modules.Wildenmanager.Admin'}</th>
                  <th>{l s='Product' d='Modules.Wildenmanager.Admin'}</th>
                  <th>{l s='Date' d='Modules.Wildenmanager.Admin'}</th>
                  <th>{l s='Detail' d='Modules.Wildenmanager.Admin'}</th>
                  <th>{l s='Review and action' d='Modules.Wildenmanager.Admin'}</th>
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
              <i class="material-icons">download</i> {l s='Export filtered CSV' d='Modules.Wildenmanager.Admin'}
            </button>
          </div>
        </div>
      </section>
      </div>

      <div class="wm-tab-panel" data-wm-panel="audit" hidden>
      <section class="card wm-configuration-card" id="wm-audit-dashboard">
        <div class="card-header">
          <h3><i class="material-icons">history</i> {l s='Audit history' d='Modules.Wildenmanager.Admin'}</h3>
        </div>
        <div class="card-body">
          <div class="alert alert-info">
            {l s='This append-only history records actions performed through Wilden Manager. Viewing it never modifies shop data.' d='Modules.Wildenmanager.Admin'}
          </div>

          <div class="wm-audit-filters">
            <div class="form-group">
              <label for="wm-audit-action">{l s='Action' d='Modules.Wildenmanager.Admin'}</label>
              <select id="wm-audit-action" class="form-control wm-audit-action"><option value=""></option></select>
            </div>
            <div class="form-group">
              <label for="wm-audit-employee">{l s='Employee' d='Modules.Wildenmanager.Admin'}</label>
              <select id="wm-audit-employee" class="form-control wm-audit-employee"><option value=""></option></select>
            </div>
            <div class="form-group">
              <label for="wm-audit-order">{l s='Order ID' d='Modules.Wildenmanager.Admin'}</label>
              <input id="wm-audit-order" class="form-control wm-audit-order" type="number" min="1" step="1">
            </div>
            <div class="form-group">
              <label for="wm-audit-date-from">{l s='From' d='Modules.Wildenmanager.Admin'}</label>
              <input id="wm-audit-date-from" class="form-control wm-audit-date-from" type="date">
            </div>
            <div class="form-group">
              <label for="wm-audit-date-to">{l s='To' d='Modules.Wildenmanager.Admin'}</label>
              <input id="wm-audit-date-to" class="form-control wm-audit-date-to" type="date">
            </div>
            <div class="form-group wm-audit-filter-actions">
              <button type="button" class="btn btn-outline-primary wm-audit-apply">
                <i class="material-icons">filter_alt</i> {l s='Apply filters' d='Modules.Wildenmanager.Admin'}
              </button>
              <button type="button" class="btn btn-outline-secondary wm-audit-reset">
                {l s='Clear' d='Modules.Wildenmanager.Admin'}
              </button>
            </div>
          </div>

          <div class="alert wm-audit-message" hidden></div>
          <div class="alert alert-secondary wm-audit-empty" hidden></div>
          <div class="wm-audit-table-wrap" hidden>
            <table class="wm-audit-table">
              <thead>
                <tr>
                  <th>{l s='Date' d='Modules.Wildenmanager.Admin'}</th>
                  <th>{l s='Employee' d='Modules.Wildenmanager.Admin'}</th>
                  <th>{l s='Action' d='Modules.Wildenmanager.Admin'}</th>
                  <th>{l s='Order' d='Modules.Wildenmanager.Admin'}</th>
                  <th>{l s='Store' d='Modules.Wildenmanager.Admin'}</th>
                  <th>{l s='Details' d='Modules.Wildenmanager.Admin'}</th>
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
      </div>
      {else}
      <div class="alert alert-warning">
        {l s='Your employee profile does not have permission to access diagnostics or audit history.' d='Modules.Wildenmanager.Admin'}
      </div>
      {/if}
    </main>
  </div>
</div>
