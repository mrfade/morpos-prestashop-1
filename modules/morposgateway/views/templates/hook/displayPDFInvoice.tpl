{**
 * MorPOS Payment Plugin for PrestaShop
 * PDF Invoice Display
 *
 * @author Morpara
 * @copyright 2026 Morpara
 * @license MIT
 *}

{if $paymentDetails.payment_id || $paymentDetails.conversation_id}
<table style="width: 100%; margin-top: 10px; font-size: 9pt;">
  <tr>
    <td style="padding: 5px; background-color: #f5f5f5; border: 1px solid #ddd;">
      <strong>{l s='MorPOS Payment Details' mod='morposgateway'}</strong>
    </td>
  </tr>
  <tr>
    <td style="padding: 8px; border: 1px solid #ddd; border-top: none;">
      {if $paymentDetails.payment_id}
        <strong>{l s='Transaction ID:' mod='morposgateway'}</strong> {$paymentDetails.payment_id|escape:'html':'UTF-8'}<br>
      {/if}
      {if $paymentDetails.conversation_id}
        <strong>{l s='Reference:' mod='morposgateway'}</strong> {$paymentDetails.conversation_id|escape:'html':'UTF-8'}<br>
      {/if}
      {if $paymentDetails.bank_reference}
        <strong>{l s='Bank Ref:' mod='morposgateway'}</strong> {$paymentDetails.bank_reference|escape:'html':'UTF-8'}<br>
      {/if}
      {if $paymentDetails.card_number}
        <strong>{l s='Card:' mod='morposgateway'}</strong> {$paymentDetails.card_number|escape:'html':'UTF-8'}
      {/if}
    </td>
  </tr>
</table>
{/if}
