<?php
/**
 * MorPOS Payment Proxy Controller
 *
 * This controller acts as a proxy/intermediate route to solve SameSite cookie restrictions
 * when the payment gateway redirects back to the store after a cross-site POST request.
 *
 * Problem: Modern browsers with SameSite=Lax/Strict cookies won't send session cookies
 * on cross-site POST requests, causing PrestaShop to lose the checkout session.
 *
 * Solution: The callback controller encrypts session data and redirects to this proxy
 * via GET request. Since it's same-origin GET, cookies are sent, and this controller
 * can set session variables and redirect to the final destination.
 *
 * Flow:
 * 1. Payment gateway POSTs to /module/morposgateway/callback
 * 2. Callback processes payment, creates encrypted data package
 * 3. Callback redirects to /module/morposgateway/proxy?data=<encrypted>
 * 4. Proxy (same-origin GET) decrypts data, sets session/cookies, redirects to final page
 *
 * Compatible with PrestaShop 1.7.x through 9.x
 *
 * @author Morpara
 * @copyright 2026 Morpara
 * @license MIT
 */

class MorposGatewayProxyModuleFrontController extends ModuleFrontController
{
    /**
     * @var bool SSL required
     */
    public $ssl = true;

    /**
     * Process the proxy redirect
     */
    public function postProcess()
    {
        $encryptedData = Tools::getValue('data', '');

        if (empty($encryptedData)) {
            PrestaShopLogger::addLog(
                'MorPOS Proxy: Missing data parameter',
                3,
                null,
                'MorposProxy',
                null,
                true
            );

            $this->redirectToCheckout();

            return;
        }

        // Decode and validate the encrypted data
        $data = MorposEncryption::decodeProxyData($encryptedData);

        if ($data === false) {
            PrestaShopLogger::addLog(
                'MorPOS Proxy: Failed to decode proxy data',
                3,
                null,
                'MorposProxy',
                null,
                true
            );

            $this->redirectToCheckout();

            return;
        }

        // Set error message in cookie if present
        if (!empty($data['error'])) {
            $this->context->cookie->morpos_error_message = $data['error'];
        }

        // Determine redirect URL
        $redirectUrl = $this->buildRedirectUrl($data);

        // Redirect to final destination
        Tools::redirect($redirectUrl);
        exit;
    }

    /**
     * Build the redirect URL from decrypted data
     *
     * @param array $data Decrypted proxy data
     * @return string Final redirect URL
     */
    protected function buildRedirectUrl($data)
    {
        $route = isset($data['route']) ? $data['route'] : '';

        // Handle different route types
        switch ($route) {
            case 'order-confirmation':
            case 'orderconfirmation':
                return $this->buildOrderConfirmationUrl($data);

            case 'retry':
                return $this->buildRetryUrl($data);

            case 'checkout':
            case 'order':
                return $this->context->link->getPageLink('order', true);

            default:
                // If route looks like a full URL, validate and use it
                if (filter_var($route, FILTER_VALIDATE_URL)) {
                    // Security: Only allow same-domain URLs
                    $routeHost = parse_url($route, PHP_URL_HOST);
                    $shopHost = parse_url($this->context->link->getPageLink('index', true), PHP_URL_HOST);

                    if ($routeHost === $shopHost) {
                        return $route;
                    }
                }

                // For PrestaShop page links, try to build them
                if (strpos($route, '/') === false) {
                    return $this->context->link->getPageLink($route, true);
                }

                // Default fallback
                return $this->context->link->getPageLink('order', true);
        }
    }

    /**
     * Build order confirmation URL
     *
     * @param array $data Proxy data with order details
     * @return string Order confirmation URL
     */
    protected function buildOrderConfirmationUrl($data)
    {
        $params = array();

        if (!empty($data['cart_id'])) {
            $params['id_cart'] = (int) $data['cart_id'];
        }

        if (!empty($data['order_id'])) {
            $params['id_order'] = (int) $data['order_id'];

            // Get module ID
            $params['id_module'] = (int) $this->module->id;

            // If secure_key not provided, try to get it from order
            if (empty($data['secure_key'])) {
                $order = new Order((int) $data['order_id']);
                if (Validate::isLoadedObject($order)) {
                    $params['key'] = $order->secure_key;
                    if (empty($params['id_cart'])) {
                        $params['id_cart'] = (int) $order->id_cart;
                    }
                }
            } else {
                $params['key'] = $data['secure_key'];
            }
        }

        return $this->context->link->getPageLink('order-confirmation', true, null, $params);
    }

    /**
     * Build retry page URL
     *
     * @param array $data Proxy data
     * @return string Retry page URL
     */
    protected function buildRetryUrl($data)
    {
        $params = array();

        if (!empty($data['order_id'])) {
            $params['id_order'] = (int) $data['order_id'];
        }

        return $this->context->link->getModuleLink(
            $this->module->name,
            'retry',
            $params,
            true
        );
    }

    /**
     * Fallback redirect to checkout page
     */
    protected function redirectToCheckout()
    {
        Tools::redirect($this->context->link->getPageLink('order', true));
        exit;
    }
}
