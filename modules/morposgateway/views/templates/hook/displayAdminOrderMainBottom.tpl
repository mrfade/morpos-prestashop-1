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
    <div class="card-header d-flex align-items-center">
      <img src="{$moduleLogoSrc|escape:'html':'UTF-8'}" alt="{$moduleDisplayName|escape:'html':'UTF-8'}" width="20" height="20" class="mr-2">
      <h3 class="card-header-title mb-0">{l s='Payment Details' mod='morposgateway'}</h3>
    </div>
    <div class="card-body p-3">
      {if $paymentDetails.payment_id || $paymentDetails.amount}
        <div class="d-flex flex-wrap" style="gap: 1.5rem;">
          {* Amount - Primary Info *}
          {if $paymentDetails.amount}
            <div class="flex-grow-1" style="min-width: 120px;">
              <small class="text-muted d-block mb-1">{l s='Amount' mod='morposgateway'}</small>
              <span class="h5 mb-0 text-success font-weight-bold">{$paymentDetails.amount|escape:'html':'UTF-8'}</span>
            </div>
          {/if}

          {* Card *}
          {if $paymentDetails.card_number}
            <div style="min-width: 140px;">
              <small class="text-muted d-block mb-1">{l s='Card' mod='morposgateway'}</small>
              <span class="font-weight-medium">
                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">credit_card</i>
                {$paymentDetails.card_number|escape:'html':'UTF-8'}
              </span>
            </div>
          {/if}

          {* Installments *}
          {if $paymentDetails.installments && $paymentDetails.installments > 1}
            <div style="min-width: 80px;">
              <small class="text-muted d-block mb-1">{l s='Installments' mod='morposgateway'}</small>
              <span class="badge badge-info">{$paymentDetails.installments|escape:'html':'UTF-8'}x</span>
            </div>
          {/if}

          {* Date *}
          {if $paymentDetails.date}
            <div style="min-width: 150px;">
              <small class="text-muted d-block mb-1">{l s='Date' mod='morposgateway'}</small>
              <span>{$paymentDetails.date|escape:'html':'UTF-8'}</span>
            </div>
          {/if}
        </div>

        {* Transaction IDs - Collapsible Details *}
        {if $paymentDetails.payment_id || $paymentDetails.conversation_id || $paymentDetails.bank_reference}
          <hr class="my-3">
          <div class="small">
            <div class="row" style="row-gap: 0.5rem;">
              {if $paymentDetails.payment_id}
                <div class="col-md-4">
                  <span class="text-muted">{l s='Payment ID:' mod='morposgateway'}</span>
                  <code class="ml-1 text-primary">{$paymentDetails.payment_id|escape:'html':'UTF-8'}</code>
                </div>
              {/if}
              {if $paymentDetails.conversation_id}
                <div class="col-md-4">
                  <span class="text-muted">{l s='Conversation ID:' mod='morposgateway'}</span>
                  <code class="ml-1">{$paymentDetails.conversation_id|escape:'html':'UTF-8'}</code>
                </div>
              {/if}
              {if $paymentDetails.bank_reference}
                <div class="col-md-4">
                  <span class="text-muted">{l s='Bank Ref:' mod='morposgateway'}</span>
                  <code class="ml-1">{$paymentDetails.bank_reference|escape:'html':'UTF-8'}</code>
                </div>
              {/if}
            </div>
          </div>
        {/if}
      {else}
        <p class="text-muted mb-0">
          <i class="material-icons" style="font-size: 16px; vertical-align: middle;">info</i>
          {l s='Payment details not available yet.' mod='morposgateway'}
        </p>
      {/if}
    </div>
  </div>
</section>
