{**
 * MorPOS Payment Plugin for PrestaShop
 * Order Detail Page Display (Customer Account)
 *
 * @author Morpara
 * @copyright 2026 Morpara
 * @license MIT
 *}

{if $paymentDetails.payment_id || $paymentDetails.conversation_id}
<section id="{$moduleName|escape:'html':'UTF-8'}-displayOrderDetail" class="box">
  <h4>
    <i class="material-icons" style="vertical-align: middle;">credit_card</i>
    {l s='Payment Details' mod='morposgateway'}
  </h4>
  
  <table class="table table-bordered">
    {if $paymentDetails.payment_id}
      <tr>
        <td class="text-muted" style="width: 40%;">{l s='Transaction ID' mod='morposgateway'}</td>
        <td><strong>{$paymentDetails.payment_id|escape:'html':'UTF-8'}</strong></td>
      </tr>
    {/if}
    
    {if $paymentDetails.conversation_id}
      <tr>
        <td class="text-muted">{l s='Reference' mod='morposgateway'}</td>
        <td><strong>{$paymentDetails.conversation_id|escape:'html':'UTF-8'}</strong></td>
      </tr>
    {/if}
    
    {if $paymentDetails.bank_reference}
      <tr>
        <td class="text-muted">{l s='Bank Reference' mod='morposgateway'}</td>
        <td>{$paymentDetails.bank_reference|escape:'html':'UTF-8'}</td>
      </tr>
    {/if}
    
    {if $paymentDetails.card_number}
      <tr>
        <td class="text-muted">{l s='Card' mod='morposgateway'}</td>
        <td>{$paymentDetails.card_number|escape:'html':'UTF-8'}</td>
      </tr>
    {/if}
    
    {if $paymentDetails.amount}
      <tr>
        <td class="text-muted">{l s='Amount' mod='morposgateway'}</td>
        <td><strong>{$paymentDetails.amount|escape:'html':'UTF-8'}</strong></td>
      </tr>
    {/if}
    
    {if $paymentDetails.date}
      <tr>
        <td class="text-muted">{l s='Date' mod='morposgateway'}</td>
        <td>{$paymentDetails.date|escape:'html':'UTF-8'}</td>
      </tr>
    {/if}
  </table>
</section>
{/if}
