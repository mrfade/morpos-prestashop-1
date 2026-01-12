{**
 * MorPOS Payment Plugin for PrestaShop
 * Admin Order Left Panel Display (PS < 1.7.7)
 *
 * @author Morpara
 * @copyright 2026 Morpara
 * @license MIT
 *}

<section id="{$moduleName|escape:'html':'UTF-8'}-displayAdminOrderLeft">
  <div class="panel">
    <div class="panel-heading">
      <img src="{$moduleLogoSrc|escape:'html':'UTF-8'}" alt="{$moduleDisplayName|escape:'html':'UTF-8'}" width="15" height="15">
      {$moduleDisplayName|escape:'html':'UTF-8'}
    </div>
    <div class="panel-body">
      <p><strong>{l s='Payment processed via MorPOS Gateway' mod='morposgateway'}</strong></p>
      
      {if $paymentDetails.payment_id}
        <p>
          <span class="text-muted">{l s='Payment ID:' mod='morposgateway'}</span>
          <strong>{$paymentDetails.payment_id|escape:'html':'UTF-8'}</strong>
        </p>
      {/if}
      
      {if $paymentDetails.conversation_id}
        <p>
          <span class="text-muted">{l s='Conversation ID:' mod='morposgateway'}</span>
          <strong>{$paymentDetails.conversation_id|escape:'html':'UTF-8'}</strong>
        </p>
      {/if}
      
      {if $paymentDetails.bank_reference}
        <p>
          <span class="text-muted">{l s='Bank Reference:' mod='morposgateway'}</span>
          <strong>{$paymentDetails.bank_reference|escape:'html':'UTF-8'}</strong>
        </p>
      {/if}
      
      {if $paymentDetails.card_number}
        <p>
          <span class="text-muted">{l s='Card Number:' mod='morposgateway'}</span>
          <strong>{$paymentDetails.card_number|escape:'html':'UTF-8'}</strong>
        </p>
      {/if}
      
      {if $paymentDetails.amount}
        <p>
          <span class="text-muted">{l s='Amount:' mod='morposgateway'}</span>
          <strong>{$paymentDetails.amount|escape:'html':'UTF-8'}</strong>
        </p>
      {/if}
      
      {if $paymentDetails.date}
        <p>
          <span class="text-muted">{l s='Date:' mod='morposgateway'}</span>
          {$paymentDetails.date|escape:'html':'UTF-8'}
        </p>
      {/if}
      
      {if $paymentDetails.transaction_id}
        <hr>
        <p class="small text-muted">
          {l s='Full Transaction Reference:' mod='morposgateway'}<br>
          <code style="font-size: 11px; word-break: break-all;">{$paymentDetails.transaction_id|escape:'html':'UTF-8'}</code>
        </p>
      {/if}
    </div>
  </div>
</section>
