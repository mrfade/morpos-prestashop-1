<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$text_processing_title|escape:'html':'UTF-8'}</title>
<style>
  body {
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    margin: 0;
    padding: 1rem;
    text-align: center;
    background: white;
    color: #333;
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }
  .result-container {
    max-width: 350px;
    width: 100%;
    padding: 0.5rem;
  }
  .status-icon {
    font-size: 2.5rem;
    margin-bottom: 0.75rem;
    display: block;
  }
  .success { color: #22c55e; }
  .error { color: #ef4444; }
  .status-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
  }
  .status-message {
    margin-bottom: 1rem;
    color: #666;
    line-height: 1.4;
    font-size: 0.9rem;
  }
  .error-details {
    background: #fef2f2;
    border-left: 4px solid #ef4444;
    padding: 0.75rem;
    margin-bottom: 1rem;
    color: #dc2626;
    text-align: left;
    font-size: 0.85rem;
  }
  .redirect-info {
    font-size: 0.8rem;
    color: #999;
  }
</style>
<script>
  (function () {
    var status = {$status|json_encode nofilter};
    var redirectUrl = {$redirect_url|json_encode nofilter};
    var orderId = {$order_id|json_encode nofilter};
    var orderReference = {$order_reference|json_encode nofilter};
    var errorMessage = {if isset($error_message)}{$error_message|json_encode nofilter}{else}''{/if};
    var errorDetails = {if isset($error_details)}{$error_details|json_encode nofilter}{else}''{/if};

    if (window.parent && window.parent !== window) {
      // Send message to parent window (modal)
      window.parent.postMessage({
        type: 'MORPOS_RESULT',
        status: status,
        redirect_url: redirectUrl,
        order_id: orderId,
        order_reference: orderReference,
        message: errorMessage,
        details: errorDetails
      }, window.location.origin);
    } else {
      // Direct navigation - redirect after showing message
      setTimeout(function() {
        window.location.href = redirectUrl;
      }, status === 'success' ? 1500 : 3000);
    }
  })();
</script>
</head>
<body>
  <div class="result-container">
    {if $status == 'success'}
      <div class="status-icon success">✅</div>
      <div class="status-title success">{$text_payment_successful|escape:'html':'UTF-8'}</div>
      <div class="status-message">{$text_processing_message|escape:'html':'UTF-8'}</div>
    {else}
      <div class="status-icon error">❌</div>
      <div class="status-title error">{$text_payment_failed|escape:'html':'UTF-8'}</div>
      {if isset($error_message) && $error_message}
        <div class="error-details">{$error_message|escape:'html':'UTF-8'}</div>
      {/if}
      <div class="status-message">{$text_redirect_retry|escape:'html':'UTF-8'}</div>
    {/if}
    
    <div class="redirect-info">
      {$text_redirecting_auto|escape:'html':'UTF-8'}
    </div>
  </div>
</body>
</html>
