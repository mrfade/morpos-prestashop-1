<?php
/**
 * MorPOS Payment Retry Controller
 *
 * @author Morpara
 * @copyright 2026 Morpara
 * @license MIT
 */

class MorposGatewayRetryModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /**
     * Initialize controller
     */
    public function init()
    {
        parent::init();
        $this->display_column_left = false;
        $this->display_column_right = false;
    }

    /**
     * Check if order is in retryable status (awaiting payment or failed payment)
     * 
     * @param Order $order
     * @return bool
     */
    protected function isOrderRetryable($order)
    {
        $currentState = (int) $order->getCurrentState();
        $awaitingPaymentStatus = (int) Configuration::get('PS_OS_PREPARATION');
        // Same failed-status resolution as the callback; PS_OS_ERROR always retryable.
        $failedPaymentStatus = MorposGateway::getFailedStatusId();
        $psError = (int) Configuration::get('PS_OS_ERROR');

        return in_array($currentState, array($awaitingPaymentStatus, $failedPaymentStatus, $psError), true);
    }

    /**
     * Post process - validate retry request
     */
    public function postProcess()
    {
        $orderId = (int) Tools::getValue('id_order');

        if (!$orderId) {
            Tools::redirect('index.php?controller=order');
        }

        $order = new Order($orderId);

        if (!Validate::isLoadedObject($order)) {
            Tools::redirect('index.php?controller=order');
        }

        // CRITICAL SECURITY: Verify order belongs to current customer (PrestaShop Rule #1)
        if ($order->id_customer != $this->context->customer->id) {
            PrestaShopLogger::addLog(
                'MorPOS Security: Retry attempt by wrong customer - Order customer: ' .
                $order->id_customer . ', Current customer: ' . $this->context->customer->id,
                3,
                null,
                'Order',
                $order->id,
                true
            );

            Tools::redirect('index.php?controller=order');
        }

        // Verify order is in retryable status (awaiting payment or failed payment)
        if (!$this->isOrderRetryable($order)) {
            $currentState = (int) $order->getCurrentState();

            PrestaShopLogger::addLog(
                'MorPOS: Retry attempt for order not in retryable status - Order: ' .
                $order->id . ', Current status: ' . $currentState,
                2,
                null,
                'Order',
                $order->id,
                true
            );
        }
    }

    /**
     * Display retry page
     */
    public function initContent()
    {
        parent::initContent();

        $orderId = (int) Tools::getValue('id_order');
        $order = new Order($orderId);

        if (!Validate::isLoadedObject($order)) {
            Tools::redirect('index.php?controller=order');
        }

        // Get error message from cookie
        $errorMessage = '';
        if (isset($this->context->cookie->morpos_error_message)) {
            $errorMessage = $this->context->cookie->morpos_error_message;
            unset($this->context->cookie->morpos_error_message);
        }

        // Check if order is retryable
        $canRetry = $this->isOrderRetryable($order);

        $currency = new Currency($order->id_currency);
        $formType = Configuration::get('MORPOS_FORM_TYPE');

        // If order cannot be retried, show error page
        if (!$canRetry) {
            $retryError = $this->module->l(
                'This order is not awaiting payment and cannot be retried.',
                'retry'
            );

            $this->context->smarty->assign(array(
                'retry_error' => $retryError,
                'module_dir' => $this->module->getPathUri(),
            ));

            $this->setTemplate('module:morposgateway/views/templates/front/retry_error.tpl');

            return;
        }

        // Build order details URL - customer can use "Reorder" from there if needed
        $orderDetailUrl = $this->context->link->getPageLink(
            'order-detail',
            true,
            null,
            array('id_order' => $order->id)
        );

        // Otherwise, show retry page with payment form
        // Format price with backward compatibility (PS 1.7.6+ vs older)
        if (method_exists($this->context, 'getCurrentLocale') && $this->context->getCurrentLocale()) {
            $formattedTotal = $this->context->getCurrentLocale()->formatPrice($order->total_paid, $currency->iso_code);
        } else {
            $formattedTotal = Tools::displayPrice($order->total_paid, $currency);
        }

        $this->context->smarty->assign(array(
            'order_id' => $order->id,
            'order_reference' => isset($order->reference) ? $order->reference : $order->id,
            'total' => $formattedTotal,
            'currency_iso' => $currency->iso_code,
            'error_message' => $errorMessage,
            'module_dir' => $this->module->getPathUri(),
            'confirm_url' => $this->context->link->getModuleLink(
                'morposgateway',
                'validate',
                array('id_order' => $order->id, 'ajax' => ($formType === 'embedded' ? 1 : 0)),
                true
            ),
            'redirect_success' => $this->context->link->getPageLink('order-confirmation', true),
            'button_text' => $this->module->l('Retry Payment', 'retry'),
            'text_payment_failed_default' => $this->module->l('Payment failed. Please try again.', 'retry'),
            'text_payment_init_failed' => $this->module->l('Payment initialization failed.', 'retry'),
            'text_network_error' => $this->module->l('Network error. Please check your connection.', 'retry'),
            'form_type' => $formType,
            // Simple alternative: link to order details where they can "Reorder"
            'order_detail_url' => $orderDetailUrl,
            'shop_url' => $this->context->link->getPageLink('index'),
        ));

        $this->setTemplate('module:morposgateway/views/templates/front/retry.tpl');
    }
}
