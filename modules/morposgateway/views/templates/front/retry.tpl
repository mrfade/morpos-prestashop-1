{extends file='page.tpl'}

{block name='page_title'}
  {l s='Retry Payment' mod='morposgateway'}
{/block}

{block name='page_content'}
<link href="{$module_dir|escape:'html':'UTF-8'}views/css/morpos.css" rel="stylesheet" type="text/css"/>

<section id="morposgateway-retry" class="morpos-retry-page">
  <div class="morpos-retry-container">
    {if isset($error_message) && $error_message}
      <div class="alert alert-danger morpos-alert morpos-error" role="alert">
        <div class="morpos-alert-wrapper morpos-error-wrapper">
          <div class="morpos-alert-icon morpos-error-icon">
            <i class="material-icons">error</i>
          </div>
          <div class="morpos-alert-content morpos-error-content-retry">
            <strong class="morpos-alert-title morpos-error-title-retry">{l s='Payment Failed' mod='morposgateway'}</strong>
            <p class="morpos-alert-message morpos-error-message-retry"><strong>{$error_message|escape:'html':'UTF-8'}</strong></p>
            <p class="morpos-alert-text morpos-error-text-retry">{l s='Please try again or contact support if the problem persists.' mod='morposgateway'}</p>
          </div>
        </div>
      </div>
    {/if}

    <div class="morpos-retry-card">
      <div class="morpos-retry-header">
        <i class="material-icons">payment</i>
        <h3>{l s='Retry Payment' mod='morposgateway'}</h3>
      </div>
      
      <div class="morpos-retry-body">
        <div class="morpos-order-info">
          <div class="morpos-info-row">
            <span class="morpos-info-label">{l s='Order Reference' mod='morposgateway'}</span>
            <span class="morpos-info-value">{$order_reference|escape:'html':'UTF-8'}</span>
          </div>
          <div class="morpos-info-row morpos-info-amount">
            <span class="morpos-info-label">{l s='Amount to Pay' mod='morposgateway'}</span>
            <span class="morpos-info-value">{$total nofilter}</span>
          </div>
        </div>
        
        <p class="morpos-retry-message">{l s='Your previous payment attempt was not successful. Click the button below to try again.' mod='morposgateway'}</p>
        
        <button id="button-confirm" class="btn btn-primary btn-lg morpos-retry-button" type="button">
          <i class="material-icons">credit_card</i>
          {$button_text|escape:'html':'UTF-8'}
        </button>

        {* Simple alternative links *}
        <div class="morpos-alternative-links">
          <p class="morpos-alternative-text">{l s='Need to use a different payment method?' mod='morposgateway'}</p>
          <p class="morpos-alternative-hint">
            {l s='You can view your' mod='morposgateway'} 
            <a href="{$order_detail_url|escape:'html':'UTF-8'}">{l s='order details' mod='morposgateway'}</a> 
            {l s='and use "Reorder" to create a new order, or' mod='morposgateway'} 
            <a href="{$shop_url|escape:'html':'UTF-8'}">{l s='return to shop' mod='morposgateway'}</a>.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

{if $form_type === 'embedded'}
<!-- Modal for embedded payment -->
<div id="morpos-modal">
  <div id="morpos-modal-content">
    <button id="morpos-close-btn">×</button>
    <iframe id="morpos-iframe"></iframe>
  </div>
</div>

<script>
var confirm_url = {$confirm_url|json_encode nofilter};
var redirect_success = {$redirect_success|json_encode nofilter};
var text_payment_failed_default = {$text_payment_failed_default|json_encode nofilter};
var text_payment_init_failed = {$text_payment_init_failed|json_encode nofilter};
var text_network_error = {$text_network_error|json_encode nofilter};
</script>
<script src="{$module_dir|escape:'html':'UTF-8'}views/js/morpos.js"></script>
{else}
<script>
// For hosted payment, redirect directly on button click
document.addEventListener('DOMContentLoaded', function() {
  var btn = document.getElementById('button-confirm');
  if (btn) {
    btn.addEventListener('click', function() {
      btn.disabled = true;
      btn.classList.add('btn-loading');
      window.location.href = {$confirm_url|json_encode nofilter};
    });
  }
});
</script>
{/if}
{/block}
