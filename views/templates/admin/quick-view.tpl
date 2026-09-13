{*
 * Wilden Manager
 * Copyright 2026 Wilden Militaria S.L.
 *}

<div class="wm-quick-view-content">
  <div class="wm-qv-header">
    <div><strong>#{$wm_qv_order->id|intval}</strong> · {$wm_qv_order->reference|escape:'htmlall':'UTF-8'}</div>
    <div><strong>{$wm_qv_total|escape:'htmlall':'UTF-8'}</strong></div>
  </div>

  <div class="row">
    <div class="col-md-4">
      <h4>{l s='Customer' mod='wilden_manager'}</h4>
      <p>{$wm_qv_customer->firstname|escape:'htmlall':'UTF-8'} {$wm_qv_customer->lastname|escape:'htmlall':'UTF-8'}<br><a href="mailto:{$wm_qv_customer->email|escape:'htmlall':'UTF-8'}">{$wm_qv_customer->email|escape:'htmlall':'UTF-8'}</a></p>
    </div>
    <div class="col-md-4"><h4>{l s='Delivery address' mod='wilden_manager'}</h4><p>{$wm_qv_delivery_address|escape:'htmlall':'UTF-8'|nl2br nofilter}</p></div>
    <div class="col-md-4"><h4>{l s='Invoice address' mod='wilden_manager'}</h4><p>{$wm_qv_invoice_address|escape:'htmlall':'UTF-8'|nl2br nofilter}</p></div>
  </div>

  <h4>{l s='Products' mod='wilden_manager'}</h4>
  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>{l s='Product' mod='wilden_manager'}</th><th>{l s='Reference' mod='wilden_manager'}</th><th class="text-center">{l s='Quantity' mod='wilden_manager'}</th><th class="text-right">{l s='Unit price' mod='wilden_manager'}</th><th class="text-right">{l s='Total' mod='wilden_manager'}</th></tr></thead>
      <tbody>
      {foreach from=$wm_qv_products item=product}
        <tr>
          <td>{$product.product_name|escape:'htmlall':'UTF-8'}</td>
          <td>{$product.product_reference|escape:'htmlall':'UTF-8'}</td>
          <td class="text-center">{$product.product_quantity|intval}</td>
          <td class="text-right">{$product.formatted_unit_price|escape:'htmlall':'UTF-8'}</td>
          <td class="text-right">{$product.formatted_total_price|escape:'htmlall':'UTF-8'}</td>
        </tr>
      {/foreach}
      </tbody>
    </table>
  </div>

  <div class="row">
    <div class="col-md-6">
      <h4>{l s='Status history' mod='wilden_manager'}</h4>
      <ul class="wm-history">
        {foreach from=$wm_qv_history item=history}
          <li><span>{$history.date_add|escape:'htmlall':'UTF-8'}</span> {$history.ostate_name|escape:'htmlall':'UTF-8'}</li>
        {/foreach}
      </ul>
    </div>
    <div class="col-md-6">
      <h4>{l s='Internal note' mod='wilden_manager'}</h4>
      {if $wm_can_edit}
        <form method="post" action="">
          <input type="hidden" name="id_order" value="{$wm_qv_order->id|intval}">
          <textarea name="wm_note" maxlength="5000" rows="6" class="form-control">{$wm_qv_note|escape:'htmlall':'UTF-8'}</textarea>
          <button type="submit" name="submitWmSaveNote" value="1" class="btn btn-primary wm-save-note"><i class="icon-save"></i> {l s='Save note' mod='wilden_manager'}</button>
        </form>
      {else}
        <p class="well">{$wm_qv_note|escape:'htmlall':'UTF-8'|nl2br nofilter}</p>
      {/if}
    </div>
  </div>

  <div class="wm-qv-footer"><a href="{$wm_qv_view_url|escape:'htmlall':'UTF-8'}" class="btn btn-default"><i class="icon-external-link"></i> {l s='Open full order' mod='wilden_manager'}</a></div>
</div>
