{**
 * MorPOS Payment Plugin for PrestaShop
 * Order Detail Page Display (Customer Account)
 *
 * @author Morpara
 * @copyright 2026 Morpara
 * @license MIT
 *}

{if $paymentDetails.amount || $paymentDetails.card_number}
<section id="{$moduleName|escape:'html':'UTF-8'}-displayOrderDetail" class="box">
  <h4>
    <i class="material-icons" style="vertical-align: middle;">credit_card</i>
    {l s='Payment Details' mod='morposgateway'}
  </h4>
  
  <table class="table table-bordered">
    {if $paymentDetails.amount}
      <tr>
        <td class="text-muted" style="width: 40%;">{l s='Amount Paid' mod='morposgateway'}</td>
        <td><strong class="text-success">{$paymentDetails.amount|escape:'html':'UTF-8'}</strong></td>
      </tr>
    {/if}
    
    {if $paymentDetails.card_number}
      <tr>
        <td class="text-muted">{l s='Payment Method' mod='morposgateway'}</td>
        <td>
          <i class="material-icons" style="font-size: 16px; vertical-align: middle;">credit_card</i>
          {$paymentDetails.card_number|escape:'html':'UTF-8'}
        </td>
      </tr>
    {/if}
    
    {if $paymentDetails.installments && $paymentDetails.installments > 1}
      <tr>
        <td class="text-muted">{l s='Installments' mod='morposgateway'}</td>
        <td><span class="badge badge-info">{$paymentDetails.installments|escape:'html':'UTF-8'}x</span></td>
      </tr>
    {/if}
    
    {if $paymentDetails.date}
      <tr>
        <td class="text-muted">{l s='Payment Date' mod='morposgateway'}</td>
        <td>{$paymentDetails.date|escape:'html':'UTF-8'}</td>
      </tr>
    {/if}
  </table>
</section>
{/if}
