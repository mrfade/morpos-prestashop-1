<?php
/**
 * MorPOS Payment Callback Controller
 *
 * @author Morpara
 * @copyright 2026 Morpara
 * @license MIT
 */

class MorposGatewayCallbackModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /**
     * Post process - handle callback from MorPOS
     */
    public function postProcess()
    {
        $orderId = (int) Tools::getValue('order_id');
        $formType = Tools::getValue('form_type', 'hosted');

        if (!$orderId) {
            Tools::redirect('index.php?controller=order');
            return;
        }

        $order = new Order($orderId);

        if (!Validate::isLoadedObject($order)) {
            Tools::redirect('index.php?controller=order');
            return;
        }

        // CRITICAL SECURITY: Verify order belongs to current customer (if customer is logged in)
        // Note: Callback may come from payment gateway directly, so customer might not be in session
        // We'll verify the conversationId and payment amount instead as primary security
        if ($this->context->customer->isLogged() && $order->id_customer != $this->context->customer->id) {
            PrestaShopLogger::addLog(
                'MorPOS Security: Order access attempt by wrong customer - Order customer: ' .
                $order->id_customer . ', Current customer: ' . $this->context->customer->id,
                3,
                null,
                'Order',
                $order->id,
                true
            );
            Tools::redirect('index.php?controller=order');
            return;
        }

        // Handle callback
        $paidStatusId = (int) Configuration::get('MORPOS_SUCCESS_STATUS') ?: Configuration::get('PS_OS_PAYMENT');
        $failStatusId = (int) Configuration::get('MORPOS_FAILED_STATUS') ?: Configuration::get('PS_OS_ERROR');

        $callbackResult = $this->handleCallback($order, $paidStatusId, $failStatusId);

        // Handle response based on form type
        if ($formType === 'embedded') {
            $this->handleEmbeddedResponse($order, $callbackResult);
        } else {
            $this->handleHostedResponse($order, $callbackResult);
        }
    }

    /**
     * Handle callback logic
     */
    protected function handleCallback($order, $paidStatusId, $failStatusId)
    {
        // Prevent duplicate processing with more robust check
        // Check current state to avoid race conditions
        $currentState = (int) $order->getCurrentState();

        // If already paid, don't process again
        if ($currentState == $paidStatusId) {
            PrestaShopLogger::addLog(
                'MorPOS: Duplicate callback ignored - Order already paid: ' . $order->id,
                2,
                null,
                'Order',
                $order->id,
                true
            );
            return array(
                'success' => true,
                'already_processed' => true
            );
        }

        // If already processing (preparation status), log potential race condition
        if ($currentState == Configuration::get('PS_OS_PREPARATION')) {
            PrestaShopLogger::addLog(
                'MorPOS: Concurrent callback detected for order in preparation: ' . $order->id,
                2,
                null,
                'Order',
                $order->id,
                true
            );
        }

        // Collect return params
        $payload = $this->collectReturnParams();
        $shortInfo = $this->buildPaymentShortInfo($payload);

        // CRITICAL SECURITY: Validate conversation ID (Security Rule #3)
        // The conversationId is cryptographically generated using HMAC with secret key
        // This ensures the callback is legitimate and not forged
        $conversationId = Tools::getValue('ConversationId', Tools::getValue('conversationId', ''));
        $isValidConversation = MorposConversation::conversationExistsForOrder($order->id, $conversationId);

        if (!$isValidConversation) {
            $errorMessage = $this->module->l('Invalid payment session.', 'callback');
            PrestaShopLogger::addLog(
                'MorPOS Security: Invalid conversationId in callback - ID: ' . $conversationId .
                ', Order: ' . $order->id,
                3,
                null,
                'Order',
                $order->id,
                true
            );
            $this->addOrderHistory($order, $failStatusId, array(), 'MorPOS: Invalid conversationId. ' . $shortInfo);
            return array(
                'success' => false,
                'error_message' => $errorMessage
            );
        }

        // Additional verification: ensure conversationId matches order
        $conversationData = MorposConversation::getConversationData($conversationId);
        if ($conversationData && isset($conversationData['cart_id'])) {
            if ((int) $conversationData['cart_id'] !== (int) $order->id_cart) {
                PrestaShopLogger::addLog(
                    'MorPOS Security: Cart mismatch in callback - Expected: ' .
                    $conversationData['cart_id'] . ', Order cart: ' . $order->id_cart,
                    3,
                    null,
                    'Order',
                    $order->id,
                    true
                );
                $this->addOrderHistory($order, $failStatusId, array(), 'MorPOS: Cart verification failed. ' . $shortInfo);
                return array(
                    'success' => false,
                    'error_message' => $this->module->l('Payment verification failed.', 'callback')
                );
            }
        }

        // Verify payment
        $paymentResult = $this->isPaymentSuccessful();

        if (!$paymentResult['success']) {
            $errorMessage = isset($paymentResult['error_message']) ?
                $paymentResult['error_message'] :
                $this->module->l('Payment verification failed.', 'callback');

            // Extract transaction details even for failed payment
            $transactionId = $this->getPayloadValue($payload, array('PaymentId', 'paymentId'), '');
            $bankRef = $this->getPayloadValue($payload, array('BankUniqueReferenceNumber', 'bankUniqueReferenceNumber'), '');
            $cardMasked = $this->getPayloadValue($payload, array('MaskedCardNumber', 'maskedCardNumber'), '');
            $errorCode = isset($paymentResult['error_code']) ? $paymentResult['error_code'] : '';

            $paymentData = array(
                'success' => false,
                'transactionId' => $transactionId,
                'bankReference' => $bankRef,
                'cardNumber' => $cardMasked,
                'errorCode' => $errorCode
            );

            $this->addOrderHistory($order, $failStatusId, $paymentData, 'MorPOS: Payment FAILED. ' . $shortInfo);
            return array(
                'success' => false,
                'error_message' => $errorMessage
            );
        }

        // Payment successful - extract transaction details
        $transactionId = $this->getPayloadValue($payload, array('PaymentId', 'paymentId'), '');
        $bankRef = $this->getPayloadValue($payload, array('BankUniqueReferenceNumber', 'bankUniqueReferenceNumber'), '');
        $amountStr = $this->getPayloadValue($payload, array('Amount', 'amount'), '');
        $cardMasked = $this->getPayloadValue($payload, array('MaskedCardNumber', 'maskedCardNumber'), '');
        $installment = $this->getPayloadValue($payload, array('InstallmentCount', 'installmentCount'), '1');

        $paymentData = array(
            'success' => true,
            'transactionId' => $transactionId,
            'conversationId' => $conversationId,
            'bankReference' => $bankRef,
            'amount' => $amountStr,
            'cardNumber' => $cardMasked,
            'installments' => $installment
        );

        $this->addOrderHistory($order, $paidStatusId, $paymentData, 'MorPOS: Payment SUCCESSFUL. ' . $shortInfo);

        return array('success' => true);
    }

    /**
     * Check if payment was successful
     */
    protected function isPaymentSuccessful()
    {
        $resultCode = Tools::getValue('ResultCode', Tools::getValue('resultCode', ''));
        $message = Tools::getValue('Message', Tools::getValue('message', ''));

        if ($resultCode !== 'B0000' || $message !== 'Approved') {
            return array(
                'success' => false,
                'error_code' => $resultCode,
                'error_message' => !empty($message) && $message !== 'Approved' ?
                    $message : $this->module->l('Payment failed.', 'callback')
            );
        }

        $conversationId = Tools::getValue('ConversationId', Tools::getValue('conversationId', ''));

        if (empty($conversationId)) {
            return array(
                'success' => false,
                'error_code' => 'MISSING_CONVERSATION_ID',
                'error_message' => $this->module->l('Missing conversation ID.', 'callback')
            );
        }

        // Verify with API
        $client = new MorposClient(
            Configuration::get('MORPOS_CLIENT_ID'),
            Configuration::get('MORPOS_CLIENT_SECRET'),
            Configuration::get('MORPOS_MERCHANT_ID'),
            '',
            Configuration::get('MORPOS_API_KEY'),
            Configuration::get('MORPOS_TESTMODE') ? 'sandbox' : 'production'
        );

        $checkResult = $client->checkPayment(array('conversationId' => $conversationId));

        if (!isset($checkResult['ok']) || !$checkResult['ok']) {
            return array(
                'success' => false,
                'error_code' => 'API_ERROR',
                'error_message' => $this->module->l('Payment verification failed.', 'callback')
            );
        }

        $checkData = isset($checkResult['data']) ? $checkResult['data'] : array();
        $checkResponseCode = isset($checkData['responseCode']) ? $checkData['responseCode'] : '';
        $checkResponseDescription = isset($checkData['responseDescription']) ? $checkData['responseDescription'] : '';

        if ($checkResponseCode !== 'B0000' || $checkResponseDescription !== 'Approved') {
            return array(
                'success' => false,
                'error_code' => $checkResponseCode,
                'error_message' => !empty($checkResponseDescription) && $checkResponseDescription !== 'Approved' ?
                    $checkResponseDescription : $this->module->l('Payment failed.', 'callback')
            );
        }

        // CRITICAL SECURITY: Verify payment amount from gateway matches expected amount (PrestaShop Rule #2)
        $gatewayAmount = isset($checkData['amount']) ? $checkData['amount'] :
            (isset($checkData['Amount']) ? $checkData['Amount'] : null);

        if ($gatewayAmount !== null) {
            // Get expected amount from conversation attempt record
            $expectedData = MorposConversation::getConversationData($conversationId);
            if ($expectedData && isset($expectedData['expected_amount'])) {
                $expectedAmount = number_format((float) $expectedData['expected_amount'], 2, '.', '');
                $receivedAmount = number_format((float) $gatewayAmount, 2, '.', '');

                if ($expectedAmount !== $receivedAmount) {
                    PrestaShopLogger::addLog(
                        'MorPOS Security: Payment amount mismatch - Expected: ' . $expectedAmount .
                        ', Received: ' . $receivedAmount . ', ConversationId: ' . $conversationId,
                        4,
                        null,
                        'Payment',
                        null,
                        true
                    );
                    return array(
                        'success' => false,
                        'error_code' => 'AMOUNT_MISMATCH',
                        'error_message' => $this->module->l('Payment amount verification failed.', 'callback')
                    );
                }
            }
        }

        return array('success' => true);
    }

    /**
     * Handle embedded form response
     */
    protected function handleEmbeddedResponse($order, $callbackResult)
    {
        $isSuccess = isset($callbackResult['success']) && $callbackResult['success'];

        // Store error message in cookie for display
        if (!$isSuccess && isset($callbackResult['error_message'])) {
            $this->context->cookie->morpos_error_message = $callbackResult['error_message'];
        }

        // Set redirect URL based on success/failure
        $redirectUrl = $isSuccess ?
            $this->context->link->getPageLink('order-confirmation', true, null, array(
                'id_cart' => $order->id_cart,
                'id_module' => $this->module->id,
                'id_order' => $order->id,
                'key' => $order->secure_key
            )) :
            $this->context->link->getModuleLink(
                $this->module->name,
                'retry',
                array('id_order' => $order->id),
                true
            );

        $this->context->smarty->assign(array(
            'status' => $isSuccess ? 'success' : 'failure',
            'order_id' => $order->id,
            'order_reference' => isset($order->reference) ? $order->reference : $order->id,
            'redirect_url' => $redirectUrl,
            'error_message' => isset($callbackResult['error_message']) ? $callbackResult['error_message'] : '',
            'text_processing_title' => $this->module->l('Processing Payment', 'callback'),
            'text_processing_heading' => $this->module->l('Payment Processing', 'callback'),
            'text_processing_message' => $this->module->l('Please wait while we process your payment...', 'callback'),
            'text_payment_successful' => $this->module->l('Payment Successful!', 'callback'),
            'text_payment_failed' => $this->module->l('Payment Failed', 'callback'),
            'text_redirect_retry' => $this->module->l('Redirecting to order details...', 'callback'),
            'text_redirecting_auto' => $this->module->l('Redirecting automatically...', 'callback'),
        ));

        $this->setTemplate('module:morposgateway/views/templates/front/payment_result.tpl');
    }

    /**
     * Handle hosted form response
     */
    protected function handleHostedResponse($order, $callbackResult)
    {
        $isSuccess = isset($callbackResult['success']) && $callbackResult['success'];

        if ($isSuccess) {
            // Success: redirect to order confirmation page
            $redirectUrl = $this->context->link->getPageLink('order-confirmation', true, null, array(
                'id_cart' => $order->id_cart,
                'id_module' => $this->module->id,
                'id_order' => $order->id,
                'key' => $order->secure_key
            ));
        } else {
            // Failed: store error and redirect to retry page with order ID
            if (isset($callbackResult['error_message'])) {
                $this->context->cookie->morpos_error_message = $callbackResult['error_message'];
            }

            $redirectUrl = $this->context->link->getModuleLink(
                $this->module->name,
                'retry',
                array('id_order' => $order->id),
                true
            );
        }

        Tools::redirect($redirectUrl);
        exit;
    }

    /**
     * Collect return parameters
     */
    protected function collectReturnParams()
    {
        $paramKeys = array(
            'ResultCode',
            'resultCode',
            'Message',
            'message',
            'ConversationId',
            'conversationId',
            'PaymentId',
            'paymentId',
            'BankUniqueReferenceNumber',
            'bankUniqueReferenceNumber',
            'Amount',
            'amount',
            'Currency',
            'currency',
            'InstallmentCount',
            'installmentCount',
            'MaskedCardNumber',
            'maskedCardNumber'
        );

        $payload = array();
        foreach ($paramKeys as $key) {
            $value = Tools::getValue($key, '');
            if (!empty($value)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    /**
     * Build payment short info
     */
    protected function buildPaymentShortInfo($payload)
    {
        $resultCode = $this->getPayloadValue($payload, array('ResultCode', 'resultCode'), '');
        $message = $this->getPayloadValue($payload, array('Message', 'message'), '');
        $conversationId = $this->getPayloadValue($payload, array('ConversationId', 'conversationId'), '');
        $paymentId = $this->getPayloadValue($payload, array('PaymentId', 'paymentId'), '');
        $bankRef = $this->getPayloadValue($payload, array('BankUniqueReferenceNumber', 'bankUniqueReferenceNumber'), '');
        $amountStr = $this->getPayloadValue($payload, array('Amount', 'amount'), '');
        $currencyNum = $this->getPayloadValue($payload, array('Currency', 'currency'), '');
        $installment = $this->getPayloadValue($payload, array('InstallmentCount', 'installmentCount'), '');
        $cardMasked = $this->getPayloadValue($payload, array('MaskedCardNumber', 'maskedCardNumber'), '');

        $currencyIso = $this->currencyFromNumeric($currencyNum);

        $shortParts = array();

        if (!empty($cardMasked)) {
            $shortParts[] = 'Card: ' . $cardMasked;
        }

        if (!empty($installment)) {
            $shortParts[] = 'Installment: ' . $installment;
        }

        $shortParts[] = 'ResultCode: ' . ($resultCode ?: '—');
        $shortParts[] = 'Message: ' . ($message ?: '—');

        if (!empty($amountStr)) {
            $shortParts[] = 'Amount: ' . ($amountStr ?: '—') . ' ' . ($currencyIso ?: '—');
        }

        $shortParts[] = 'PaymentId: ' . ($paymentId ?: '—');
        $shortParts[] = 'ConversationId: ' . ($conversationId ?: '—');

        return implode("\n", $shortParts);
    }

    /**
     * Get payload value from multiple possible keys
     */
    protected function getPayloadValue($payload, $keys, $default = null)
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && !empty($payload[$key])) {
                return $payload[$key];
            }
        }
        return $default;
    }

    /**
     * Convert numeric currency to ISO code
     */
    protected function currencyFromNumeric($numericCode)
    {
        if (empty($numericCode)) {
            return '';
        }

        $map = MorposCurrency::numericMap();
        $reversed = array_flip($map);

        return isset($reversed[$numericCode]) ? $reversed[$numericCode] : $numericCode;
    }

    /**
     * Add order history with payment transaction record
     * 
     * @param Order $order Order object
     * @param int $statusId Order state ID
     * @param array $paymentData Payment transaction details (transactionId, amount, etc.)
     * @param string $message Additional message for order history
     */
    protected function addOrderHistory($order, $statusId, $paymentData = array(), $message = '')
    {
        if (!Validate::isLoadedObject($order)) {
            return false;
        }

        // Create order history entry
        $history = new OrderHistory();
        $history->id_order = (int) $order->id;
        $history->id_order_state = (int) $statusId;

        // Prepare email template variables
        $templateVars = array();

        // Change order state and send email if needed
        $history->changeIdOrderState((int) $statusId, $order);
        $history->addWithemail(true, $templateVars);

        // Add payment transaction record ONLY for successful payments
        $isSuccess = isset($paymentData['success']) && $paymentData['success'];
        if ($isSuccess && !empty($paymentData) && isset($paymentData['transactionId'])) {
            $this->addOrderPayment($order, $paymentData);
        }

        // For failed payments or additional info, add as order message (visible in admin Messages tab)
        if (!empty($message)) {
            $this->addOrderMessage($order, $message);
        }

        return true;
    }

    /**
     * Add payment transaction record to order (visible in admin panel Payments tab)
     * Only for successful payments
     * 
     * @param Order $order The order object
     * @param array $paymentData Transaction details (transactionId, conversationId, bankReference, amount, cardNumber, etc.)
     * @return bool Success status
     */
    protected function addOrderPayment($order, $paymentData)
    {
        if (!Validate::isLoadedObject($order) || empty($paymentData)) {
            return false;
        }

        try {
            $payment = new OrderPayment();
            $payment->order_reference = $order->reference;
            $payment->id_currency = (int) $order->id_currency;
            $payment->amount = isset($paymentData['amount']) ? (float) $paymentData['amount'] : (float) $order->total_paid;
            $payment->payment_method = 'MorPOS';
            $payment->conversion_rate = (float) $order->conversion_rate;

            // Build transaction ID with structured format: PaymentID:ABC123|ConversationID:XYZ789|BankRef:123456
            $txnParts = array();
            if (isset($paymentData['transactionId']) && !empty($paymentData['transactionId'])) {
                $txnParts[] = 'PaymentID:' . $paymentData['transactionId'];
            }
            if (isset($paymentData['conversationId']) && !empty($paymentData['conversationId'])) {
                $txnParts[] = 'ConversationID:' . $paymentData['conversationId'];
            }
            if (isset($paymentData['bankReference']) && !empty($paymentData['bankReference'])) {
                $txnParts[] = 'BankRef:' . $paymentData['bankReference'];
            }
            $payment->transaction_id = !empty($txnParts) ? implode('|', $txnParts) : '';

            // Card details
            if (isset($paymentData['cardNumber']) && !empty($paymentData['cardNumber'])) {
                $payment->card_number = $paymentData['cardNumber'];
            }

            if (!$payment->add()) {
                throw new Exception('Failed to save OrderPayment');
            }

            return true;
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'MorPOS: Failed to add order payment: ' . $e->getMessage(),
                3,
                null,
                'Order',
                (int) $order->id,
                true
            );
            return false;
        }
    }

    /**
     * Add a private message to an order (visible in admin panel Messages tab)
     * Delegates to module's centralized implementation
     *
     * @param Order $order The order object
     * @param string $message Message content (HTML allowed: <br>)
     * @return bool Success status
     */
    protected function addOrderMessage($order, $message)
    {
        return $this->module->addOrderMessage($order, $message);
    }
}
