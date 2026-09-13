{*
 * Wilden Manager
 * Copyright 2026 Wilden Militaria S.L.
 *}

<div class="wm-manager">
  <div class="panel wm-filter-panel">
    <div class="panel-heading">
      <i class="icon-search"></i> {l s='Advanced order search' mod='wilden_manager'}
    </div>

    <form method="post" action="{$wm_base_url|escape:'htmlall':'UTF-8'}" class="form-horizontal">
      <input type="hidden" name="wm_filter_submitted" value="1">

      <div class="row">
        <div class="col-lg-2 col-md-3 form-group">
          <label>{l s='Order ID' mod='wilden_manager'}</label>
          <input type="number" min="1" name="id_order_filter" value="{$wm_filters.id_order|escape:'htmlall':'UTF-8'}" class="form-control">
        </div>
        <div class="col-lg-2 col-md-3 form-group">
          <label>{l s='Reference' mod='wilden_manager'}</label>
          <input type="text" name="reference" value="{$wm_filters.reference|escape:'htmlall':'UTF-8'}" class="form-control">
        </div>
        <div class="col-lg-3 col-md-6 form-group">
          <label>{l s='Customer name or email' mod='wilden_manager'}</label>
          <input type="text" name="customer" value="{$wm_filters.customer|escape:'htmlall':'UTF-8'}" class="form-control">
        </div>
        <div class="col-lg-2 col-md-3 form-group">
          <label>{l s='State' mod='wilden_manager'}</label>
          <select name="id_order_state" class="form-control">
            <option value="0">{l s='All states' mod='wilden_manager'}</option>
            {foreach from=$wm_order_states item=state}
              <option value="{$state.id_order_state|intval}"{if $wm_filters.id_order_state == $state.id_order_state} selected{/if}>{$state.name|escape:'htmlall':'UTF-8'}</option>
            {/foreach}
          </select>
        </div>
        <div class="col-lg-3 col-md-3 form-group">
          <label>{l s='Payment method' mod='wilden_manager'}</label>
          <input type="text" name="payment" value="{$wm_filters.payment|escape:'htmlall':'UTF-8'}" class="form-control">
        </div>
      </div>

      <div class="row">
        <div class="col-lg-2 col-md-3 form-group">
          <label>{l s='Date from' mod='wilden_manager'}</label>
          <input type="date" name="date_from" value="{$wm_filters.date_from|escape:'htmlall':'UTF-8'}" class="form-control">
        </div>
        <div class="col-lg-2 col-md-3 form-group">
          <label>{l s='Date to' mod='wilden_manager'}</label>
          <input type="date" name="date_to" value="{$wm_filters.date_to|escape:'htmlall':'UTF-8'}" class="form-control">
        </div>
        <div class="col-lg-2 col-md-3 form-group">
          <label>{l s='Minimum total' mod='wilden_manager'}</label>
          <input type="number" step="0.01" name="total_min" value="{$wm_filters.total_min|escape:'htmlall':'UTF-8'}" class="form-control">
        </div>
        <div class="col-lg-2 col-md-3 form-group">
          <label>{l s='Maximum total' mod='wilden_manager'}</label>
          <input type="number" step="0.01" name="total_max" value="{$wm_filters.total_max|escape:'htmlall':'UTF-8'}" class="form-control">
        </div>
        <div class="col-lg-2 col-md-3 form-group">
          <label>{l s='Carrier' mod='wilden_manager'}</label>
          <input type="text" name="carrier" value="{$wm_filters.carrier|escape:'htmlall':'UTF-8'}" class="form-control">
        </div>
        <div class="col-lg-2 col-md-3 form-group">
          <label>{l s='Internal note contains' mod='wilden_manager'}</label>
          <input type="text" name="note" value="{$wm_filters.note|escape:'htmlall':'UTF-8'}" class="form-control">
        </div>
      </div>

      <details class="wm-columns">
        <summary>{l s='Visible columns' mod='wilden_manager'}</summary>
        <div class="wm-column-options">
          {foreach from=$wm_available_columns key=columnKey item=columnName}
            <label class="checkbox-inline">
              <input type="checkbox" name="columns[]" value="{$columnKey|escape:'htmlall':'UTF-8'}"{if in_array($columnKey, $wm_columns)} checked{/if}>
              {$columnName|escape:'htmlall':'UTF-8'}
            </label>
          {/foreach}
        </div>
      </details>

      <div class="wm-filter-actions">
        <label class="wm-inline-control">{l s='Order by' mod='wilden_manager'}
          <select name="sort" class="form-control">
            <option value="date_add"{if $wm_sort == 'date_add'} selected{/if}>{l s='Date' mod='wilden_manager'}</option>
            <option value="id_order"{if $wm_sort == 'id_order'} selected{/if}>{l s='Order ID' mod='wilden_manager'}</option>
            <option value="reference"{if $wm_sort == 'reference'} selected{/if}>{l s='Reference' mod='wilden_manager'}</option>
            <option value="customer"{if $wm_sort == 'customer'} selected{/if}>{l s='Customer' mod='wilden_manager'}</option>
            <option value="total"{if $wm_sort == 'total'} selected{/if}>{l s='Total' mod='wilden_manager'}</option>
            <option value="state"{if $wm_sort == 'state'} selected{/if}>{l s='State' mod='wilden_manager'}</option>
          </select>
        </label>
        <label class="wm-inline-control">{l s='Direction' mod='wilden_manager'}
          <select name="direction" class="form-control">
            <option value="DESC"{if $wm_direction == 'DESC'} selected{/if}>{l s='Descending' mod='wilden_manager'}</option>
            <option value="ASC"{if $wm_direction == 'ASC'} selected{/if}>{l s='Ascending' mod='wilden_manager'}</option>
          </select>
        </label>
        <label class="wm-inline-control">{l s='Rows' mod='wilden_manager'}
          <select name="limit" class="form-control">
            {foreach from=$wm_limits item=rowLimit}<option value="{$rowLimit|intval}"{if $wm_limit == $rowLimit} selected{/if}>{$rowLimit|intval}</option>{/foreach}
          </select>
        </label>
        <button type="submit" class="btn btn-primary">
          <i class="icon-search"></i> {l s='Apply filters' mod='wilden_manager'}
        </button>
        <a href="{$wm_base_url|escape:'htmlall':'UTF-8'}" class="btn btn-default">
          <i class="icon-eraser"></i> {l s='Clear' mod='wilden_manager'}
        </a>
        <a href="{$wm_base_url|escape:'htmlall':'UTF-8'}&amp;{$wm_query|escape:'htmlall':'UTF-8'}&amp;exportWmOrders=1" class="btn btn-default">
          <i class="icon-download"></i> {l s='Export CSV' mod='wilden_manager'}
        </a>

        <span class="wm-save-view">
          <input type="text" name="wm_view_name" maxlength="128" placeholder="{l s='Name this view' mod='wilden_manager'}" class="form-control">
          <label><input type="checkbox" name="wm_view_default" value="1"> {l s='Default' mod='wilden_manager'}</label>
          <button type="submit" name="submitWmSaveView" value="1" class="btn btn-default">
            <i class="icon-save"></i> {l s='Save view' mod='wilden_manager'}
          </button>
        </span>
      </div>
    </form>

    {if $wm_saved_views}
      <div class="wm-saved-views">
        <strong>{l s='Saved views:' mod='wilden_manager'}</strong>
        {foreach from=$wm_saved_views item=view}
          <span class="wm-saved-view{if $wm_active_view_id == $view.id_wilden_manager_saved_view} active{/if}">
            <a href="{$wm_base_url|escape:'htmlall':'UTF-8'}&amp;id_wm_view={$view.id_wilden_manager_saved_view|intval}">{$view.name|escape:'htmlall':'UTF-8'}</a>
            <form method="post" action="{$wm_base_url|escape:'htmlall':'UTF-8'}" class="wm-delete-view-form">
              <input type="hidden" name="id_wm_view" value="{$view.id_wilden_manager_saved_view|intval}">
              <button type="submit" name="deleteWmView" value="1" class="wm-delete-view" data-confirm="{l s='Delete this saved view?' mod='wilden_manager'}">&times;</button>
            </form>
          </span>
        {/foreach}
      </div>
    {/if}
  </div>

  {if $wm_bulk_preview}
    <div class="panel wm-preview-panel">
      <div class="panel-heading"><i class="icon-eye"></i> {l s='Operation preview' mod='wilden_manager'}</div>
      <p class="alert alert-warning">
        {l s='Review the changes below. Nothing has been modified yet.' mod='wilden_manager'}
      </p>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>ID</th><th>{l s='Reference' mod='wilden_manager'}</th><th>{l s='Current state' mod='wilden_manager'}</th><th>{l s='New state' mod='wilden_manager'}</th></tr></thead>
          <tbody>
          {foreach from=$wm_bulk_preview.orders item=previewOrder}
            <tr>
              <td>{$previewOrder.id_order|intval}</td>
              <td>{$previewOrder.reference|escape:'htmlall':'UTF-8'}</td>
              <td>{$previewOrder.state_name|escape:'htmlall':'UTF-8'}</td>
              <td><strong>{$wm_bulk_preview.target_state.name|escape:'htmlall':'UTF-8'}</strong></td>
            </tr>
          {/foreach}
          </tbody>
        </table>
      </div>
      <form method="post" action="{$wm_base_url|escape:'htmlall':'UTF-8'}" class="wm-confirm-bulk">
        {foreach from=$wm_bulk_preview.ids item=previewId}<input type="hidden" name="order_ids[]" value="{$previewId|intval}">{/foreach}
        <input type="hidden" name="bulk_state" value="{$wm_bulk_preview.target_state.id|intval}">
        <input type="hidden" name="preview_timestamp" value="{$wm_bulk_preview.timestamp|intval}">
        <input type="hidden" name="preview_snapshot" value="{$wm_bulk_preview.snapshot|escape:'htmlall':'UTF-8'}">
        <input type="hidden" name="preview_signature" value="{$wm_bulk_preview.signature|escape:'htmlall':'UTF-8'}">
        <label><input type="checkbox" name="send_email" value="1"> {l s='Send the state email to customers' mod='wilden_manager'}</label>
        <button type="submit" name="submitWmBulkExecute" value="1" class="btn btn-danger wm-confirm-action" data-confirm="{l s='Apply this state to the selected orders?' mod='wilden_manager'}">
          <i class="icon-check"></i> {l s='Confirm and execute' mod='wilden_manager'}
        </button>
      </form>
    </div>
  {/if}

  <form method="post" action="{$wm_base_url|escape:'htmlall':'UTF-8'}&amp;{$wm_query|escape:'htmlall':'UTF-8'}" id="wm-orders-form">
    <div class="panel">
      <div class="panel-heading">
        <i class="icon-shopping-cart"></i> {l s='Orders' mod='wilden_manager'}
        <span class="badge">{$wm_total|intval}</span>
      </div>

      <div class="table-responsive">
        <table class="table table-striped table-hover wm-orders-table">
          <thead>
            <tr>
              <th class="wm-select"><input type="checkbox" id="wm-select-all" aria-label="{l s='Select all visible orders' mod='wilden_manager'}"></th>
              {foreach from=$wm_columns item=column}
                <th>{$wm_available_columns[$column]|escape:'htmlall':'UTF-8'}</th>
              {/foreach}
              <th>{l s='Actions' mod='wilden_manager'}</th>
            </tr>
          </thead>
          <tbody>
          {foreach from=$wm_orders item=order}
            <tr>
              <td class="wm-select"><input type="checkbox" name="order_ids[]" value="{$order.id_order|intval}" class="wm-order-checkbox"{if in_array($order.id_order, $wm_selected_order_ids)} checked{/if}></td>
              {foreach from=$wm_columns item=column}
                <td>
                  {if $column == 'id_order'}#{$order.id_order|intval}
                  {elseif $column == 'reference'}{$order.reference|escape:'htmlall':'UTF-8'}
                  {elseif $column == 'customer'}{$order.customer|escape:'htmlall':'UTF-8'}
                  {elseif $column == 'email'}<a href="mailto:{$order.email|escape:'htmlall':'UTF-8'}">{$order.email|escape:'htmlall':'UTF-8'}</a>
                  {elseif $column == 'total'}<strong>{$order.formatted_total|escape:'htmlall':'UTF-8'}</strong>
                  {elseif $column == 'state'}<span class="wm-state" style="--wm-state-color: {$order.state_color|escape:'htmlall':'UTF-8'}">{$order.state_name|escape:'htmlall':'UTF-8'}</span>
                  {elseif $column == 'payment'}{$order.payment|escape:'htmlall':'UTF-8'}
                  {elseif $column == 'carrier'}{$order.carrier_name|escape:'htmlall':'UTF-8'}
                  {elseif $column == 'note'}<span class="wm-note-preview" title="{$order.note|escape:'htmlall':'UTF-8'}">{$order.note|truncate:80:'…'|escape:'htmlall':'UTF-8'}</span>
                  {elseif $column == 'date_add'}{$order.date_add|escape:'htmlall':'UTF-8'}
                  {/if}
                </td>
              {/foreach}
              <td class="wm-actions">
                <button type="button" class="btn btn-default btn-sm wm-quick-view" data-order-id="{$order.id_order|intval}" title="{l s='Quick view' mod='wilden_manager'}"><i class="icon-eye"></i></button>
                <a href="{$order.view_url|escape:'htmlall':'UTF-8'}" class="btn btn-default btn-sm" title="{l s='Open native order page' mod='wilden_manager'}"><i class="icon-external-link"></i></a>
                <a href="{$order.invoice_url|escape:'htmlall':'UTF-8'}" class="btn btn-default btn-sm" title="{l s='Download invoice' mod='wilden_manager'}"><i class="icon-file-pdf-o"></i></a>
              </td>
            </tr>
          {foreachelse}
            <tr><td colspan="12" class="text-center text-muted">{l s='No orders match these filters.' mod='wilden_manager'}</td></tr>
          {/foreach}
          </tbody>
        </table>
      </div>

      <div class="wm-table-footer">
        <div class="wm-pagination">
          {if $wm_page > 1}<a class="btn btn-default" href="{$wm_base_url|escape:'htmlall':'UTF-8'}&amp;{$wm_query|escape:'htmlall':'UTF-8'}&amp;page={$wm_page-1}">&larr;</a>{/if}
          <span>{l s='Page' mod='wilden_manager'} {$wm_page|intval} / {$wm_total_pages|intval}</span>
          {if $wm_page < $wm_total_pages}<a class="btn btn-default" href="{$wm_base_url|escape:'htmlall':'UTF-8'}&amp;{$wm_query|escape:'htmlall':'UTF-8'}&amp;page={$wm_page+1}">&rarr;</a>{/if}
        </div>
        {if $wm_can_edit}
          <div class="wm-bulk-controls">
            <span class="wm-selected-count">0 {l s='selected' mod='wilden_manager'}</span>
            <select name="bulk_state" class="form-control">
              <option value="0">{l s='Choose new state' mod='wilden_manager'}</option>
              {foreach from=$wm_order_states item=state}<option value="{$state.id_order_state|intval}">{$state.name|escape:'htmlall':'UTF-8'}</option>{/foreach}
            </select>
            <button type="submit" name="submitWmBulkPreview" value="1" class="btn btn-warning">
              <i class="icon-eye"></i> {l s='Preview state change' mod='wilden_manager'}
            </button>
            <small>{l s='Maximum per operation:' mod='wilden_manager'} {$wm_max_bulk|intval}</small>
          </div>
        {/if}
      </div>
    </div>
  </form>

  <div class="panel wm-audit-panel">
    <div class="panel-heading"><i class="icon-history"></i> {l s='Recent Wilden Manager activity' mod='wilden_manager'}</div>
    <div class="table-responsive">
      <table class="table">
        <thead><tr><th>{l s='Date' mod='wilden_manager'}</th><th>{l s='Employee' mod='wilden_manager'}</th><th>{l s='Order' mod='wilden_manager'}</th><th>{l s='Action' mod='wilden_manager'}</th></tr></thead>
        <tbody>
        {foreach from=$wm_recent_audit item=audit}
          <tr><td>{$audit.date_add|escape:'htmlall':'UTF-8'}</td><td>{$audit.employee_name|escape:'htmlall':'UTF-8'}</td><td>{if $audit.id_order}#{$audit.id_order|intval}{else}—{/if}</td><td><code>{$audit.action|escape:'htmlall':'UTF-8'}</code></td></tr>
        {foreachelse}<tr><td colspan="4" class="text-muted text-center">{l s='No activity recorded yet.' mod='wilden_manager'}</td></tr>{/foreach}
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="wm-quick-view-modal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">{l s='Order quick view' mod='wilden_manager'}</h4></div>
      <div class="modal-body"><div class="wm-loading"><i class="icon-refresh icon-spin"></i> {l s='Loading…' mod='wilden_manager'}</div></div>
    </div>
  </div>
</div>

<script>
  window.wildenManagerConfig = {
    ajaxUrl: '{$wm_ajax_url|escape:'javascript':'UTF-8'}',
    maxBulk: {$wm_max_bulk|intval},
    loadingText: '{l s='Loading…' mod='wilden_manager' js=1}',
    errorText: '{l s='The order could not be loaded.' mod='wilden_manager' js=1}'
  };
</script>
