{extends file='page.tpl'}

{block name='page_title'}
  {l s='Payment' mod='morposgateway'}
{/block}

{block name='page_content'}
<link href="{$module_dir|escape:'html':'UTF-8'}views/css/morpos.css" rel="stylesheet" type="text/css"/>

<section id="morposgateway-payment" class="morpos-payment-page">
  {if isset($error_message) && $error_message}
    <div class="alert alert-danger" role="alert">
      <p class="warning">
        <i class="material-icons">error</i>
        {l s='Payment Failed' mod='morposgateway'}
      </p>
      <p><strong>{$error_message|escape:'html':'UTF-8'}</strong></p>
      <p>{l s='Please try again or contact support if the problem persists.' mod='morposgateway'}</p>
    </div>
  {/if}

  {if isset($is_retry) && $is_retry}
    <div class="alert alert-info" role="alert">
      <p class="label">{l s='Order Reference:' mod='morposgateway'} <strong>{$order_reference|escape:'html':'UTF-8'}</strong></p>
      <p class="label">{l s='Amount to Pay:' mod='morposgateway'} <strong>{$total nofilter}</strong></p>
    </div>
  {/if}

  <div class="card card-block">
    <div class="card-body">
      <p>{l s='You are about to pay with credit or debit card.' mod='morposgateway'}</p>
      <p>{l s='Please click the button below to proceed to the secure payment page.' mod='morposgateway'}</p>
      
      <form action="#" method="post" class="form-horizontal mt-4">
        <div class="text-center">
          <button id="button-confirm" class="btn btn-primary btn-lg" type="button">
            {$button_confirm|escape:'html':'UTF-8'}
          </button>
        </div>
      </form>
    </div>
  </div>
</section>

<!-- Modal for embedded payment -->
<div id="morpos-modal">
  <div id="morpos-modal-content">
    <button id="morpos-close-btn">×</button>
    <iframe id="morpos-iframe"></iframe>
  </div>
</div>

<script>
var confirm_url = '{$confirm_url|escape:'javascript':'UTF-8'}';
var redirect_success = '{$redirect_success|escape:'javascript':'UTF-8'}';
var text_payment_failed_default = '{$text_payment_failed_default|escape:'javascript':'UTF-8'}';
var text_payment_init_failed = '{$text_payment_init_failed|escape:'javascript':'UTF-8'}';
var text_network_error = '{$text_network_error|escape:'javascript':'UTF-8'}';
var title_payment_error = '{$title_payment_error|escape:'javascript':'UTF-8'}';
var title_system_error = '{$title_system_error|escape:'javascript':'UTF-8'}';
var title_network_error = '{$title_network_error|escape:'javascript':'UTF-8'}';
var title_payment_failed = '{$title_payment_failed|escape:'javascript':'UTF-8'}';
var text_unable_to_connect = '{$text_unable_to_connect|escape:'javascript':'UTF-8'}';
var text_check_connection = '{$text_check_connection|escape:'javascript':'UTF-8'}';
</script>
<script src="{$module_dir|escape:'html':'UTF-8'}views/js/morpos.js"></script>
{/block}
