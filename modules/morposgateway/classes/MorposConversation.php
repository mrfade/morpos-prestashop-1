<?php
/**
 * MorPOS Conversation Helper
 *
 * @author Morpara
 * @copyright 2026 Morpara
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MorposConversation
{
    /** Crockford base32 alphabet (no I,L,O,U) */
    private static $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function b32Encode($bin)
    {
        $bits = '';
        $len = strlen($bin);
        for ($i = 0; $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($bin[$i])), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        for ($i = 0, $bl = strlen($bits); $i < $bl; $i += 5) {
            $chunk = substr($bits, $i, 5);
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0');
            }

            $out .= self::$alphabet[bindec($chunk)];
        }

        return $out;
    }

    public static function makeConversationId20ForAttempt($orderId, $attemptSeq, $secret = '')
    {
        $version = defined('_PS_VERSION_') ? _PS_VERSION_ : 'unknown';
        $message = 'morpos:prestashop:' . $version . ':' . $orderId . ':' . $attemptSeq;
        if ($secret !== '') {
            $mac = hash_hmac('sha256', $message, $secret, true);
        } else {
            $mac = hash('sha256', $message, true);
        }

        return self::b32Encode(substr($mac, 0, 12));
    }

    /**
     * Get next attempt sequence number for order
     */
    public static function getNextAttemptSeq($orderId)
    {
        $sql = 'SELECT MAX(attempt_seq) as mx FROM `' . _DB_PREFIX_ . 'morpos_conversation_attempt` ' .
                'WHERE order_id = ' . (int)$orderId;
        $result = Db::getInstance()->getRow($sql);
        
        if ($result === false) {
            PrestaShopLogger::addLog(
                'MorPOS: Database error in getNextAttemptSeq for order: ' . $orderId . ' - ' . 
                Db::getInstance()->getMsgError(),
                3,
                null,
                'Order',
                $orderId,
                true
            );
            return 1; // Start from 1 if query fails
        }
        
        $mx = isset($result['mx']) ? (int)$result['mx'] : 0;
        return $mx + 1;
    }

    /**
     * Add conversation attempt record
     */
    public static function addAttempt($orderId, $attemptSeq, $conversationId, $extraData = array())
    {
        $data = array_merge(array('created_by' => 'catalog_validate'), $extraData);
        $result = Db::getInstance()->insert('morpos_conversation_attempt', array(
            'order_id' => (int)$orderId,
            'attempt_seq' => (int)$attemptSeq,
            'conversation_id' => pSQL($conversationId),
            'data' => pSQL(json_encode($data)),
        ));
        
        if (!$result) {
            PrestaShopLogger::addLog(
                'MorPOS: Failed to insert conversation attempt for order: ' . $orderId . 
                ', conversationId: ' . $conversationId . ' - ' . Db::getInstance()->getMsgError(),
                3,
                null,
                'Order',
                $orderId,
                true
            );
        }
        
        return $result;
    }

    /**
     * Check if conversation exists for order
     */
    public static function conversationExistsForOrder($orderId, $conversationId)
    {
        $sql = 'SELECT COUNT(*) as c FROM `' . _DB_PREFIX_ . 'morpos_conversation_attempt` ' .
                'WHERE order_id = ' . (int)$orderId . ' ' .
                'AND conversation_id = "' . pSQL($conversationId) . '"';
        $result = Db::getInstance()->getRow($sql);
        
        if ($result === false) {
            PrestaShopLogger::addLog(
                'MorPOS: Database error in conversationExistsForOrder - Order: ' . $orderId . 
                ', ConversationId: ' . $conversationId . ' - ' . Db::getInstance()->getMsgError(),
                3,
                null,
                'Order',
                $orderId,
                true
            );
            return false; // Fail closed for security
        }
        
        return isset($result['c']) && (int)$result['c'] > 0;
    }

    /**
     * Get conversation data for verification
     */
    public static function getConversationData($conversationId)
    {
        if (empty($conversationId)) {
            return array();
        }
        
        // NOTE: Do not add LIMIT 1 here - getRow() automatically adds it
        $sql = 'SELECT `data` FROM `' . _DB_PREFIX_ . 'morpos_conversation_attempt` ' .
                'WHERE `conversation_id` = \'' . pSQL($conversationId) . '\'';
        
        $result = Db::getInstance()->getRow($sql);
        
        if ($result === false) {
            PrestaShopLogger::addLog(
                'MorPOS: Database error in getConversationData - ConversationId: ' . 
                $conversationId . ' - ' . Db::getInstance()->getMsgError(),
                3,
                null,
                'Payment',
                null,
                true
            );
            return array();
        }
        
        if ($result && isset($result['data'])) {
            $data = json_decode($result['data'], true);
            return is_array($data) ? $data : array();
        }
        
        return array();
    }
}
