{extends file='page.tpl'}

{block name='page_title'}
  {l s='Payment' mod='morposgateway'}
{/block}

{block name='page_content'}
<link href="{$module_dir|escape:'html':'UTF-8'}views/css/morpos.css" rel="stylesheet" type="text/css"/>

<section id="morposgateway-retry-error" class="morpos-retry-error-section">
  <div class="alert alert-info morpos-alert morpos-info">
    <div class="morpos-alert-wrapper">
      <div class="morpos-alert-icon">
        <i class="material-icons">info</i>
      </div>
      <div class="morpos-alert-content">
        <strong class="morpos-alert-title">{l s='Payment Not Available' mod='morposgateway'}</strong>
        <p class="morpos-alert-message">{l s='You can only make payments for orders that are awaiting payment. This order is no longer in a pending payment status.' mod='morposgateway'}</p>
      </div>
    </div>
  </div>
  
  <div class="morpos-retry-error-actions">
    <a href="{$link->getPageLink('history', true)|escape:'html':'UTF-8'}" class="btn btn-primary">
      {l s='Return to Order History' mod='morposgateway'}
    </a>
  </div>
</section>
{/block}
