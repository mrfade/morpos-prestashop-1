{**
 * MorPOS Payment Plugin for PrestaShop
 * Admin Order Main Bottom Display (PS >= 1.7.7)
 *
 * @author Morpara
 * @copyright 2026 Morpara
 * @license MIT
 *}

<section id="{$moduleName|escape:'html':'UTF-8'}-displayAdminOrderMainBottom">
  <div class="card mt-2">
    <div class="card-header">
      <h3 class="card-header-title">
        <img src="{$moduleLogoSrc|escape:'html':'UTF-8'}" alt="{$moduleDisplayName|escape:'html':'UTF-8'}" width="20" height="20" class="mr-1">
        {$moduleDisplayName|escape:'html':'UTF-8'} - {l s='Payment Details' mod='morposgateway'}
      </h3>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          {if $paymentDetails.payment_id}
            <div class="mb-2">
              <span class="text-muted">{l s='Payment ID:' mod='morposgateway'}</span><br>
              <strong class="text-primary">{$paymentDetails.payment_id|escape:'html':'UTF-8'}</strong>
            </div>
          {/if}
          
          {if $paymentDetails.conversation_id}
            <div class="mb-2">
              <span class="text-muted">{l s='Conversation ID:' mod='morposgateway'}</span><br>
              <strong>{$paymentDetails.conversation_id|escape:'html':'UTF-8'}</strong>
            </div>
          {/if}
          
          {if $paymentDetails.bank_reference}
            <div class="mb-2">
              <span class="text-muted">{l s='Bank Reference:' mod='morposgateway'}</span><br>
              <strong>{$paymentDetails.bank_reference|escape:'html':'UTF-8'}</strong>
            </div>
          {/if}
        </div>
        
        <div class="col-md-6">
          {if $paymentDetails.card_number}
            <div class="mb-2">
              <span class="text-muted">{l s='Card:' mod='morposgateway'}</span><br>
              <strong>{$paymentDetails.card_number|escape:'html':'UTF-8'}</strong>
            </div>
          {/if}
          
          {if $paymentDetails.amount}
            <div class="mb-2">
              <span class="text-muted">{l s='Amount:' mod='morposgateway'}</span><br>
              <strong class="text-success">{$paymentDetails.amount|escape:'html':'UTF-8'}</strong>
            </div>
          {/if}
          
          {if $paymentDetails.date}
            <div class="mb-2">
              <span class="text-muted">{l s='Payment Date:' mod='morposgateway'}</span><br>
              {$paymentDetails.date|escape:'html':'UTF-8'}
            </div>
          {/if}
        </div>
      </div>
      
      {if $paymentDetails.transaction_id}
        <hr>
        <div class="alert alert-light mb-0" role="alert">
          <small class="text-muted">{l s='Full Transaction Reference:' mod='morposgateway'}</small><br>
          <code class="d-block mt-1" style="font-size: 12px; word-break: break-all;">{$paymentDetails.transaction_id|escape:'html':'UTF-8'}</code>
        </div>
      {/if}
    </div>
  </div>
</section>
