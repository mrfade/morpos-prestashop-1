<link rel="stylesheet" href="{$module_dir|escape:'html':'UTF-8'}views/css/morpos-admin.css">
<link rel="stylesheet" href="{$module_dir|escape:'html':'UTF-8'}views/css/morpos-toast.css">
<script src="{$module_dir|escape:'html':'UTF-8'}views/js/morpos-toast.js"></script>

<div class="morpos-settings">
    <div class="morpos-header">
        <div class="header-left">
            <h2 class="morpos-h2">{l s='MorPOS Payment Plugin' mod='morposgateway'}</h2>
            <p class="morpos-desc">{l s='MorPOS PrestaShop is a virtual POS plugin specially developed for e-commerce websites built on the PrestaShop infrastructure. With this plugin, you can easily accept payments from your customers via credit or debit card — either in full or in installments — and integrate seamlessly with all banks. It is easy to install and requires no technical knowledge.' mod='morposgateway'}</p>
            <p class="morpos-version">{l s='Version' mod='morposgateway'}: {$module_version|escape:'html':'UTF-8'}</p>
        </div>
        <div class="header-right">
            <img class="morpos-logo" src="{$module_dir|escape:'html':'UTF-8'}views/img/morpos-logo.png" alt="MorPOS"/>
        </div>
    </div>

    <div class="morpos-connection">
        <span class="pill pill-setup">{l s='Setup' mod='morposgateway'}</span>
        <span class="pill pill-ok">{l s='Connection Successful' mod='morposgateway'}</span>
        <span class="pill pill-fail">{l s='Connection Failed' mod='morposgateway'}</span>
        <button type="button" class="button button-primary morpos-test-btn">{l s='Test Connection' mod='morposgateway'}</button>
    </div>

    <form id="form-payment" class="form" action="{$form_action|escape:'html':'UTF-8'}" method="post">
        <div class="field-row">
            <div class="label">{l s='Status' mod='morposgateway'}</div>
            <div class="field">
                <label class="cbx">
                    <input type="checkbox" id="enabled" name="MORPOS_ENABLED" value="1" {if $enabled}checked{/if}>
                    <span class="cbx__box" aria-hidden="true">
                        <svg class="cbx__check" viewBox="0 0 24 24" width="16" height="16">
                            <path d="M5 12.5l4 4L19 7.5"></path>
                        </svg>
                    </span>
                    <span class="cbx__label">{l s='Enable' mod='morposgateway'}</span>
                </label>
            </div>
        </div>

        <div class="field-row">
            <div class="label">{l s='Test Mode' mod='morposgateway'}</div>
            <div class="field">
                <label class="cbx cbx--warn">
                    <input type="checkbox" id="testmode" name="MORPOS_TESTMODE" value="1" {if $testmode}checked{/if}>
                    <span class="cbx__box" aria-hidden="true">
                        <svg class="cbx__check" viewBox="0 0 24 24" width="16" height="16">
                            <path d="M5 12.5l4 4L19 7.5"></path>
                        </svg>
                    </span>
                    <span class="cbx__label">{l s='Enable Test Mode' mod='morposgateway'}</span>
                    <span class="cbx__hint">{l s='For testing purposes only' mod='morposgateway'}</span>
                </label>
            </div>
        </div>

        <div class="field-row">
            <label class="label" for="merchant_id">{l s='Merchant ID' mod='morposgateway'}</label>
            <div class="field">
                <input id="merchant_id" type="text" placeholder="{l s='Your MorPOS Merchant ID' mod='morposgateway'}" name="MORPOS_MERCHANT_ID" value="{$merchant_id|escape:'html':'UTF-8'}" autocomplete="off" required>
            </div>
        </div>

        <div class="field-row">
            <label class="label" for="client_id">{l s='Client ID' mod='morposgateway'}</label>
            <div class="field">
                <input id="client_id" type="text" placeholder="{l s='Your MorPOS Client ID' mod='morposgateway'}" name="MORPOS_CLIENT_ID" value="{$client_id|escape:'html':'UTF-8'}" autocomplete="off" required>
            </div>
        </div>

        <div class="field-row">
            <label class="label" for="client_secret">{l s='Client Secret' mod='morposgateway'}</label>
            <div class="field">
                <input id="client_secret" type="password" placeholder="{l s='Your MorPOS Client Secret' mod='morposgateway'}" name="MORPOS_CLIENT_SECRET" value="{$client_secret|escape:'html':'UTF-8'}" autocomplete="off" required>
            </div>
        </div>

        <div class="field-row">
            <label class="label" for="api_key">{l s='API Key' mod='morposgateway'}</label>
            <div class="field">
                <input id="api_key" type="password" placeholder="{l s='Your MorPOS API Key' mod='morposgateway'}" name="MORPOS_API_KEY" value="{$api_key|escape:'html':'UTF-8'}" autocomplete="off" required>
            </div>
        </div>

        <div class="field-row">
            <div class="label" for="form_type">{l s='Payment Form Type' mod='morposgateway'}</div>
            <div class="field select">
                <select id="form_type" name="MORPOS_FORM_TYPE">
                    <option value="hosted" {if $form_type == 'hosted'}selected{/if}>{l s='Hosted Payment' mod='morposgateway'}</option>
                    <option value="embedded" {if $form_type == 'embedded'}selected{/if}>{l s='Embedded Payment' mod='morposgateway'}</option>
                </select>
            </div>
        </div>

        <div class="field-row">
            <div class="label" for="success_status">{l s='Success Order Status' mod='morposgateway'}</div>
            <div class="field select">
                <select id="success_status" name="MORPOS_SUCCESS_STATUS">
                    {foreach from=$order_statuses item=status}
                        <option value="{$status.id_order_state}" {if $success_status == $status.id_order_state}selected{/if}>
                            {$status.name|escape:'html':'UTF-8'}
                        </option>
                    {/foreach}
                </select>
            </div>
        </div>

        <div class="field-row">
            <div class="label" for="failed_status">{l s='Failed Order Status' mod='morposgateway'}</div>
            <div class="field select">
                <select id="failed_status" name="MORPOS_FAILED_STATUS">
                    {foreach from=$order_statuses item=status}
                        <option value="{$status.id_order_state}" {if $failed_status == $status.id_order_state}selected{/if}>
                            {$status.name|escape:'html':'UTF-8'}
                        </option>
                    {/foreach}
                </select>
            </div>
        </div>

        <input type="hidden" name="MORPOS_CONNECTION_STATUS" value="{$connection_status|escape:'html':'UTF-8'}">

        <div class="actions">
            <button class="button" type="submit" name="{$submit_action|escape:'html':'UTF-8'}">{l s='Save Changes' mod='morposgateway'}</button>
        </div>
    </form>

    <div class="morpos-requirements">
        <h2>{l s='System Requirements' mod='morposgateway'}</h2>
        <p>{l s='The table below shows the current state of your server environment. Please ensure all requirements are met for optimal performance.' mod='morposgateway'}</p>
        <div class="morpos-reqs">
            <div class="morpos-reqs__head">{l s='Requirements' mod='morposgateway'}</div>
            <table>
                <thead>
                    <tr>
                        <th>{l s='Component' mod='morposgateway'}</th>
                        <th class="col-current">{l s='Current' mod='morposgateway'}</th>
                        <th>{l s='Recommended' mod='morposgateway'}</th>
                        <th>{l s='Required' mod='morposgateway'}</th>
                        <th class="col-status">{l s='Status' mod='morposgateway'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$requirements item=req}
                        <tr>
                            <td>{$req.label|escape:'html':'UTF-8'}</td>
                            <td>{$req.cur|escape:'html':'UTF-8'}</td>
                            <td>{$req.rec|escape:'html':'UTF-8'}</td>
                            <td>{$req.req|escape:'html':'UTF-8'}</td>
                            <td>
                                <span class="morpos-badge {$req.status.class|escape:'html':'UTF-8'}">
                                    {if $req.status.class == 'morpos-ok'}
                                        &#x2714;
                                    {elseif $req.status.class == 'morpos-warning'}
                                        &#9888;&#xfe0f;
                                    {elseif $req.status.class == 'morpos-danger'}
                                        &#10060;
                                    {/if}
                                    {$req.status.hint|escape:'html':'UTF-8'}
                                </span>

                                {if $req.status.class == 'morpos-warning' && $req.label == 'PHP'}
                                    <span class="morpos-hint">{l s='Available, but please upgrade for better performance and security.' mod='morposgateway'}</span>
                                {/if}
                                {if $req.status.class == 'morpos-danger' && $req.label == 'TLS'}
                                    <span class="morpos-hint">{l s='Payments cannot be processed until the server supports TLS 1.2 or newer.' mod='morposgateway'}</span>
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function setStatus(s) { // 'setup' | 'ok' | 'fail'
    $('.morpos-connection .pill').css('opacity', .35);
    if (s === 'ok') {
        $('.pill-ok').css('opacity', 1);
    } else if (s === 'fail') {
        $('.pill-fail').css('opacity', 1);
    } else {
        $('.pill-setup').css('opacity', 1);
    }

    $('[name="MORPOS_CONNECTION_STATUS"]').val(s);
}

