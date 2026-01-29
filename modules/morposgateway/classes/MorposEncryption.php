<?php
/**
 * MorPOS Encryption Helper
 *
 * Provides AES-256-CBC encryption/decryption for secure data transfer
 * across cross-site redirects (SameSite cookie workaround via proxy).
 *
 * Compatible with PrestaShop 1.7.x through 9.x
 *
 * @author Morpara
 * @copyright 2026 Morpara
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MorposEncryption
{
    /**
     * Encryption method
     */
    const METHOD = 'AES-256-CBC';

    /**
     * Get the secret key for encryption/decryption
     * Uses PrestaShop's _COOKIE_KEY_ which is unique per installation
     *
     * @return string Secret key
     */
    protected static function getSecret()
    {
        // _COOKIE_KEY_ is defined in config/settings.inc.php and is unique per PrestaShop installation
        // It's available across all PS 1.7+ versions
        return defined('_COOKIE_KEY_') ? _COOKIE_KEY_ : '';
    }

    /**
     * Encrypt data for secure transmission
     *
     * @param string $data Data to encrypt (typically JSON string)
     * @return string Base64-encoded encrypted data (URL-safe)
     */
    public static function encrypt($data)
    {
        $secret = self::getSecret();
        if (empty($secret)) {
            PrestaShopLogger::addLog(
                'MorPOS Encryption: _COOKIE_KEY_ not available',
                3,
                null,
                'MorposEncryption',
                null,
                true
            );

            return '';
        }

        $method = self::METHOD;
        $ivLength = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivLength);

        $encrypted = openssl_encrypt($data, $method, $secret, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            PrestaShopLogger::addLog(
                'MorPOS Encryption: openssl_encrypt failed',
                3,
                null,
                'MorposEncryption',
                null,
                true
            );

            return '';
        }

        // Combine IV + encrypted data and encode
        // Use URL-safe base64 encoding (replace + with -, / with _, remove padding =)
        $combined = $iv . $encrypted;

        return rtrim(strtr(base64_encode($combined), '+/', '-_'), '=');
    }

    /**
     * Decrypt data
     *
     * @param string $encryptedData Base64-encoded encrypted data
     * @return string|false Decrypted data or false on failure
     */
    public static function decrypt($encryptedData)
    {
        $secret = self::getSecret();
        if (empty($secret) || empty($encryptedData)) {
            return false;
        }

        // Decode URL-safe base64
        $encryptedData = strtr($encryptedData, '-_', '+/');
        // Add padding if needed
        $padding = strlen($encryptedData) % 4;
        if ($padding > 0) {
            $encryptedData .= str_repeat('=', 4 - $padding);
        }

        $combined = base64_decode($encryptedData, true);
        if ($combined === false) {
            PrestaShopLogger::addLog(
                'MorPOS Decryption: base64_decode failed',
                3,
                null,
                'MorposEncryption',
                null,
                true
            );

            return false;
        }

        $method = self::METHOD;
        $ivLength = openssl_cipher_iv_length($method);

        if (strlen($combined) < $ivLength) {
            PrestaShopLogger::addLog(
                'MorPOS Decryption: data too short',
                3,
                null,
                'MorposEncryption',
                null,
                true
            );

            return false;
        }

        $iv = substr($combined, 0, $ivLength);
        $encrypted = substr($combined, $ivLength);

        $decrypted = openssl_decrypt($encrypted, $method, $secret, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            PrestaShopLogger::addLog(
                'MorPOS Decryption: openssl_decrypt failed',
                3,
                null,
                'MorposEncryption',
                null,
                true
            );

            return false;
        }

        return $decrypted;
    }

    /**
     * Build encrypted proxy URL
     *
     * @param Context $context PrestaShop context
     * @param string $moduleName Module name
     * @param string $route Target route to redirect to after proxy
     * @param string|null $errorMessage Error message to display (optional)
     * @param int|null $orderId Order ID (optional)
     * @param int|null $cartId Cart ID (optional)
     * @param string|null $secureKey Order secure key (optional)
     * @return string Full proxy URL with encrypted data parameter
     */
    public static function buildProxyUrl(
        $context,
        $moduleName,
        $route,
        $errorMessage = null,
        $orderId = null,
        $cartId = null,
        $secureKey = null
    ) {
        $data = array(
            'route' => $route,
            'timestamp' => time(),
        );

        if ($errorMessage !== null) {
            $data['error'] = $errorMessage;
        }

        if ($orderId !== null) {
            $data['order_id'] = (int) $orderId;
        }

        if ($cartId !== null) {
            $data['cart_id'] = (int) $cartId;
        }

        if ($secureKey !== null) {
            $data['secure_key'] = $secureKey;
        }

        $encrypted = self::encrypt(json_encode($data));

        if (empty($encrypted)) {
            // Fallback: direct redirect without proxy
            return $route;
        }

        return $context->link->getModuleLink(
            $moduleName,
            'proxy',
            array('data' => $encrypted),
            true
        );
    }

    /**
     * Decode and validate proxy data
     *
     * @param string $encryptedData Encrypted data from URL parameter
     * @param int $maxAge Maximum age in seconds (default: 5 minutes)
     * @return array|false Decoded data array or false on failure
     */
    public static function decodeProxyData($encryptedData, $maxAge = 300)
    {
        $decrypted = self::decrypt($encryptedData);
        if ($decrypted === false) {
            return false;
        }

        $data = json_decode($decrypted, true);
        if (!is_array($data)) {
            PrestaShopLogger::addLog(
                'MorPOS Proxy: Invalid JSON in decrypted data',
                3,
                null,
                'MorposEncryption',
                null,
                true
            );

            return false;
        }

        // Validate timestamp to prevent replay attacks
        if (isset($data['timestamp'])) {
            $age = time() - (int) $data['timestamp'];
            if ($age > $maxAge || $age < 0) {
                PrestaShopLogger::addLog(
                    'MorPOS Proxy: Data expired or invalid timestamp - Age: ' . $age . 's',
                    3,
                    null,
                    'MorposEncryption',
                    null,
                    true
                );

                return false;
            }
        }

        // Validate required field
        if (!isset($data['route']) || empty($data['route'])) {
            PrestaShopLogger::addLog(
                'MorPOS Proxy: Missing route in data',
                3,
                null,
                'MorposEncryption',
                null,
                true
            );

            return false;
        }

        return $data;
    }
}
