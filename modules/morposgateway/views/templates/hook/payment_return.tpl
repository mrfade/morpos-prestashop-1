<section id="morposgateway-displayPaymentReturn" class="morpos-payment-return">
  <div class="alert alert-success morpos-alert morpos-success" role="alert">
    <div class="morpos-alert-wrapper morpos-success-wrapper">
      <div class="morpos-alert-icon morpos-success-icon">
        <i class="material-icons">check_circle</i>
      </div>
      <div class="morpos-alert-content morpos-success-content">
        <strong class="morpos-alert-title morpos-success-title">{l s='Payment Successful!' mod='morposgateway'}</strong>
        <p class="morpos-alert-message morpos-success-message">{l s='Your payment has been processed successfully.' mod='morposgateway'}</p>
        {if !empty($order_reference)}
          <p class="morpos-alert-text morpos-success-reference">{l s='Order reference:' mod='morposgateway'} <strong>{$order_reference|escape:'html':'UTF-8'}</strong></p>
        {/if}
        <p class="morpos-alert-text morpos-success-info">{l s='You will receive an order confirmation email shortly.' mod='morposgateway'}</p>
      </div>
    </div>
  </div>
</section>