setStatus('{$connection_status|escape:'html':'UTF-8'}' || 'setup');

var morposAjaxUrl = '{$ajax_url|escape:'javascript':'UTF-8'}';

document.querySelector('.morpos-test-btn').addEventListener('click', function() {
    var btn = this;
    var originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '{l s='Testing...' mod='morposgateway' js=1}';

    var merchantId = document.querySelector('[name="MORPOS_MERCHANT_ID"]').value;
    var clientId = document.querySelector('[name="MORPOS_CLIENT_ID"]').value;
    var clientSecret = document.querySelector('[name="MORPOS_CLIENT_SECRET"]').value;
    var apiKey = document.querySelector('[name="MORPOS_API_KEY"]').value;
    var testmode = document.querySelector('[name="MORPOS_TESTMODE"]').checked ? '1' : '0';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', morposAjaxUrl + '&action=testConnection', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onload = function() {
        btn.disabled = false;
        btn.textContent = originalText;
        
        if (xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    toast({ title: '{l s='Connection Successful!' mod='morposgateway' js=1}', color: 'success' });
                } else {
                    toast({ title: response.error || '{l s='Connection Failed' mod='morposgateway' js=1}', color: 'danger' });
                }
                setStatus(response.status || 'ok');
            } catch (e) {
                toast({ title: '{l s='Invalid response from server' mod='morposgateway' js=1}', color: 'danger' });
            }
        } else {
            toast({ title: '{l s='Network error' mod='morposgateway' js=1}', color: 'danger' });
        }
    };
    
    xhr.onerror = function() {
        btn.disabled = false;
        btn.textContent = originalText;
        setStatus('fail');
        toast({ title: '{l s='Network error' mod='morposgateway' js=1}', color: 'danger' });
    };
    
    xhr.send('merchant_id=' + encodeURIComponent(merchantId) + 
             '&client_id=' + encodeURIComponent(clientId) +
             '&client_secret=' + encodeURIComponent(clientSecret) +
             '&api_key=' + encodeURIComponent(apiKey) +
             '&testmode=' + (testmode === '1' ? 'yes' : 'no'));
});
</script>
