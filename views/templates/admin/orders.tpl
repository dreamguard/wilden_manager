{* Wilden Manager - native-style order grid *}

<div class="wm-manager">
  {if $wm_bulk_preview}
    <div class="panel wm-preview-panel">
      <div class="panel-heading"><i class="icon-eye"></i> {l s='Operation preview' mod='wilden_manager'}</div>
      <p class="alert alert-warning">{l s='Review the changes below. Nothing has been modified yet.' mod='wilden_manager'}</p>
      <div class="table-responsive"><table class="table">
        <thead><tr><th>ID</th><th>{l s='Reference' mod='wilden_manager'}</th><th>{l s='Current state' mod='wilden_manager'}</th><th>{l s='New state' mod='wilden_manager'}</th></tr></thead>
        <tbody>{foreach from=$wm_bulk_preview.orders item=previewOrder}<tr>
          <td>{$previewOrder.id_order|intval}</td><td>{$previewOrder.reference|escape:'htmlall':'UTF-8'}</td>
          <td>{$previewOrder.state_name|escape:'htmlall':'UTF-8'}</td><td><strong>{$wm_bulk_preview.target_state.name|escape:'htmlall':'UTF-8'}</strong></td>
        </tr>{/foreach}</tbody>
      </table></div>
      <form method="post" action="{$wm_base_url|escape:'htmlall':'UTF-8'}" class="wm-confirm-bulk">
        {foreach from=$wm_bulk_preview.ids item=previewId}<input type="hidden" name="order_ids[]" value="{$previewId|intval}">{/foreach}
        <input type="hidden" name="bulk_state" value="{$wm_bulk_preview.target_state.id|intval}">
        <input type="hidden" name="preview_timestamp" value="{$wm_bulk_preview.timestamp|intval}">
        <input type="hidden" name="preview_snapshot" value="{$wm_bulk_preview.snapshot|escape:'htmlall':'UTF-8'}">
        <input type="hidden" name="preview_signature" value="{$wm_bulk_preview.signature|escape:'htmlall':'UTF-8'}">
        <label><input type="checkbox" name="send_email" value="1"> {l s='Send the state email to customers' mod='wilden_manager'}</label>
        <button type="submit" name="submitWmBulkExecute" value="1" class="btn btn-danger wm-confirm-action" data-confirm="{l s='Apply this state to the selected orders?' mod='wilden_manager'}"><i class="icon-check"></i> {l s='Confirm and execute' mod='wilden_manager'}</button>
      </form>
    </div>
  {/if}

  {if $wm_saved_views}<div class="wm-saved-views">
    <strong>{l s='Saved views:' mod='wilden_manager'}</strong>
    {foreach from=$wm_saved_views item=view}<span class="wm-saved-view{if $wm_active_view_id == $view.id_wilden_manager_saved_view} active{/if}">
      <a href="{$wm_base_url|escape:'htmlall':'UTF-8'}&amp;id_wm_view={$view.id_wilden_manager_saved_view|intval}">{$view.name|escape:'htmlall':'UTF-8'}</a>
      <form method="post" action="{$wm_base_url|escape:'htmlall':'UTF-8'}" class="wm-delete-view-form"><input type="hidden" name="id_wm_view" value="{$view.id_wilden_manager_saved_view|intval}"><button type="submit" name="deleteWmView" value="1" class="wm-delete-view" data-confirm="{l s='Delete this saved view?' mod='wilden_manager'}">&times;</button></form>
    </span>{/foreach}
  </div>{/if}

  <form method="post" action="{$wm_base_url|escape:'htmlall':'UTF-8'}" id="wm-orders-form">
    <input type="hidden" name="wm_filter_submitted" value="1"><input type="hidden" name="sort" value="{$wm_sort|escape:'htmlall':'UTF-8'}"><input type="hidden" name="direction" value="{$wm_direction|escape:'htmlall':'UTF-8'}">
    <div class="card wm-grid-card">
      <div class="card-header wm-grid-header">
        <div class="wm-grid-title"><i class="material-icons">shopping_cart</i><span>{l s='Orders' mod='wilden_manager'}</span><span class="badge badge-secondary">{$wm_total|intval}</span></div>
        <div class="wm-grid-actions">
          <div class="dropdown wm-columns-dropdown">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-toggle="dropdown"><i class="material-icons">view_column</i> {l s='Columns' mod='wilden_manager'}</button>
            <div class="dropdown-menu dropdown-menu-right wm-column-menu">
              <div class="wm-column-menu-title">{l s='Visible columns' mod='wilden_manager'}</div>
              {foreach from=$wm_available_columns key=columnKey item=columnLabel}<label class="dropdown-item"><input type="checkbox" name="columns[]" value="{$columnKey|escape:'htmlall':'UTF-8'}"{if in_array($columnKey, $wm_columns)} checked{/if}> {$columnLabel|escape:'htmlall':'UTF-8'}</label>{/foreach}
              <div class="dropdown-divider"></div><button type="submit" class="btn btn-primary btn-sm wm-apply-columns">{l s='Apply columns' mod='wilden_manager'}</button>
            </div>
          </div>
          <a href="{$wm_base_url|escape:'htmlall':'UTF-8'}&amp;{$wm_query|escape:'htmlall':'UTF-8'}&amp;exportWmOrders=1" class="btn btn-outline-secondary"><i class="material-icons">cloud_download</i> {l s='Export' mod='wilden_manager'}</a>
          <a href="{$wm_base_url|escape:'htmlall':'UTF-8'}" class="btn btn-outline-secondary" title="{l s='Refresh list' mod='wilden_manager'}"><i class="material-icons">refresh</i></a>
        </div>
      </div>

      <div class="wm-view-toolbar"><input type="text" name="wm_view_name" maxlength="128" placeholder="{l s='Name this view' mod='wilden_manager'}" class="form-control"><label><input type="checkbox" name="wm_view_default" value="1"> {l s='Default view' mod='wilden_manager'}</label><button type="submit" name="submitWmSaveView" value="1" class="btn btn-outline-secondary"><i class="material-icons">bookmark_border</i> {l s='Save current view' mod='wilden_manager'}</button></div>

      <div class="table-responsive"><table class="table grid-table wm-orders-table">
        <thead>
          <tr class="column-headers"><th class="wm-select"><input type="checkbox" id="wm-select-all"></th>
            {foreach from=$wm_columns item=column}{assign var=nextDirection value='ASC'}{if $wm_sort == $column && $wm_direction == 'ASC'}{assign var=nextDirection value='DESC'}{/if}
              <th class="column-{$column|escape:'htmlall':'UTF-8'}">{if $column != 'note'}<a href="{$wm_base_url|escape:'htmlall':'UTF-8'}&amp;{$wm_query|escape:'htmlall':'UTF-8'}&amp;sort={$column|escape:'htmlall':'UTF-8'}&amp;direction={$nextDirection}">{$wm_available_columns[$column]|escape:'htmlall':'UTF-8'}{if $wm_sort == $column}<i class="material-icons wm-sort-icon">{if $wm_direction == 'ASC'}arrow_upward{else}arrow_downward{/if}</i>{/if}</a>{else}{$wm_available_columns[$column]|escape:'htmlall':'UTF-8'}{/if}</th>
            {/foreach}<th class="wm-actions">{l s='Actions' mod='wilden_manager'}</th>
          </tr>
          <tr class="column-filters"><th></th>{foreach from=$wm_columns item=column}<th>
            {if $column == 'id_order'}<input type="text" name="id_order_filter" value="{$wm_filters.id_order|escape:'htmlall':'UTF-8'}" class="form-control" placeholder="ID">
            {elseif $column == 'reference'}<input type="text" name="reference" value="{$wm_filters.reference|escape:'htmlall':'UTF-8'}" class="form-control" placeholder="{l s='Search reference' mod='wilden_manager'}">
            {elseif $column == 'new'}<select name="new_customer" class="form-control"><option value="">-</option><option value="1"{if $wm_filters.new_customer == '1'} selected{/if}>{l s='Yes' mod='wilden_manager'}</option><option value="0"{if $wm_filters.new_customer == '0'} selected{/if}>{l s='No' mod='wilden_manager'}</option></select>
            {elseif $column == 'country'}<input type="text" name="country" value="{$wm_filters.country|escape:'htmlall':'UTF-8'}" class="form-control" placeholder="{l s='Delivery country' mod='wilden_manager'}">
            {elseif $column == 'customer'}<input type="text" name="customer" value="{$wm_filters.customer|escape:'htmlall':'UTF-8'}" class="form-control" placeholder="{l s='Search customer' mod='wilden_manager'}">
            {elseif $column == 'email'}<input type="text" name="email" value="{$wm_filters.email|escape:'htmlall':'UTF-8'}" class="form-control" placeholder="Email">
            {elseif $column == 'company'}<input type="text" name="company" value="{$wm_filters.company|escape:'htmlall':'UTF-8'}" class="form-control" placeholder="{l s='Search company' mod='wilden_manager'}">
            {elseif $column == 'total'}<div class="wm-range-filter"><input type="text" name="total_min" value="{$wm_filters.total_min|escape:'htmlall':'UTF-8'}" class="form-control" placeholder="Min"><input type="text" name="total_max" value="{$wm_filters.total_max|escape:'htmlall':'UTF-8'}" class="form-control" placeholder="Max"></div>
            {elseif $column == 'state'}<select name="id_order_state" class="form-control"><option value="0">-</option>{foreach from=$wm_order_states item=state}<option value="{$state.id_order_state|intval}"{if $wm_filters.id_order_state == $state.id_order_state} selected{/if}>{$state.name|escape:'htmlall':'UTF-8'}</option>{/foreach}</select>
            {elseif $column == 'payment'}<input type="text" name="payment" value="{$wm_filters.payment|escape:'htmlall':'UTF-8'}" class="form-control" placeholder="{l s='Search payment' mod='wilden_manager'}">
            {elseif $column == 'carrier'}<input type="text" name="carrier" value="{$wm_filters.carrier|escape:'htmlall':'UTF-8'}" class="form-control" placeholder="{l s='Search carrier' mod='wilden_manager'}">
            {elseif $column == 'shop'}<input type="text" name="shop" value="{$wm_filters.shop|escape:'htmlall':'UTF-8'}" class="form-control" placeholder="{l s='Search store' mod='wilden_manager'}">
            {elseif $column == 'note'}<input type="text" name="note" value="{$wm_filters.note|escape:'htmlall':'UTF-8'}" class="form-control" placeholder="{l s='Search note' mod='wilden_manager'}">
            {elseif $column == 'date_add'}<div class="wm-range-filter"><input type="date" name="date_from" value="{$wm_filters.date_from|escape:'htmlall':'UTF-8'}" class="form-control"><input type="date" name="date_to" value="{$wm_filters.date_to|escape:'htmlall':'UTF-8'}" class="form-control"></div>{/if}
          </th>{/foreach}<th class="wm-filter-actions-cell"><button type="submit" class="btn btn-primary btn-sm" title="{l s='Search' mod='wilden_manager'}"><i class="material-icons">search</i></button><a href="{$wm_base_url|escape:'htmlall':'UTF-8'}" class="btn btn-outline-secondary btn-sm" title="{l s='Reset' mod='wilden_manager'}"><i class="material-icons">clear</i></a></th></tr>
        </thead>
        <tbody>{foreach from=$wm_orders item=order}<tr>
          <td class="wm-select"><input type="checkbox" name="order_ids[]" value="{$order.id_order|intval}" class="wm-order-checkbox"{if in_array($order.id_order, $wm_selected_order_ids)} checked{/if}></td>
          {foreach from=$wm_columns item=column}<td class="column-{$column|escape:'htmlall':'UTF-8'}">
            {if $column == 'id_order'}<button type="button" class="btn btn-link wm-id-preview wm-quick-view" data-order-id="{$order.id_order|intval}"><i class="material-icons">keyboard_arrow_down</i>{$order.id_order|intval}</button>
            {elseif $column == 'reference'}<a href="{$order.view_url|escape:'htmlall':'UTF-8'}"><strong>{$order.reference|escape:'htmlall':'UTF-8'}</strong></a>
            {elseif $column == 'new'}<span class="badge {if $order.is_new_customer}badge-success{else}badge-secondary{/if}">{if $order.is_new_customer}{l s='Yes' mod='wilden_manager'}{else}{l s='No' mod='wilden_manager'}{/if}</span>
            {elseif $column == 'country'}{$order.country_name|escape:'htmlall':'UTF-8'}
            {elseif $column == 'customer'}{if !$order.deleted_customer}<a href="{$order.customer_url|escape:'htmlall':'UTF-8'}" target="_blank">{$order.customer|escape:'htmlall':'UTF-8'}</a>{else}{$order.customer|escape:'htmlall':'UTF-8'}{/if}
            {elseif $column == 'email'}<a href="mailto:{$order.email|escape:'htmlall':'UTF-8'}">{$order.email|escape:'htmlall':'UTF-8'}</a>
            {elseif $column == 'company'}{$order.company|escape:'htmlall':'UTF-8'}
            {elseif $column == 'total'}<strong>{$order.formatted_total|escape:'htmlall':'UTF-8'}</strong>
            {elseif $column == 'state'}<span class="wm-state" style="--wm-state-color: {$order.state_color|escape:'htmlall':'UTF-8'}">{$order.state_name|escape:'htmlall':'UTF-8'}</span>
            {elseif $column == 'payment'}{$order.payment|escape:'htmlall':'UTF-8'}
            {elseif $column == 'carrier'}{$order.carrier_name|escape:'htmlall':'UTF-8'}
            {elseif $column == 'shop'}{$order.shop_name|escape:'htmlall':'UTF-8'}
            {elseif $column == 'note'}<span class="wm-note-preview" title="{$order.note|escape:'htmlall':'UTF-8'}">{$order.note|truncate:80:'…'|escape:'htmlall':'UTF-8'}</span>
            {elseif $column == 'date_add'}{$order.formatted_date|escape:'htmlall':'UTF-8'}{/if}
          </td>{/foreach}
          <td class="wm-actions"><div class="btn-group"><a href="{$order.view_url|escape:'htmlall':'UTF-8'}" class="btn btn-outline-secondary btn-sm" title="{l s='View' mod='wilden_manager'}"><i class="material-icons">zoom_in</i></a><button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button><div class="dropdown-menu dropdown-menu-right"><button type="button" class="dropdown-item wm-quick-view" data-order-id="{$order.id_order|intval}"><i class="material-icons">visibility</i> {l s='Quick view' mod='wilden_manager'}</button><a href="{$order.invoice_url|escape:'htmlall':'UTF-8'}" class="dropdown-item"><i class="material-icons">receipt</i> {l s='View invoice' mod='wilden_manager'}</a><a href="{$order.delivery_slip_url|escape:'htmlall':'UTF-8'}" class="dropdown-item"><i class="material-icons">local_shipping</i> {l s='View delivery slip' mod='wilden_manager'}</a></div></div></td>
        </tr>{foreachelse}<tr><td colspan="20" class="text-center text-muted wm-empty-grid">{l s='No orders match these filters.' mod='wilden_manager'}</td></tr>{/foreach}</tbody>
      </table></div>

      <div class="wm-grid-footer"><div class="wm-pagination"><label>{l s='Items per page' mod='wilden_manager'} <select name="limit" class="form-control" onchange="this.form.submit()">{foreach from=$wm_limits item=rowLimit}<option value="{$rowLimit|intval}"{if $wm_limit == $rowLimit} selected{/if}>{$rowLimit|intval}</option>{/foreach}</select></label><span>{l s='Page' mod='wilden_manager'} {$wm_page|intval} / {$wm_total_pages|intval}</span>{if $wm_page > 1}<a class="btn btn-outline-secondary btn-sm" href="{$wm_base_url|escape:'htmlall':'UTF-8'}&amp;{$wm_query|escape:'htmlall':'UTF-8'}&amp;page={$wm_page-1}"><i class="material-icons">chevron_left</i></a>{/if}{if $wm_page < $wm_total_pages}<a class="btn btn-outline-secondary btn-sm" href="{$wm_base_url|escape:'htmlall':'UTF-8'}&amp;{$wm_query|escape:'htmlall':'UTF-8'}&amp;page={$wm_page+1}"><i class="material-icons">chevron_right</i></a>{/if}</div>
        {if $wm_can_edit}<div class="wm-bulk-controls"><span class="wm-selected-count">0 {l s='selected' mod='wilden_manager'}</span><select name="bulk_state" class="form-control"><option value="0">{l s='Change order status' mod='wilden_manager'}</option>{foreach from=$wm_order_states item=state}<option value="{$state.id_order_state|intval}">{$state.name|escape:'htmlall':'UTF-8'}</option>{/foreach}</select><button type="submit" name="submitWmBulkPreview" value="1" class="btn btn-warning wm-bulk-preview-button"><i class="material-icons">preview</i> {l s='Preview' mod='wilden_manager'}</button></div>{/if}
      </div>
    </div>
  </form>

  <details class="card wm-audit-panel"><summary class="card-header"><i class="material-icons">history</i> {l s='Recent Wilden Manager activity' mod='wilden_manager'}</summary><div class="table-responsive"><table class="table"><thead><tr><th>{l s='Date' mod='wilden_manager'}</th><th>{l s='Employee' mod='wilden_manager'}</th><th>{l s='Order' mod='wilden_manager'}</th><th>{l s='Action' mod='wilden_manager'}</th></tr></thead><tbody>{foreach from=$wm_recent_audit item=audit}<tr><td>{$audit.date_add|escape:'htmlall':'UTF-8'}</td><td>{$audit.employee_name|escape:'htmlall':'UTF-8'}</td><td>{if $audit.id_order}#{$audit.id_order|intval}{else}—{/if}</td><td><code>{$audit.action|escape:'htmlall':'UTF-8'}</code></td></tr>{foreachelse}<tr><td colspan="4" class="text-muted text-center">{l s='No activity recorded yet.' mod='wilden_manager'}</td></tr>{/foreach}</tbody></table></div></details>
</div>

<div class="modal fade" id="wm-quick-view-modal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-lg" role="document"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">{l s='Order quick view' mod='wilden_manager'}</h4><button type="button" class="close" data-dismiss="modal">&times;</button></div><div class="modal-body"><div class="wm-loading"><i class="icon-refresh icon-spin"></i> {l s='Loading…' mod='wilden_manager'}</div></div></div></div></div>

<script>window.wildenManagerConfig={ajaxUrl:'{$wm_ajax_url|escape:'javascript':'UTF-8'}',maxBulk:{$wm_max_bulk|intval},loadingText:'{l s='Loading…' mod='wilden_manager' js=1}',errorText:'{l s='The order could not be loaded.' mod='wilden_manager' js=1}',selectOrderText:'{l s='Select at least one order.' mod='wilden_manager' js=1}',selectStateText:'{l s='Choose a new state.' mod='wilden_manager' js=1}',tooManyText:'{l s='Too many orders selected.' mod='wilden_manager' js=1}'};</script>
