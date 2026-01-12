{**
 * MorPOS Payment Plugin - Modal Container Template
 * Modal is displayed after clicking Place Order for embedded payment
 *
 * @author Morpara
 * @copyright 2026 Morpara
 * @license MIT
 *}

{* Hidden data container for JavaScript *}
<div id="morpos-iframe-wrapper" 
     data-module-name="{$module_name|escape:'html':'UTF-8'}"
     data-form-type="{$form_type|escape:'html':'UTF-8'}"
     data-validate-url="{$validate_url|escape:'html':'UTF-8'}"
     style="display:none;">
</div>

<script>
// Translation variables for embedded payment modal
var title_payment_error = '{$title_payment_error|escape:'javascript':'UTF-8'}';
var title_system_error = '{$title_system_error|escape:'javascript':'UTF-8'}';
var title_network_error = '{$title_network_error|escape:'javascript':'UTF-8'}';
var title_payment_failed = '{$title_payment_failed|escape:'javascript':'UTF-8'}';
var text_payment_init_failed = '{$text_payment_init_failed|escape:'javascript':'UTF-8'}';
var text_unable_to_connect = '{$text_unable_to_connect|escape:'javascript':'UTF-8'}';
var text_check_connection = '{$text_check_connection|escape:'javascript':'UTF-8'}';
</script>

{* Payment info *}
<div class="morpos-payment-info">
  <p>{l s='Pay securely with your credit or debit card through MorPOS Payment Plugin.' mod='morposgateway'}</p>
  <p><small>{l s='A secure payment form will open for you to complete your payment.' mod='morposgateway'}</small></p>
</div>

{* Error message container - outside modal *}
<div id="morpos-error" class="alert alert-danger morpos-alert morpos-error" role="alert" style="display:none;">
  <div class="morpos-alert-wrapper morpos-error-wrapper">
    <div class="morpos-alert-icon morpos-error-icon">
      <i class="material-icons">error</i>
    </div>
    <div class="morpos-alert-content" id="morpos-error-content">
      <strong class="morpos-alert-title" id="morpos-error-title">{l s='Payment Error' mod='morposgateway'}</strong>
      <p class="morpos-alert-message" id="morpos-error-message"></p>
      <small class="morpos-alert-details" id="morpos-error-details" style="display:none;"></small>
    </div>
  </div>
</div>

{* Modal for embedded payment *}
<div id="morpos-modal">
  <div id="morpos-modal-content">
    <button id="morpos-close-btn" aria-label="Close">&times;</button>
    
    {* Loading state *}
    <div id="morpos-loading" class="morpos-loading" style="display:none;">
      <div class="morpos-spinner">
        <div class="spinner-border text-primary" role="status">
          <span class="sr-only">{l s='Processing...' mod='morposgateway'}</span>
        </div>
      </div>
      <p class="morpos-loading-text">{l s='Preparing secure payment form...' mod='morposgateway'}</p>
    </div>

    {* Iframe container *}
    <iframe id="morpos-iframe"></iframe>
  </div>
</div>
