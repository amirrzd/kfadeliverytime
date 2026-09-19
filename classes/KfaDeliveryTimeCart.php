<?php

declare(strict_types=1);

/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, February 2020
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

class KfaDeliveryTimeCart {
    /**
     * 
     * @return int
     */
    private static function getIdEmployee(): int {
        return empty(Context::getContext()->employee->id) ? 0 : (int) Context::getContext()->employee->id;
    }
    
    /**
     * 
     * @param int $id_cart
     * @return bool
     */
    public static function deleteOption(int $id_cart): bool {
        KfaDeliveryTime::logCalculations(__CLASS__ . ' > ' . __FUNCTION__ . " > id_cart: $id_cart");
        $result = self::deleteOptionAtomically($id_cart);
        KfaDeliveryTime::logCalculations(__CLASS__ . ' > ' . __FUNCTION__ . " > result: $result");

        return $result;
    }

    private static function deleteOptionAtomically(int $id_cart): bool
    {
        $db = Db::getInstance();
        try {
            $transaction_state = $db->getValue('SELECT @@in_transaction', false);
        } catch (Throwable $exception) {
            KfaDeliveryTime::logCalculations(
                __CLASS__ . ' > ' . __FUNCTION__ . ' > transaction probe failed: ' . $exception->getMessage()
            );

            return false;
        }
        if ($transaction_state !== 0 && $transaction_state !== '0'
            && $transaction_state !== 1 && $transaction_state !== '1') {
            return false;
        }

        $owns_transaction = (int) $transaction_state === 0;
        $savepoint = 'kfadt_delete_option_' . $id_cart;
        $begin_sql = $owns_transaction ? 'START TRANSACTION' : "SAVEPOINT $savepoint";
        if (!$db->execute($begin_sql)) {
            return false;
        }

        try {
            $deleted = $db->delete('kfadeliverytime_cart', 'id_cart = ' . $id_cart);
            $history_recorded = $deleted
                && (bool) KfaDeliveryTimeHistory::addToHistory($id_cart, self::getIdEmployee(), '');
            if (!$history_recorded) {
                self::rollbackDeleteOption($db, $owns_transaction, $savepoint);

                return false;
            }

            if ($owns_transaction) {
                if (!$db->execute('COMMIT')) {
                    $db->execute('ROLLBACK');

                    return false;
                }

                return true;
            }

            if (!$db->execute("RELEASE SAVEPOINT $savepoint")) {
                self::rollbackDeleteOption($db, false, $savepoint);

                return false;
            }

            return true;
        } catch (Throwable $exception) {
            self::rollbackDeleteOption($db, $owns_transaction, $savepoint);
            KfaDeliveryTime::logCalculations(
                __CLASS__ . ' > ' . __FUNCTION__ . ' failed: ' . $exception->getMessage()
            );

            return false;
        }
    }

    private static function rollbackDeleteOption(object $db, bool $owns_transaction, string $savepoint): void
    {
        if ($owns_transaction) {
            $db->execute('ROLLBACK');

            return;
        }

        $db->execute("ROLLBACK TO SAVEPOINT $savepoint");
        $db->execute("RELEASE SAVEPOINT $savepoint");
    }
    
