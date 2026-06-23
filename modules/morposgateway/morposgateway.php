<?php
/**
 * MorPOS Payment Plugin for PrestaShop
 *
 * @author Morpara
 * @copyright 2026 Morpara
 * @license MIT
 * @version 1.0.1
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

require_once dirname(__FILE__) . '/classes/MorposClient.php';
require_once dirname(__FILE__) . '/classes/MorposCurrency.php';
require_once dirname(__FILE__) . '/classes/MorposConversation.php';
require_once dirname(__FILE__) . '/classes/MorposEncryption.php';

class MorposGateway extends PaymentModule
{
    const MODULE_VERSION = '1.0.2';

    /**
     * List of hooks used by this module
     */
    const HOOKS = array(
        'paymentOptions',
        'displayPaymentReturn',
        'header',
        'actionPaymentCCAdd',
        'displayAdminOrderLeft',
        'displayAdminOrderMainBottom',
        'displayOrderConfirmation',
        'displayOrderDetail',
        'displayPDFInvoice',
        'actionObjectShopAddAfter',
    );

    public function __construct()
    {
        $this->name = 'morposgateway';
        $this->tab = 'payments_gateways';
        $this->version = self::MODULE_VERSION;
        $this->author = 'Morpara';
        $this->need_instance = 0;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
        $this->module_key = '';
        $this->controllers = array(
            'validate',
            'callback',
            'retry',
            'proxy',
        );

        parent::__construct();

        $this->displayName = $this->l('MorPOS Payment Plugin');
        $this->description = $this->l('Accept credit card payments through MorPOS Payment Plugin.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
        $this->limited_currencies = MorposCurrency::supportedCurrencies();

        if (
            !Configuration::get('MORPOS_MERCHANT_ID') ||
            !Configuration::get('MORPOS_CLIENT_ID') ||
            !Configuration::get('MORPOS_CLIENT_SECRET') ||
            !Configuration::get('MORPOS_API_KEY')
        ) {
            $this->warning = $this->l('MorPOS Gateway credentials are not configured.');
        }
    }

    /**
     * Install the module
     */
    public function install()
    {
        // Check for required PHP extensions
        $requiredExtensions = array('curl', 'json', 'openssl', 'hash');
        $missingExtensions = array_filter($requiredExtensions, function ($ext) {
            return !extension_loaded($ext);
        });

        if (!empty($missingExtensions)) {
            $this->_errors[] = sprintf(
                $this->l('Cannot install MorPOS Gateway. Missing required PHP extensions: %s'),
                implode(', ', $missingExtensions)
            );

            return false;
        }

        if (
            !parent::install() ||
            !$this->registerHook(static::HOOKS)
        ) {
            return false;
        }

        // Create database table
        if (!$this->createTables()) {
            return false;
        }

        // Add only supported currencies (TRY, USD, EUR, GBP) instead of all currencies
        if (!$this->addSupportedCurrenciesForModule()) {
            return false;
        }

        // Add country and carrier restrictions (standard PrestaShop behavior)
        if (!$this->addCheckboxCountryRestrictionsForModule()) {
            return false;
        }

        if (!$this->addCheckboxCarrierRestrictionsForModule()) {
            return false;
        }

        // Set default configuration values
        Configuration::updateValue('MORPOS_ENABLED', false);
        Configuration::updateValue('MORPOS_TESTMODE', true);
        Configuration::updateValue('MORPOS_MERCHANT_ID', '');
        Configuration::updateValue('MORPOS_CLIENT_ID', '');
        Configuration::updateValue('MORPOS_CLIENT_SECRET', '');
        Configuration::updateValue('MORPOS_API_KEY', '');
        Configuration::updateValue('MORPOS_FORM_TYPE', 'hosted');
        Configuration::updateValue('MORPOS_SUCCESS_STATUS', Configuration::get('PS_OS_PAYMENT'));
        Configuration::updateValue('MORPOS_FAILED_STATUS', Configuration::get('PS_OS_ERROR'));
        Configuration::updateValue('MORPOS_CONNECTION_STATUS', 'setup');

        return true;
    }

    /**
     * Uninstall the module
     */
    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        // Drop database table
        $this->dropTables();

        // Remove configuration
        Configuration::deleteByName('MORPOS_ENABLED');
        Configuration::deleteByName('MORPOS_TESTMODE');
        Configuration::deleteByName('MORPOS_MERCHANT_ID');
        Configuration::deleteByName('MORPOS_CLIENT_ID');
        Configuration::deleteByName('MORPOS_CLIENT_SECRET');
        Configuration::deleteByName('MORPOS_API_KEY');
        Configuration::deleteByName('MORPOS_FORM_TYPE');
        Configuration::deleteByName('MORPOS_SUCCESS_STATUS');
        Configuration::deleteByName('MORPOS_FAILED_STATUS');
        Configuration::deleteByName('MORPOS_CONNECTION_STATUS');

        return true;
    }

    /**
     * Create the conversation attempts table
     */
    private function createTables()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'morpos_conversation_attempt` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `order_id` int(11) NOT NULL,
            `attempt_seq` int(11) NOT NULL DEFAULT 0,
            `conversation_id` varchar(64) NOT NULL,
            `data` text,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `order_conversation_unique` (`order_id`, `conversation_id`),
            KEY `order_id_idx` (`order_id`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

        $result = Db::getInstance()->execute($sql);

        if (!$result) {
            $error = Db::getInstance()->getMsgError();
            PrestaShopLogger::addLog(
                'MorPOS Table Creation Error: ' . $error,
                3,
                null,
                'Module',
                $this->id,
                true
            );
        }

        return $result;
    }

    /**
     * Drop the conversation attempts table
     */
    private function dropTables()
    {
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'morpos_conversation_attempt`';
        return Db::getInstance()->execute($sql);
    }

    /**
     * Add only supported currencies for the module
     * Only enables TRY, USD, EUR, and GBP (if they exist in the shop)
     *
     * @param array $shops
     * @return bool
     */
    private function addSupportedCurrenciesForModule(array $shops = array())
    {
        if (empty($shops)) {
            $shops = Shop::getShops(true, null, true);
        }

        // Get supported currency codes from MorposCurrency
        $supportedCurrencyCodes = MorposCurrency::supportedCurrencies();

        // Get all active currencies from the shop using PrestaShop's Currency class
        $allCurrencies = Currency::getCurrencies(true, true); // active = true, deleted = false

        if (empty($allCurrencies)) {
            // No currencies in shop - log warning but don't fail installation
            PrestaShopLogger::addLog(
                'MorPOS Gateway: No active currencies found in shop during installation',
                2,
                null,
                'Module',
                $this->id,
                true
            );
            return true;
        }

        // Filter to only supported currencies
        $currenciesToAdd = array();
        foreach ($allCurrencies as $currency) {
            // Handle both array and object formats (depends on PrestaShop version)
            $isoCode = is_array($currency) ? $currency['iso_code'] : $currency->iso_code;
            $currencyId = is_array($currency) ? $currency['id_currency'] : $currency->id;
            if (in_array(strtoupper($isoCode), $supportedCurrencyCodes)) {
                $currenciesToAdd[] = (int) $currencyId;
            }
        }

        if (empty($currenciesToAdd)) {
            // No supported currencies found - log warning but don't fail installation
            PrestaShopLogger::addLog(
                'MorPOS Gateway: None of the supported currencies (TRY, USD, EUR, GBP) are installed in the shop',
                2,
                null,
                'Module',
                $this->id,
                true
            );
            return true;
        }

        // Delete existing currency restrictions for this module before inserting
        Db::getInstance()->delete(
            'module_currency',
            'id_module = ' . (int) $this->id
        );

        // Insert supported currencies for each shop using PrestaShop's insert method
        foreach ($shops as $shopId) {
            foreach ($currenciesToAdd as $currencyId) {
                $result = Db::getInstance()->insert(
                    'module_currency',
                    array(
                        'id_module' => (int) $this->id,
                        'id_shop' => (int) $shopId,
                        'id_currency' => (int) $currencyId,
                    ),
                    false,
                    true,
                    Db::INSERT_IGNORE
                );

                if (!$result) {
                    PrestaShopLogger::addLog(
                        'MorPOS Gateway: Failed to add currency restriction for currency ID ' . $currencyId,
                        3,
                        null,
                        'Module',
                        $this->id,
                        true
                    );
                    // Don't fail on duplicate - just continue
                }
            }
        }

        return true;
    }

    /**
     * Override parent method to handle duplicate entries on reset
     * Adds carrier restrictions for this module
     *
     * @param array $shops List of shop IDs
     * @return bool
     */
    public function addCheckboxCarrierRestrictionsForModule(array $shops = [])
    {
        if (empty($shops)) {
            $shops = Shop::getShops(true, null, true);
        }

        // Delete existing carrier restrictions for this module and these shops
        foreach ($shops as $shopId) {
            Db::getInstance()->delete(
                'module_carrier',
                'id_module = ' . (int) $this->id . ' AND id_shop = ' . (int) $shopId
            );
        }

        // Call parent implementation to add restrictions
        return parent::addCheckboxCarrierRestrictionsForModule($shops);
    }

    /**
     * Override parent method to handle duplicate entries on reset
     * Adds country restrictions for this module
     *
     * @param array $shops List of shop IDs
     * @return bool
     */
    public function addCheckboxCountryRestrictionsForModule(array $shops = [])
    {
        if (empty($shops)) {
            $shops = Shop::getShops(true, null, true);
        }

        // Delete existing country restrictions for this module and these shops
        foreach ($shops as $shopId) {
            Db::getInstance()->delete(
                'module_country',
                'id_module = ' . (int) $this->id . ' AND id_shop = ' . (int) $shopId
            );
        }

        // Call parent implementation to add restrictions
        return parent::addCheckboxCountryRestrictionsForModule($shops);
    }

    /**
     * Check if a specific currency is supported by this module
     *
     * @param Cart|int $cart Cart object or currency ID
     * @return bool
     */
    public function checkCurrency($cart)
    {
        // Handle both Cart object and currency ID
        if (is_object($cart)) {
            $currencyId = (int) $cart->id_currency;
        } else {
            $currencyId = (int) $cart;
        }

        // Get currency object
        $currency = new Currency($currencyId);

        if (!Validate::isLoadedObject($currency)) {
            return false;
        }

        // Check if currency is in supported list
        $supportedCurrencies = MorposCurrency::supportedCurrencies();
        if (!in_array(strtoupper($currency->iso_code), $supportedCurrencies)) {
            return false;
        }

        // Check if currency is enabled for this module in database
        $enabledCurrencies = $this->getCurrency($currencyId);

        if (is_array($enabledCurrencies)) {
            foreach ($enabledCurrencies as $enabledCurrency) {
                if ($currency->id == $enabledCurrency['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Module configuration page
     */
    public function getContent()
    {
        $output = '';

        // Handle form submission
        if (Tools::isSubmit('submit' . $this->name)) {
            $output .= $this->processConfiguration();
        }

        // Handle AJAX test connection
        if (Tools::isSubmit('ajax') && Tools::getValue('action') == 'testConnection') {
            $this->ajaxTestConnection();
        }

        // Display configuration form
        return $output . $this->displayConfigurationForm();
    }

    /**
     * Order status for a successful (paid) payment.
     *
     * @return int Order state id
     */
    public static function getSuccessStatusId()
    {
        return (int) Configuration::get('MORPOS_SUCCESS_STATUS') ?: (int) Configuration::get('PS_OS_PAYMENT');
    }

    /**
     * Order status for a failed payment. Shared by callback/retry/validate.
     * Invariant: never equal to the paid status (falls back to PS_OS_ERROR).
     *
     * @return int Order state id
     */
    public static function getFailedStatusId()
    {
        $failStatusId = (int) Configuration::get('MORPOS_FAILED_STATUS') ?: (int) Configuration::get('PS_OS_ERROR');

        if ($failStatusId === self::getSuccessStatusId()) {
            $failStatusId = (int) Configuration::get('PS_OS_ERROR');
        }

        return $failStatusId;
    }

    /**
     * Process configuration form submission
     * Includes input validation for security
     *
     * @return string HTML output (confirmation or error message)
     */
    protected function processConfiguration()
    {
        $errors = array();

        $enabled = (bool) Tools::getValue('MORPOS_ENABLED');
        $testmode = (bool) Tools::getValue('MORPOS_TESTMODE');
        $merchantId = trim(Tools::getValue('MORPOS_MERCHANT_ID'));
        $clientId = trim(Tools::getValue('MORPOS_CLIENT_ID'));
        $clientSecret = trim(Tools::getValue('MORPOS_CLIENT_SECRET'));
        $apiKey = trim(Tools::getValue('MORPOS_API_KEY'));
        $formType = Tools::getValue('MORPOS_FORM_TYPE');
        $successStatus = (int) Tools::getValue('MORPOS_SUCCESS_STATUS');
        $failedStatus = (int) Tools::getValue('MORPOS_FAILED_STATUS');

        if (!in_array($formType, array('hosted', 'embedded'), true)) {
            $errors[] = $this->l('Invalid form type selected.');
            $formType = 'hosted';
        }

        if (!Validate::isUnsignedId($successStatus)) {
            $errors[] = $this->l('Invalid success order status.');
        }

        if (!Validate::isUnsignedId($failedStatus)) {
            $errors[] = $this->l('Invalid failed order status.');
        }

        // Success and failed statuses must differ, and success must be paid / failed must not be.
        if (Validate::isUnsignedId($successStatus) && Validate::isUnsignedId($failedStatus)
            && $successStatus === $failedStatus) {
            $errors[] = $this->l('The success and failed order statuses must be different.');
        }

        if (Validate::isUnsignedId($successStatus)) {
            $successState = new OrderState($successStatus);
            if (Validate::isLoadedObject($successState) && !$successState->paid) {
                $errors[] = $this->l('The success order status must be a paid status.');
            }
        }
        if (Validate::isUnsignedId($failedStatus)) {
            $failedState = new OrderState($failedStatus);
            if (Validate::isLoadedObject($failedState) && $failedState->paid) {
                $errors[] = $this->l('The failed order status must not be a paid status.');
            }
        }

        if (!empty($errors)) {
            return $this->displayError(implode('<br>', $errors));
        }

        // Test connection before saving
        $connectionStatus = 'setup';
        if (!empty($merchantId) && !empty($clientId) && !empty($clientSecret) && !empty($apiKey)) {
            $testResult = $this->performConnectionTest($clientId, $clientSecret, $merchantId, $apiKey, $testmode);
            $connectionStatus = $testResult['success'] ? 'ok' : 'fail';
        }

        Configuration::updateValue('MORPOS_ENABLED', $enabled);
        Configuration::updateValue('MORPOS_TESTMODE', $testmode);
        Configuration::updateValue('MORPOS_MERCHANT_ID', $merchantId);
        Configuration::updateValue('MORPOS_CLIENT_ID', $clientId);
        Configuration::updateValue('MORPOS_CLIENT_SECRET', $clientSecret);
        Configuration::updateValue('MORPOS_API_KEY', $apiKey);
        Configuration::updateValue('MORPOS_FORM_TYPE', $formType);
        Configuration::updateValue('MORPOS_SUCCESS_STATUS', $successStatus);
        Configuration::updateValue('MORPOS_FAILED_STATUS', $failedStatus);
        Configuration::updateValue('MORPOS_CONNECTION_STATUS', $connectionStatus);

        return $this->displayConfirmation($this->l('Settings updated successfully.'));
    }

    /**
     * AJAX handler for testing connection
     * Includes CSRF token verification for security
     */
    protected function ajaxTestConnection()
    {
        // Security: Verify this is an authorized admin request
        if (!$this->context->employee || !$this->context->employee->id) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(array('success' => false, 'error' => $this->l('Unauthorized access')));
            exit;
        }

        // Security: Verify CSRF token
        $adminToken = Tools::getAdminTokenLite('AdminModules');
        $requestToken = Tools::getValue('token', '');
        if (empty($requestToken) || $requestToken !== $adminToken) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(array('success' => false, 'error' => $this->l('Invalid security token')));
            exit;
        }

        header('Content-Type: application/json');

        $merchantId = Tools::getValue('merchant_id');
        $clientId = Tools::getValue('client_id');
        $clientSecret = Tools::getValue('client_secret');
        $apiKey = Tools::getValue('api_key');
        $testmode = Tools::getValue('testmode') === 'yes';

        $result = $this->performConnectionTest($clientId, $clientSecret, $merchantId, $apiKey, $testmode);

        echo json_encode(array(
            'status' => $result['success'] ? 'ok' : 'fail',
            'success' => $result['success'],
            'message' => isset($result['message']) ? $result['message'] : '',
            'error' => isset($result['error']) ? $result['error'] : null,
        ));
        exit;
    }

    /**
     * Perform connection test
     */
    protected function performConnectionTest($clientId, $clientSecret, $merchantId, $apiKey, $testmode)
    {
        if (empty($merchantId) || empty($clientId) || empty($clientSecret) || empty($apiKey)) {
            return array(
                'success' => false,
                'error' => $this->l('Please fill all credential fields.')
            );
        }

        try {
            $client = new MorposClient(
                $clientId,
                $clientSecret,
                $merchantId,
                '',
                $apiKey,
                $testmode ? 'sandbox' : 'production'
            );

            $response = $client->makeTestConnection();

            if (isset($response['ok']) && $response['ok'] === true) {
                return array(
                    'success' => true,
                    'message' => $this->l('Connection successful!')
                );
            } else {
                return array(
                    'success' => false,
                    'error' => $this->l('Connection failed.')
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $this->l('Connection failed: ') . $e->getMessage()
            );
        }
    }

    /**
     * Display configuration form
     */
    protected function displayConfigurationForm()
    {
        // Get current configuration
        $enabled = Configuration::get('MORPOS_ENABLED');
        $testmode = Configuration::get('MORPOS_TESTMODE');
        $merchantId = Configuration::get('MORPOS_MERCHANT_ID');
        $clientId = Configuration::get('MORPOS_CLIENT_ID');
        $clientSecret = Configuration::get('MORPOS_CLIENT_SECRET');
        $apiKey = Configuration::get('MORPOS_API_KEY');
        $formType = Configuration::get('MORPOS_FORM_TYPE');
        $successStatus = Configuration::get('MORPOS_SUCCESS_STATUS');
        $failedStatus = Configuration::get('MORPOS_FAILED_STATUS');
        $connectionStatus = Configuration::get('MORPOS_CONNECTION_STATUS');

        // Get order statuses
        $orderStatuses = OrderState::getOrderStates($this->context->language->id);

        // Get requirements info
        $requirements = $this->getRequirementsInfo();

        // Assign to smarty
        $this->context->smarty->assign(array(
            'module_dir' => $this->_path,
            'module_name' => $this->displayName,
            'module_description' => $this->description,
            'module_version' => $this->version,
            'enabled' => $enabled,
            'testmode' => $testmode,
            'merchant_id' => $merchantId,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'api_key' => $apiKey,
            'form_type' => $formType,
            'success_status' => $successStatus,
            'failed_status' => $failedStatus,
            'connection_status' => $connectionStatus,
            'order_statuses' => $orderStatuses,
            'requirements' => $requirements,
            'ajax_url' => $this->context->link->getAdminLink('AdminModules', true) .
                '&configure=' . $this->name . '&ajax=1',
            'submit_action' => 'submit' . $this->name,
            'form_action' => AdminController::$currentIndex . '&configure=' . $this->name .
                '&token=' . Tools::getAdminTokenLite('AdminModules'),
        ));

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    /**
     * Get system requirements information
     */
    protected function getRequirementsInfo()
    {
        $targets = array(
            'php' => array(
                'required' => '7.4',
                'recommended' => '8.2',
            ),
            'ps' => array(
                'required' => '1.7',
                'recommended' => '8.0',
            ),
            'tls' => array(
                'required' => '1.2',
                'recommended' => '1.3',
            ),
        );

        $current = array(
            'php' => PHP_VERSION,
            'ps' => _PS_VERSION_,
            'tls' => $this->detectTlsCapability(),
        );

        $ver_status = function ($cur, $req, $rec) {
            if ($cur === null) {
                return array('class' => 'morpos-danger', 'hint' => $this->l('Not detected'));
            }

            if (version_compare($cur, $req, '<')) {
                return array('class' => 'morpos-danger', 'hint' => $this->l('Below required'));
            }

            if (version_compare($cur, $rec, '<')) {
                return array('class' => 'morpos-warning', 'hint' => $this->l('Allowed but discouraged'));
            }

            return array('class' => 'morpos-ok', 'hint' => $this->l('Meets recommended'));
        };

        $tls_status = function ($current, $required, $recommended) {
            if (!$current || $current['min_tls'] === 'unknown') {
                return array(
                    'class' => 'morpos-danger',
                    'hint' => $this->l('Unable to verify TLS support')
                );
            }

            if (version_compare($current['min_tls'], $required, '<')) {
                return array('class' => 'morpos-danger', 'hint' => $this->l('Below required'));
            }

            if (version_compare($current['min_tls'], $recommended, '<')) {
                return array('class' => 'morpos-warning', 'hint' => $this->l('Allowed but discouraged'));
            }

            return array('class' => 'morpos-ok', 'hint' => $this->l('Meets recommended'));
        };

        return array(
            array(
                'label' => $this->l('PHP'),
                'cur' => $current['php'],
                'req' => $targets['php']['required'] . '+',
                'rec' => $targets['php']['recommended'] . '+',
                'status' => $ver_status($current['php'], $targets['php']['required'], $targets['php']['recommended']),
            ),
            array(
                'label' => $this->l('PrestaShop'),
                'cur' => $current['ps'],
                'req' => $targets['ps']['required'] . '+',
                'rec' => $targets['ps']['recommended'] . '+',
                'status' => $ver_status($current['ps'], $targets['ps']['required'], $targets['ps']['recommended']),
            ),
            array(
                'label' => $this->l('TLS'),
                'cur' => $current['tls'] ? $current['tls']['label'] : $this->l('Unknown'),
                'req' => 'TLS ' . $targets['tls']['required'] . '+',
                'rec' => 'TLS ' . $targets['tls']['recommended'] . '+',
                'status' => $tls_status($current['tls'], $targets['tls']['required'], $targets['tls']['recommended']),
            ),
        );
    }

    /**
     * Detect TLS capability
     */
    protected function detectTlsCapability()
    {
        $openssl_text = defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : null;
        $openssl_num = defined('OPENSSL_VERSION_NUMBER') ? OPENSSL_VERSION_NUMBER : null;
        $curl_info = function_exists('curl_version') ? curl_version() : null;

        $min_tls = 'unknown';
        if ($openssl_num) {
            if ($openssl_num < 0x1000100f) {
                $min_tls = '1.0';
            } elseif ($openssl_num < 0x1010100f) {
                $min_tls = '1.2';
            } else {
                $min_tls = '1.3';
            }
        }

        $label_parts = array();
        if ($openssl_text) {
            $label_parts[] = $openssl_text;
            if ($min_tls !== 'unknown') {
                $label_parts[] = sprintf('(TLS %s)', $min_tls);
            }
        } elseif ($curl_info && !empty($curl_info['ssl_version'])) {
            $label_parts[] = $curl_info['ssl_version'];
        } elseif ($curl_info && ($curl_info['features'] & CURL_VERSION_SSL)) {
            $label_parts[] = $this->l('SSL/TLS available (version unknown)');
        } else {
            $label_parts[] = $this->l('No SSL/TLS detected');
        }

        return array(
            'label' => implode(' ', $label_parts),
            'min_tls' => $min_tls,
        );
    }

    /**
     * Hook for payment options (PS 1.7+)
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active || !Configuration::get('MORPOS_ENABLED')) {
            return;
        }

        // Check if current cart currency is supported
        if (!isset($params['cart']) || !$this->checkCurrency($params['cart'])) {
            return;
        }

        $payment_options = array(
            $this->getEmbeddedPaymentOption(),
        );

        return $payment_options;
    }

    /**
     * Get payment option - handles both hosted and embedded payment types
     */
    public function getEmbeddedPaymentOption()
    {
        $formType = Configuration::get('MORPOS_FORM_TYPE');
        $paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();

        if ($formType === 'hosted') {
            // Hosted payment - direct redirect to validate controller, then to gateway
            $paymentOption->setModuleName($this->name)
                ->setCallToActionText($this->l('Pay with Credit/Debit Card'))
                ->setAction($this->context->link->getModuleLink($this->name, 'validate', array(), true))
                ->setAdditionalInformation($this->fetch('module:morposgateway/views/templates/hook/payment_info.tpl'))
                ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/card-logos-small.png'));
        } else {
            // Embedded payment - iframe will be shown inline in checkout after Place Order button click
            // JavaScript will intercept the payment-confirmation event and handle via AJAX
            $this->context->smarty->assign(array(
                'validate_url' => $this->context->link->getModuleLink($this->name, 'validate', array('ajax' => 1), true),
                'module_name' => $this->name,
                'form_type' => 'embedded',
                'title_payment_error' => $this->l('Payment Error'),
                'title_system_error' => $this->l('System Error'),
                'title_network_error' => $this->l('Network Error'),
                'title_payment_failed' => $this->l('Payment Failed'),
                'text_payment_init_failed' => $this->l('Payment initialization failed. Please try again.'),
                'text_unable_to_connect' => $this->l('Unable to connect to payment server. Please try again.'),
                'text_check_connection' => $this->l('Unable to connect to payment server. Please check your connection and try again.'),
            ));

            $paymentOption->setModuleName($this->name)
                ->setCallToActionText($this->l('Pay with Credit/Debit Card'))
                ->setAction($this->context->link->getModuleLink($this->name, 'validate', array(), true))
                ->setAdditionalInformation($this->fetch('module:morposgateway/views/templates/hook/payment_iframe_container.tpl'))
                ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/card-logos-small.png'));
        }

        return $paymentOption;
    }

    /**
     * Hook for payment return - shown on order confirmation page (only for successful payments)
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayPaymentReturn($params)
    {
        if (!$this->active || empty($params['order'])) {
            return '';
        }

        /** @var Order $order */
        $order = $params['order'];

        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $this->context->smarty->assign(array(
            'order_reference' => isset($order->reference) ? $order->reference : $order->id,
        ));

        return $this->fetch('module:morposgateway/views/templates/hook/payment_return.tpl');
    }

    /**
     * Hook for header
     */
    public function hookHeader()
    {
        if (
            $this->context->controller instanceof OrderController ||
            $this->context->controller instanceof OrderConfirmationController
        ) {
            $this->context->controller->registerStylesheet(
                'morposgateway-style',
                'modules/' . $this->name . '/views/css/morpos.css',
                array('media' => 'all', 'priority' => 200)
            );
            $this->context->controller->registerJavascript(
                'morposgateway-script',
                'modules/' . $this->name . '/views/js/morpos.js',
                array('position' => 'bottom', 'priority' => 200)
            );
        }
    }

    /**
     * Hook called after OrderPayment is created - add additional card details
     *
     * @param array $params
     */
    public function hookActionPaymentCCAdd(array $params)
    {
        if (empty($params['paymentCC'])) {
            return;
        }

        /** @var OrderPayment $orderPayment */
        $orderPayment = $params['paymentCC'];

        if (false === Validate::isLoadedObject($orderPayment) || empty($orderPayment->order_reference)) {
            return;
        }

        // Verify this order belongs to our module
        /** @var Order[] $orderCollection */
        $orderCollection = Order::getByReference($orderPayment->order_reference);

        foreach ($orderCollection as $order) {
            if ($this->name !== $order->module) {
                return;
            }
        }

        // Card details are already set in callback.php addOrderPayment method
        // This hook can be used for additional processing if needed
    }

    /**
     * Hook to display payment info in admin order view (PS < 1.7.7)
     *
     * @param array $params
     * @return string
     */
    public function hookDisplayAdminOrderLeft(array $params)
    {
        if (empty($params['id_order'])) {
            return '';
        }

        $order = new Order((int) $params['id_order']);

        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $paymentDetails = $this->getPaymentDetailsForOrder($order);

        $this->context->smarty->assign(array(
            'moduleName' => $this->name,
            'moduleDisplayName' => $this->displayName,
            'moduleLogoSrc' => $this->getPathUri() . 'logo.png',
            'paymentDetails' => $paymentDetails,
        ));

        return $this->fetch('module:morposgateway/views/templates/hook/displayAdminOrderLeft.tpl');
    }

    /**
     * Hook to display payment info in admin order view (PS >= 1.7.7)
     *
     * @param array $params
     * @return string
     */
    public function hookDisplayAdminOrderMainBottom(array $params)
    {
        if (empty($params['id_order'])) {
            return '';
        }

        $order = new Order((int) $params['id_order']);

        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $paymentDetails = $this->getPaymentDetailsForOrder($order);

        $this->context->smarty->assign(array(
            'moduleName' => $this->name,
            'moduleDisplayName' => $this->displayName,
            'moduleLogoSrc' => $this->getPathUri() . 'logo.png',
            'paymentDetails' => $paymentDetails,
        ));

        return $this->fetch('module:morposgateway/views/templates/hook/displayAdminOrderMainBottom.tpl');
    }

    /**
     * Hook to display transaction info on order confirmation page
     *
     * @param array $params
     * @return string
     */
    public function hookDisplayOrderConfirmation(array $params)
    {
        if (empty($params['order'])) {
            return '';
        }

        /** @var Order $order */
        $order = $params['order'];

        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $paymentDetails = $this->getPaymentDetailsForOrder($order);

        $this->context->smarty->assign(array(
            'moduleName' => $this->name,
            'paymentDetails' => $paymentDetails,
        ));

        return $this->fetch('module:morposgateway/views/templates/hook/displayOrderConfirmation.tpl');
    }

    /**
     * Hook to display transaction info on order detail page (customer account)
     *
     * @param array $params
     * @return string
     */
    public function hookDisplayOrderDetail(array $params)
    {
        if (empty($params['order'])) {
            return '';
        }

        /** @var Order $order */
        $order = $params['order'];

        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $paymentDetails = $this->getPaymentDetailsForOrder($order);

        $this->context->smarty->assign(array(
            'moduleName' => $this->name,
            'paymentDetails' => $paymentDetails,
        ));

        return $this->fetch('module:morposgateway/views/templates/hook/displayOrderDetail.tpl');
    }

    /**
     * Hook to display transaction info on PDF invoice
     *
     * @param array $params
     * @return string
     */
    public function hookDisplayPDFInvoice(array $params)
    {
        if (empty($params['object'])) {
            return '';
        }

        /** @var OrderInvoice $orderInvoice */
        $orderInvoice = $params['object'];

        if (false === Validate::isLoadedObject($orderInvoice)) {
            return '';
        }

        $order = $orderInvoice->getOrder();

        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $paymentDetails = $this->getPaymentDetailsForOrder($order);

        $this->context->smarty->assign(array(
            'moduleName' => $this->name,
            'paymentDetails' => $paymentDetails,
        ));

        return $this->fetch('module:morposgateway/views/templates/hook/displayPDFInvoice.tpl');
    }

    /**
     * Get payment details for an order
     *
     * @param Order $order
     * @return array
     */
    protected function getPaymentDetailsForOrder(Order $order)
    {
        $details = array(
            'payment_id' => '',
            'conversation_id' => '',
            'bank_reference' => '',
            'card_number' => '',
            'amount' => '',
            'date' => '',
            'installments' => '',
        );

        $payments = $order->getOrderPaymentCollection();
        if ($payments->count()) {
            // Get the last payment record
            $orderPayment = $payments->getLast() ?: $payments->getFirst();

            if ($orderPayment) {
                // Standard OrderPayment fields
                $details['payment_id'] = $orderPayment->transaction_id;
                $details['card_number'] = $orderPayment->card_number;
                $details['amount'] = $this->formatPriceCompat(
                    $orderPayment->amount,
                    (int) $orderPayment->id_currency
                );
                $details['date'] = Tools::displayDate($orderPayment->date_add, true);
            }
        }

        // Fetch extra payment info from conversation_attempt table
        // This keeps OrderPayment fields clean and avoids showing JSON in customer UI
        $conversationData = MorposConversation::getLatestPaymentResultForOrder((int) $order->id);
        if (!empty($conversationData)) {
            $details['conversation_id'] = isset($conversationData['conversation_id'])
                ? $conversationData['conversation_id'] : '';
            
            if (isset($conversationData['payment_result']) && is_array($conversationData['payment_result'])) {
                $result = $conversationData['payment_result'];
                if (isset($result['bankReference'])) {
                    $details['bank_reference'] = $result['bankReference'];
                }
                if (isset($result['installments'])) {
                    $details['installments'] = $result['installments'];
                }
                // If payment_id is empty in OrderPayment, try to get from conversation
                if (empty($details['payment_id']) && isset($result['paymentId'])) {
                    $details['payment_id'] = $result['paymentId'];
                }
            }
        }

        return $details;
    }

    /**
     * Format price with backward compatibility for PS 1.7.0 - 1.7.5
     * Uses Locale::formatPrice() for PS 1.7.6+ and falls back to Tools::displayPrice() for older versions
     *
     * @param float $price The price to format
     * @param string|int $currency Currency ISO code or Currency ID
     * @return string Formatted price
     */
    public function formatPriceCompat($price, $currency)
    {
        // PS 1.7.6+ has getCurrentLocale()
        if (method_exists($this->context, 'getCurrentLocale') && $this->context->getCurrentLocale()) {
            $currencyIso = is_numeric($currency) ? Currency::getIsoCodeById((int) $currency) : $currency;
            return $this->context->getCurrentLocale()->formatPrice($price, $currencyIso);
        }

        // Fallback for PS 1.7.0 - 1.7.5
        return Tools::displayPrice($price, $currency);
    }

    /**
     * Hook called after a new Shop is created - handles multi-shop support
     *
     * @param array $params
     * @return void
     */
    public function hookActionObjectShopAddAfter(array $params)
    {
        if (empty($params['object'])) {
            return;
        }

        /** @var Shop $shop */
        $shop = $params['object'];

        if (false === Validate::isLoadedObject($shop)) {
            return;
        }

        // Add restrictions for the new shop
        $this->addCheckboxCarrierRestrictionsForModule([(int) $shop->id]);
        $this->addCheckboxCountryRestrictionsForModule([(int) $shop->id]);
        $this->addSupportedCurrenciesForModule([(int) $shop->id]);
    }

    /**
     * Add a private message to an order (visible only in admin panel)
     * Centralized method to avoid code duplication in controllers
     *
     * @param Order $order The order object
     * @param string $message Message content (HTML allowed: <br>)
     * @return bool Success status
     */
    public function addOrderMessage($order, $message)
    {
        if (!Validate::isLoadedObject($order) || empty($message)) {
            return false;
        }

        $msg = new Message();
        $msg->message = strip_tags($message, '<br>');
        $msg->id_cart = (int) $order->id_cart;
        $msg->id_order = (int) $order->id;
        $msg->id_customer = (int) $order->id_customer;
        $msg->private = 1; // Private = visible only to employees in admin panel

        try {
            return $msg->add();
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'MorPOS: Failed to add order message: ' . $e->getMessage(),
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
     * Check if this payment option is still available
     * in case the customer changed address before checkout
     *
     * @return bool
     */
    public function checkIfPaymentOptionIsAvailable()
    {
        if (!Configuration::get('MORPOS_ENABLED')) {
            return false;
        }

        $modules = Module::getPaymentModules();

        if (empty($modules)) {
            return false;
        }

        foreach ($modules as $module) {
            if (isset($module['name']) && $this->name === $module['name']) {
                return true;
            }
        }

        return false;
    }
}
