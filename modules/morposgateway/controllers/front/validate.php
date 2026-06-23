<?php
/**
 * MorPOS Payment Validation Controller
 *
 * @author Morpara
 * @copyright 2026 Morpara
 * @license MIT
 */

class MorposGatewayValidateModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /**
     * Format error response with title, message, and details
     *
     * @param string $message User-friendly error message
     * @param string $details Technical details (optional)
     * @param string $title Error title (optional)
     * @param array $extra Additional data like order_id, can_retry (optional)
     * @return array Structured error response
     */
    protected function formatError($message, $details = '', $title = '', $extra = array())
    {
        $response = array(
            'error' => $message,
            'error_title' => !empty($title) ? $title : 'Payment Error',
            'error_details' => $details
        );

        // Merge extra data (order_id, can_retry, etc.)
        if (!empty($extra)) {
            $response = array_merge($response, $extra);
        }

        return $response;
    }

    /**
     * Post process - create payment and redirect/return data
     *
     * FLOW:
     * 1. First attempt: Create order with PS_OS_PREPARATION status (cart is emptied by PrestaShop)
     * 2. If payment init fails: Order stays in system with error status, order_id returned to frontend
     * 3. User clicks retry: Same order_id is used, new payment attempt created
     * 4. Success: User completes payment, callback updates order to paid status
     * 5. Failure: User can retry again with same order_id
     *
     * This prevents duplicate orders and maintains proper order history
     */
    public function postProcess()
    {
        // Check if this is a retry (order_id passed means order already exists)
        $orderId = (int) Tools::getValue('id_order');
        $formType = Configuration::get('MORPOS_FORM_TYPE');

        // Security: Check if payment module is still available for this context
        if (!$this->module->checkIfPaymentOptionIsAvailable()) {
            if (Tools::getValue('ajax')) {
                header('Content-Type: application/json');
                echo json_encode($this->formatError(
                    $this->module->l('Payment method is not available.', 'validate'),
                    'Payment module is disabled or unavailable',
                    $this->module->l('Payment Unavailable', 'validate')
                ));
                exit;
            }

            Tools::redirect('index.php?controller=order&step=1');
        }

        if (!$orderId) {
            // Normal payment flow - validate cart
            $cart = $this->context->cart;

            if (
                $cart->id_customer == 0 || $cart->id_address_delivery == 0 ||
                $cart->id_address_invoice == 0 || !$this->module->active
            ) {
                // Return error for AJAX, redirect for non-AJAX
                if (Tools::getValue('ajax')) {
                    header('Content-Type: application/json');
                    echo json_encode($this->formatError(
                        $this->module->l('Invalid cart.', 'validate'),
                        'Cart is missing customer, delivery, or invoice address',
                        $this->module->l('Cart Error', 'validate')
                    ));
                    exit;
                }

                Tools::redirect('index.php?controller=order&step=1');
            }

            $customer = new Customer($cart->id_customer);

            if (!Validate::isLoadedObject($customer)) {
                // Return error for AJAX, redirect for non-AJAX
                if (Tools::getValue('ajax')) {
                    header('Content-Type: application/json');
                    echo json_encode($this->formatError(
                        $this->module->l('Invalid customer.', 'validate'),
                        'Customer object could not be loaded',
                        $this->module->l('Customer Error', 'validate')
                    ));
                    exit;
                }

                Tools::redirect('index.php?controller=order&step=1');
            }
        }

        // Check if request is AJAX (embedded payment from checkout page)
        if (Tools::getValue('ajax')) {
            header('Content-Type: application/json');
            $result = $this->initiatePayment();
            echo json_encode($result);
            exit;
        }

        // Non-AJAX request - hosted payment type, redirect directly to gateway
        $result = $this->initiatePayment();

        if (isset($result['redirect'])) {
            // Redirect to payment gateway (hosted)
            Tools::redirect($result['redirect']);
        } elseif (isset($result['html'])) {
            // Should not happen for non-AJAX (embedded should use AJAX)
            // But handle gracefully - redirect to order
            Tools::redirect('index.php?controller=order&step=1');
        } elseif (isset($result['error'])) {
            // Store error and redirect to retry page
            $this->context->cookie->morpos_error_message = $result['error'];
            $redirectParams = $orderId ? array('id_order' => $orderId) : array();
            Tools::redirect($this->context->link->getModuleLink(
                'morposgateway',
                'retry',
                $redirectParams,
                true
            ));
        }

        // Fallback - redirect to order page
        Tools::redirect('index.php?controller=order&step=1');
    }

    /**
     * Initiate payment with MorPOS
     */
    protected function initiatePayment()
    {
        // Check if this is a retry
        $orderId = (int) Tools::getValue('id_order');

        if ($orderId) {
            // Retry existing order
            return $this->initiateRetryPayment($orderId);
        }

        // New payment
        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);

        // Validate cart
        if (!Validate::isLoadedObject($cart) || $cart->id_customer == 0 || !Validate::isLoadedObject($customer)) {
            return $this->formatError(
                $this->module->l('Invalid cart or customer.', 'validate'),
                'Cart or customer object validation failed',
                $this->module->l('Validation Error', 'validate')
            );
        }

        // CRITICAL SECURITY: Verify cart belongs to current customer (PrestaShop Rule #1)
        if ($cart->id_customer != $this->context->customer->id) {
            PrestaShopLogger::addLog(
                'MorPOS Security: Cart hijacking attempt - Cart customer: ' . $cart->id_customer .
                ', Current customer: ' . $this->context->customer->id,
                3,
                null,
                'Cart',
                $cart->id,
                true
            );
            return $this->formatError(
                $this->module->l('Security validation failed.', 'validate'),
                'Cart does not belong to current customer',
                $this->module->l('Security Error', 'validate')
            );
        }

        // Verify cart secure_key matches customer
        if ($cart->secure_key != $customer->secure_key) {
            PrestaShopLogger::addLog(
                'MorPOS Security: Invalid cart secure_key',
                3,
                null,
                'Cart',
                $cart->id,
                true
            );
            return $this->formatError(
                $this->module->l('Security validation failed.', 'validate'),
                'Cart secure_key does not match customer',
                $this->module->l('Security Error', 'validate')
            );
        }

        // Rate limiting: Check for too many payment attempts
        if ($this->isRateLimitExceeded($cart->id)) {
            PrestaShopLogger::addLog(
                'MorPOS: Rate limit exceeded for cart: ' . $cart->id,
                2,
                null,
                'Cart',
                $cart->id,
                true
            );
            return $this->formatError(
                $this->module->l('Too many payment attempts. Please wait a few minutes and try again.', 'validate'),
                'Rate limit exceeded for cart ID: ' . $cart->id,
                $this->module->l('Rate Limit', 'validate')
            );
        }

        // Check credentials
        $requiredConfigs = array('MORPOS_CLIENT_ID', 'MORPOS_CLIENT_SECRET', 'MORPOS_MERCHANT_ID', 'MORPOS_API_KEY');
        foreach ($requiredConfigs as $config) {
            if (empty(Configuration::get($config))) {
                return $this->formatError(
                    $this->module->l('Payment gateway is not properly configured.', 'validate'),
                    'Missing configuration: ' . $config,
                    $this->module->l('Configuration Error', 'validate')
                );
            }
        }

        // Create order with proper module association and error handling
        // Using PS_OS_PREPARATION status for pending payments (approach 1: create order during payment)
        // This is a standard PrestaShop practice - order created with pending status,
        // then updated to paid/failed based on payment gateway response
        try {
            $this->module->validateOrder(
                $cart->id,
                Configuration::get('PS_OS_PREPARATION'),
                $cart->getOrderTotal(true, Cart::BOTH),
                $this->module->displayName,
                null,
                array(),
                (int) $cart->id_currency,
                false,
                $customer->secure_key
            );

            $orderId = $this->module->currentOrder;

            if (!$orderId) {
                PrestaShopLogger::addLog(
                    'MorPOS: Order creation failed - validateOrder returned no order ID for cart: ' . $cart->id,
                    3,
                    null,
                    'Cart',
                    $cart->id,
                    true
                );
                return $this->formatError(
                    $this->module->l('Failed to create order.', 'validate'),
                    'validateOrder returned no order ID for cart: ' . $cart->id,
                    $this->module->l('Order Creation Error', 'validate')
                );
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'MorPOS: Order creation exception - ' . $e->getMessage() . ' for cart: ' . $cart->id,
                3,
                null,
                'Cart',
                $cart->id,
                true
            );
            return $this->formatError(
                $this->module->l('Failed to create order.', 'validate'),
                $e->getMessage(),
                $this->module->l('Order Creation Error', 'validate')
            );
        }

        $order = new Order($orderId);
        $currency = new Currency($order->id_currency);

        // Get form type
        $formType = Configuration::get('MORPOS_FORM_TYPE');

        // Convert currency
        $currencyNumeric = MorposCurrency::toNumeric($currency->iso_code);
        if (!$currencyNumeric) {
            return $this->formatError(
                $this->module->l('Currency not supported.', 'validate'),
                'Currency code: ' . $currency->iso_code,
                $this->module->l('Currency Error', 'validate')
            );
        }

        // Generate conversation ID
        $attemptSeq = MorposConversation::getNextAttemptSeq($orderId);
        $secret = _COOKIE_KEY_;
        $conversationId = MorposConversation::makeConversationId20ForAttempt($orderId, $attemptSeq, $secret);

        // Store attempt with expected amount for later verification (Security Rule #2)
        $expectedAmount = $order->total_paid;
        MorposConversation::addAttempt($orderId, $attemptSeq, $conversationId, array(
            'expected_amount' => $expectedAmount,
            'currency_code' => $currencyNumeric,
            'cart_id' => $cart->id,
        ));

        // Prepare callback URLs
        $language = $this->context->language->iso_code;
        $returnUrl = $this->context->link->getModuleLink(
            'morposgateway',
            'callback',
            array(
                'order_id' => $orderId,
                'form_type' => $formType,
                'language' => $language,
            ),
            true
        );

        // Create MorPOS client
        $client = new MorposClient(
            Configuration::get('MORPOS_CLIENT_ID'),
            Configuration::get('MORPOS_CLIENT_SECRET'),
            Configuration::get('MORPOS_MERCHANT_ID'),
            '',
            Configuration::get('MORPOS_API_KEY'),
            Configuration::get('MORPOS_TESTMODE') ? 'sandbox' : 'production'
        );

        // Create payment
        $payload = array(
            'conversationId' => $conversationId,
            'paymentMethod' => $formType === 'hosted' ? 'HOSTEDPAYMENT' : 'EMBEDDEDPAYMENT',
            'returnUrl' => $returnUrl,
            'failUrl' => $returnUrl,
            'language' => substr($language, 0, 2) === 'tr' ? 'tr' : 'en',
            'amount' => number_format($order->total_paid, 2, '.', ''),
            'currencyCode' => $currencyNumeric,
        );

        $response = $client->createPayment($payload);

        if (isset($response['ok']) && $response['ok']) {
            $data = isset($response['data']) ? $response['data'] : array();

            if ($formType === 'hosted' && isset($data['returnUrl'])) {
                return array(
                    'redirect' => $data['returnUrl'],
                    'order_id' => $orderId
                );
            } elseif (isset($data['paymentFormContent'])) {
                return array(
                    'html' => $data['paymentFormContent'],
                    'order_id' => $orderId
                );
            } else {
                // Payment gateway didn't return expected data - mark order as error
                $this->handlePaymentInitFailure($order, 'Payment data not received from gateway');
                return $this->formatError(
                    $this->module->l('Payment data not received.', 'validate'),
                    'Gateway response missing expected fields (returnUrl or paymentFormContent)',
                    $this->module->l('Gateway Error', 'validate'),
                    array('order_id' => $orderId, 'can_retry' => true)
                );
            }
        } else {
            // Payment initiation failed - mark order as error
            $errorMsg = isset($response['message']) ? $response['message'] :
                (isset($response['error']) ? $response['error'] :
                    $this->module->l('Payment initiation failed.', 'validate'));
            $errorDetails = isset($response['errorCode']) ? 'Error code: ' . $response['errorCode'] : '';
            if (isset($response['details'])) {
                $errorDetails .= ($errorDetails ? ' - ' : '') . $response['details'];
            }
            $this->handlePaymentInitFailure($order, $errorMsg);

            // Return error WITH order_id so frontend can retry
            return $this->formatError(
                $errorMsg,
                $errorDetails,
                $this->module->l('Payment Gateway Error', 'validate'),
                array('order_id' => $orderId, 'can_retry' => true)
            );
        }
    }

    /**
     * Initiate retry payment for existing order
     */
    protected function initiateRetryPayment($orderId)
    {
        $order = new Order($orderId);

        if (!Validate::isLoadedObject($order) || $order->id_customer != $this->context->customer->id) {
            return $this->formatError(
                $this->module->l('Invalid order.', 'validate'),
                'Order not found or does not belong to current customer',
                $this->module->l('Order Error', 'validate')
            );
        }

        // Check credentials
        $requiredConfigs = array('MORPOS_CLIENT_ID', 'MORPOS_CLIENT_SECRET', 'MORPOS_MERCHANT_ID', 'MORPOS_API_KEY');
        foreach ($requiredConfigs as $config) {
            if (empty(Configuration::get($config))) {
                return $this->formatError(
                    $this->module->l('Payment gateway is not properly configured.', 'validate'),
                    'Missing configuration: ' . $config,
                    $this->module->l('Configuration Error', 'validate')
                );
            }
        }

        $currency = new Currency($order->id_currency);
        $formType = Configuration::get('MORPOS_FORM_TYPE');
        $currencyNumeric = MorposCurrency::toNumeric($currency->iso_code);

        if (!$currencyNumeric) {
            return $this->formatError(
                $this->module->l('Currency not supported.', 'validate'),
                'Currency code: ' . $currency->iso_code,
                $this->module->l('Currency Error', 'validate')
            );
        }

        // Generate new conversation ID
        $attemptSeq = MorposConversation::getNextAttemptSeq($orderId);
        $secret = _COOKIE_KEY_;
        $conversationId = MorposConversation::makeConversationId20ForAttempt($orderId, $attemptSeq, $secret);

        // Store expected amount for verification (Security Rule #2)
        MorposConversation::addAttempt($orderId, $attemptSeq, $conversationId, array(
            'expected_amount' => $order->total_paid,
            'currency_code' => $currencyNumeric,
            'is_retry' => true,
        ));

        $language = $this->context->language->iso_code;
        $returnUrl = $this->context->link->getModuleLink(
            'morposgateway',
            'callback',
            array('order_id' => $orderId, 'form_type' => $formType, 'language' => $language),
            true
        );

        $client = new MorposClient(
            Configuration::get('MORPOS_CLIENT_ID'),
            Configuration::get('MORPOS_CLIENT_SECRET'),
            Configuration::get('MORPOS_MERCHANT_ID'),
            '',
            Configuration::get('MORPOS_API_KEY'),
            Configuration::get('MORPOS_TESTMODE') ? 'sandbox' : 'production'
        );

        $payload = array(
            'conversationId' => $conversationId,
            'paymentMethod' => $formType === 'hosted' ? 'HOSTEDPAYMENT' : 'EMBEDDEDPAYMENT',
            'returnUrl' => $returnUrl,
            'failUrl' => $returnUrl,
            'language' => substr($language, 0, 2) === 'tr' ? 'tr' : 'en',
            'amount' => number_format($order->total_paid, 2, '.', ''),
            'currencyCode' => $currencyNumeric,
        );

        $response = $client->createPayment($payload);

        if (isset($response['ok']) && $response['ok']) {
            $data = isset($response['data']) ? $response['data'] : array();
            if ($formType === 'hosted' && isset($data['returnUrl'])) {
                return array('redirect' => $data['returnUrl']);
            } elseif (isset($data['paymentFormContent'])) {
                return array('html' => $data['paymentFormContent']);
            }
        }

        return $this->formatError(
            $this->module->l('Payment initiation failed.', 'validate'),
            'Gateway did not return success response',
            $this->module->l('Payment Gateway Error', 'validate')
        );
    }

    /**
     * Handle payment initialization failure by updating order status
     * This prevents orders from staying in preparation status indefinitely
     */
    protected function handlePaymentInitFailure($order, $errorMessage)
    {
        if (!Validate::isLoadedObject($order)) {
            return;
        }

        $failedStatusId = MorposGateway::getFailedStatusId();

        PrestaShopLogger::addLog(
            'MorPOS: Payment initialization failed for order: ' . $order->id . ' - ' . $errorMessage,
            3,
            null,
            'Order',
            $order->id,
            true
        );

        // Update order to failed status
        $history = new OrderHistory();
        $history->id_order = (int) $order->id;
        $history->id_order_state = $failedStatusId;
        $history->changeIdOrderState($failedStatusId, $order);
        $history->addWithemail(true, array());

        // Add private message with error details
        $this->addOrderMessage($order, 'MorPOS Payment Initialization Failed: ' . $errorMessage);
    }

    /**
     * Check if rate limit is exceeded for cart/customer
     * Prevents spam of payment creation attempts
     */
    protected function isRateLimitExceeded($cartId)
    {
        // Allow maximum 5 payment attempts per 5 minutes per cart
        $maxAttempts = 5;
        $timeWindow = 300; // 5 minutes in seconds

        $sql = 'SELECT COUNT(*) as attempt_count 
                FROM `' . _DB_PREFIX_ . 'morpos_conversation_attempt` 
                WHERE order_id IN (
                    SELECT id_order FROM `' . _DB_PREFIX_ . 'orders` WHERE id_cart = ' . (int) $cartId . '
                )
                AND created_at > DATE_SUB(NOW(), INTERVAL ' . (int) $timeWindow . ' SECOND)';

        $result = Db::getInstance()->getRow($sql);

        if ($result === false) {
            // If query fails, allow the attempt (fail open for user experience)
            PrestaShopLogger::addLog(
                'MorPOS: Rate limit check failed - ' . Db::getInstance()->getMsgError(),
                2,
                null,
                'Cart',
                $cartId,
                true
            );
            return false;
        }

        $attemptCount = isset($result['attempt_count']) ? (int) $result['attempt_count'] : 0;
        return $attemptCount >= $maxAttempts;
    }

    /**
     * Initialize content
     */
    public function initContent()
    {
        parent::initContent();

        $this->context->smarty->assign(array(
            'confirm_url' => $this->context->link->getModuleLink('morposgateway', 'validate', array('ajax' => 1), true),
            'redirect_success' => $this->context->link->getPageLink('order-confirmation', true),
            'text_payment_failed_default' => $this->module->l('Payment failed. Please try again.', 'validate'),
            'text_payment_init_failed' => $this->module->l('Payment initialization failed.', 'validate'),
            'text_network_error' => $this->module->l('Network error. Please check your connection.', 'validate'),
            'title_payment_error' => $this->module->l('Payment Error', 'validate'),
            'title_system_error' => $this->module->l('System Error', 'validate'),
            'title_network_error' => $this->module->l('Network Error', 'validate'),
            'title_payment_failed' => $this->module->l('Payment Failed', 'validate'),
            'text_unable_to_connect' => $this->module->l('Unable to connect to payment server. Please try again.', 'validate'),
            'text_check_connection' => $this->module->l('Unable to connect to payment server. Please check your connection and try again.', 'validate'),
        ));

        $this->setTemplate('module:morposgateway/views/templates/front/payment.tpl');
    }

    /**
     * Add a private message to an order (visible only in admin panel)
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
