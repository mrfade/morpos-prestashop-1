{**
 * MorPOS Payment Plugin for PrestaShop
 * Order Confirmation Page Display
 *
 * @author Morpara
 * @copyright 2026 Morpara
 * @license MIT
 *}

{if $paymentDetails.payment_id || $paymentDetails.conversation_id}
<section id="{$moduleName|escape:'html':'UTF-8'}-displayOrderConfirmation" class="card mb-3">
  <div class="card-block">
    <h4 class="card-title h5">
      <i class="material-icons" style="vertical-align: middle;">payment</i>
      {l s='Payment Information' mod='morposgateway'}
    </h4>
    
    <div class="row">
      {if $paymentDetails.payment_id}
        <div class="col-md-6 col-sm-12 mb-2">
          <span class="text-muted">{l s='Transaction ID:' mod='morposgateway'}</span><br>
          <strong>{$paymentDetails.payment_id|escape:'html':'UTF-8'}</strong>
        </div>
      {/if}
      
      {if $paymentDetails.conversation_id}
        <div class="col-md-6 col-sm-12 mb-2">
          <span class="text-muted">{l s='Reference:' mod='morposgateway'}</span><br>
          <strong>{$paymentDetails.conversation_id|escape:'html':'UTF-8'}</strong>
        </div>
      {/if}
      
      {if $paymentDetails.card_number}
        <div class="col-md-6 col-sm-12 mb-2">
          <span class="text-muted">{l s='Card:' mod='morposgateway'}</span><br>
          <strong>{$paymentDetails.card_number|escape:'html':'UTF-8'}</strong>
        </div>
      {/if}
      
      {if $paymentDetails.bank_reference}
        <div class="col-md-6 col-sm-12 mb-2">
          <span class="text-muted">{l s='Bank Reference:' mod='morposgateway'}</span><br>
          <strong>{$paymentDetails.bank_reference|escape:'html':'UTF-8'}</strong>
        </div>
      {/if}
    </div>
    
    <p class="card-text small text-muted mt-2">
      <i class="material-icons" style="font-size: 14px; vertical-align: middle;">info</i>
      {l s='Please save these details for your records. You may need them for any future inquiries.' mod='morposgateway'}
    </p>
  </div>
</section>
{/if}
