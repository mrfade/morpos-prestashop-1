<?php
/**
 * MorPOS Currency Helper
 *
 * @author Morpara
 * @copyright 2026 Morpara
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MorposCurrency
{
    /**
     * Map 3-letter ISO currency codes to ISO-4217 numeric codes.
     * Extend this map as needed.
     *
     * @return array
     */
    public static function numericMap()
    {
        return array(
            'TRY' => '949',
            'USD' => '840',
            'EUR' => '978',
            'GBP' => '826',
        );
    }

    /**
     * Convert 3-letter code to numeric ISO-4217 code.
     * Returns null if not found.
     *
     * @param string $alpha3
     * @return string|null
     */
    public static function toNumeric($alpha3)
    {
        $map = self::numericMap();
        $alpha3 = strtoupper(trim($alpha3));
        return isset($map[$alpha3]) ? $map[$alpha3] : null;
    }

    /**
     * Get list of supported currency codes.
     *
     * @return array Array of 3-letter ISO currency codes
     */
    public static function supportedCurrencies()
    {
        return array_keys(self::numericMap());
    }
}