    /**
     * Used in appsys3
     * @param int|string $id_cart
     * @param string $value
     * @param string|bool|null $date_process
     * @param string $date_start
     * @param string $date_end
     * @param bool|int|string $approximate
     * @return bool
     */
    public static function selectOption(
        int|string $id_cart,
        string $value,
        string|bool|null $date_process,
        string $date_start,
        string $date_end,
        bool|int|string $approximate
    ): bool {
        if (is_string($id_cart)) {
            if (!preg_match('/^\d+$/D', $id_cart)) {
                return false;
            }
            $normalized_id_cart = ltrim($id_cart, '0');
            $normalized_id_cart = $normalized_id_cart === '' ? '0' : $normalized_id_cart;
            if (strlen($normalized_id_cart) > 10
                || (strlen($normalized_id_cart) === 10
                    && strcmp($normalized_id_cart, '4294967295') > 0)) {
                return false;
            }
        }
        $id_cart = (int) $id_cart;
        if ($id_cart < 1 || $id_cart > 4294967295) {
            return false;
        }
        $date_process = is_string($date_process) ? $date_process : null;
        $approximate = (bool) $approximate;
        $normalized_date_process = self::normalizeDateProcess($date_process);
        $escaped_value = pSQL($value, true);
        $date_process_sql = $normalized_date_process === null
            ? 'NULL'
            : "'" . pSQL($normalized_date_process, true) . "'";
        $escaped_date_start = pSQL($date_start, true);
        $escaped_date_end = pSQL($date_end, true);
        $now = pSQL(date('Y-m-d H:i:s'));
        $approximate_value = $approximate ? 1 : 0;
        $prefix = _DB_PREFIX_;
        $sql = "
            INSERT INTO `{$prefix}kfadeliverytime_cart`
                (`id_cart`, `value`, `date_process`, `date_start`, `date_end`,
                 `date_add`, `date_upd`, `approximate`)
            VALUES
                ($id_cart, '$escaped_value', $date_process_sql, '$escaped_date_start',
                 '$escaped_date_end', '$now', '$now', $approximate_value)
            ON DUPLICATE KEY UPDATE
                `date_upd` = IF(
                    NOT (
                        BINARY `value` <=> BINARY VALUES(`value`)
                        AND `date_process` <=> VALUES(`date_process`)
                        AND `date_start` <=> VALUES(`date_start`)
                        AND `date_end` <=> VALUES(`date_end`)
                        AND `approximate` <=> VALUES(`approximate`)
                    ),
                    VALUES(`date_upd`),
                    `date_upd`
                ),
                `value` = VALUES(`value`),
                `date_process` = VALUES(`date_process`),
                `date_start` = VALUES(`date_start`),
                `date_end` = VALUES(`date_end`),
                `approximate` = VALUES(`approximate`)
        ";

        KfaDeliveryTime::logCalculations(__CLASS__ . ' > ' . __FUNCTION__ . ' > sql: ' . $sql);

        $db = Db::getInstance();
        $result = (bool) $db->execute($sql);
        $affected_rows = $result ? (int) $db->Affected_Rows() : 0;
        
        KfaDeliveryTime::logCalculations(__CLASS__ . ' > ' . __FUNCTION__ . " > result: $result");
        
        if ($affected_rows > 0) {
            KfaDeliveryTimeHistory::addToHistory($id_cart, self::getIdEmployee(), $value);
        }
        
        return $result;
    }

    private static function normalizeDateProcess(?string $date_process): ?string
    {
        if ($date_process === null || !preg_match(
            '/^(\d{4})-(\d{1,2})-(\d{1,2})(?: (\d{2}):(\d{2}):(\d{2}))?$/D',
            $date_process,
            $matches
        )) {
            return null;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        $hour = isset($matches[4]) && $matches[4] !== '' ? (int) $matches[4] : 0;
        $minute = isset($matches[5]) && $matches[5] !== '' ? (int) $matches[5] : 0;
        $second = isset($matches[6]) && $matches[6] !== '' ? (int) $matches[6] : 0;
        if (!checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        return sprintf(
            '%04d-%02d-%02d %02d:%02d:%02d',
            $year,
            $month,
            $day,
            $hour,
            $minute,
            $second
        );
    }
    
    /**
     * 
     * @param int $id_cart
     * @return bool
     */
    public static function exists(int $id_cart): bool {
        $sql = new DbQuery();
        $sql->select('COUNT(*)')->from('kfadeliverytime_cart')->where('id_cart = ' . (int) $id_cart);
        return (bool) Db::getInstance()->getValue($sql);
    }
    
    /**
     * 
     * @param int|null $id_cart Statistics uses a placeholder Cart(0), whose id is null.
     * @return string|false|null
     */
    public static function getOption(?int $id_cart): string|false|null {
        $sql = new DbQuery();
        $sql->select('value')->from('kfadeliverytime_cart')->where('id_cart = ' . (int) $id_cart);
        return Db::getInstance()->getValue($sql, false);
    }
    
    public static function getByIdCart(int $id_cart): array|false {
        $sql = new DbQuery();
        $sql->select('*')->from('kfadeliverytime_cart')->where('id_cart = ' . (int) $id_cart);
        return Db::getInstance()->getRow($sql, false);
    }
    
    /**
     * 
     * @param int|null $id_cart A missing LEFT JOIN row has no delivery-time selection.
     * @return string|false|null
     */
    public static function getOptionForAdminOrdersListing(?int $id_cart, bool &$approximate): string|false|null {
        if ($id_cart === null) {
            return false;
        }

        $sql = new DbQuery();
        $sql->select('*')->from('kfadeliverytime_cart')->where('id_cart = ' . (int) $id_cart);
        $row = Db::getInstance()->getRow($sql, false);
        if (!$row) {
            return false;
        }
        
        $approximate = (bool) $row['approximate'];
        
        if ($row['approximate'] && $row['date_process']) {
            if (KfaDeliveryTime::calendarIsPersian()) {
                return KfaPersianDate::gToS($row['date_process']) . '-23:59';
            }
                
            return $row['date_process'] . '-23:59';
        }
        
        return $row['value'];
    }
    
    /**
     * 
     * @param int $id_cart
     * @return int
     */
    public static function getCarrierFromOrder(int $id_cart): int {
        $prefix = _DB_PREFIX_;
        return (int) Db::getInstance()->getValue("SELECT `id_carrier` FROM `{$prefix}orders` WHERE `id_cart` = " . (int) $id_cart);
    }
}
