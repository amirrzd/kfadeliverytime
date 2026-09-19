<?php

declare(strict_types=1);

/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, September 2019
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

class KfaDeliveryTimeCapacityHelper
{
    public static array $id_cache_list = array();
    
    public static bool $use_cache = true;
    
    /**
     *
     * @var KfaDeliveryTime
     */
    private KfaDeliveryTime $module;
    
    public function __construct(KfaDeliveryTime $module)
    {
        $this->module = $module;
    }
    
    /**
     * 
     * @param int $id_carrier
     * @return array
     */
    public function getCapacityInfoFilters(int $id_carrier): array {
        $settings = $this->getCapacityInfoSettings($id_carrier);
        $filters = array();
        if (array_key_exists('valid', $settings)) {
            $filters[] = "o.`valid` = $settings[valid]";
        }
        if (array_key_exists('current_state', $settings)) {
            $filters[] = "o.`current_state` IN($settings[current_state])";
        }
        if (array_key_exists('id_carrier', $settings)) {
            $filters[] = "o.`id_carrier` IN($settings[id_carrier])";
        }
        return $filters;
    }
    
    /**
     * 
     * @param int $id_carrier
     * @return array
     */
    private function getCapacityInfoSettings(int $id_carrier): array {
        static $id_order_state_list = null;
        $prefix = _DB_PREFIX_;
        $settings = array();
        
        if ($this->module->getConf('ORDER_CHECKING') == KfaDeliveryTime::ORDER_CHECKING_BY_VALIDITY) {
            $settings['valid'] = 1;
        } else {
            
            if (is_null($id_order_state_list)) {
                $order_states = Db::getInstance()->executeS("SELECT `id_order_state` FROM `{$prefix}order_state`");
                $id_order_state_list = array();
                foreach ($order_states as $order_state) {
                    if ($this->module->getConf('ORDER_CHECKING_ORDER_STATE_' . $order_state['id_order_state'])) {
                        $id_order_state_list[] = $order_state['id_order_state'];
                    }
                }
            }
            
            if (empty($id_order_state_list)) {
                /**
                 * شرطی بر میگردانیم که در هر کوئری ای قرار بگیرد خروجی کوئری خالی شود
                 */
                return array('current_state' => -1);
            }
            
            $settings['current_state'] = implode(',', $id_order_state_list);
        }
        
        if ($id_carrier > 0 && $this->module->getConf('ORDER_CHECKING_BY_CARRIER')) {
            $id_reference = KfaDeliveryTimeCarrier::getCarrierReference($id_carrier);
            $id_carrier_rows = Db::getInstance()->executeS("
                SELECT `id_carrier` 
                FROM `{$prefix}carrier` 
                WHERE `id_reference` = $id_reference
            ");
            if ($id_carrier_rows) {
                $id_carrier_array = array();
                foreach ($id_carrier_rows as $row) {
                    $id_carrier_array[] = (int) $row['id_carrier'];
                }
                $settings['id_carrier'] = implode(',', $id_carrier_array);
            }
        }
        
        return $settings;
    }
    
    /**
     * 
     * @param int $id_carrier
     * @return int
     */
    private function getIdCache(int $id_carrier): int {
        if (!array_key_exists($id_carrier, self::$id_cache_list)) {
            $settings = $this->getCapacityInfoSettings($id_carrier);
            $cache = new KfaDeliveryTimeCache($settings);
            self::$id_cache_list[$id_carrier] = $cache->getIdCache();
            $context = Context::getContext();
            if (!empty($context->employee->id) && $context->employee->id == 20) {
                error_log("id_carrier: $id_carrier, settings: " . var_export($settings, true) . ', list: ' . var_export(self::$id_cache_list, true));
            }
        }
        return self::$id_cache_list[$id_carrier];
    }
    
    public function getNbOrders(array $delivery_time, int $id_carrier): int {
        if (self::$use_cache) {
            return $this->getNbOrdersUsingCache($delivery_time, $id_carrier);
        }
        
        return $this->getNbOrdersNoCache($delivery_time, $id_carrier);
    }
    
    public function getNbOrdersUsingCache(array $delivery_time, int $id_carrier): int {
        $id_cache = $this->getIdCache($id_carrier);
        if ($id_cache <= 0) {
            return $this->getNbOrdersNoCache($delivery_time, $id_carrier, true);
        }

        $start = $delivery_time['date_start'];
        $end = $delivery_time['date_end'];
        $where = "
            (
                (`date_process` IS NOT NULL AND `date_process` = '$delivery_time[date] 00:00:00')
                OR
                (`date_process` IS NULL AND `date_start` >= '$start' AND `date_end` <= '$end')
                OR
                (`date_process` IS NULL AND `date_start` <= '$start' AND `date_end` >= '$end')
            )
        ";
        $count = $this->getCachedCount($id_cache, $where);

        return $count ?? $this->getNbOrdersNoCache($delivery_time, $id_carrier, true);
    }
    
    public function getNbOrdersNoCache(
        array $delivery_time,
        int $id_carrier,
        bool $apply_cache_age_limit = false
    ): int
    {
        $prefix = _DB_PREFIX_;
        $start = $delivery_time['date_start'];
        $end = $delivery_time['date_end'];
        $filters = $this->getCapacityInfoFilters($id_carrier);
        if ($apply_cache_age_limit) {
            $filters[] = $this->getCacheAgeFilter();
        }
        $filters[] = "
            `id_cart` IN(
                SELECT
                    `id_cart`
                FROM
                    `{$prefix}kfadeliverytime_cart`
                WHERE
                    (`date_process` IS NOT NULL AND `date_process` = '$delivery_time[date] 00:00:00')
                    OR
                    (`date_process` IS NULL AND `date_start` >= '$start' AND `date_end` <= '$end')
                    OR
                    (`date_process` IS NULL AND `date_start` <= '$start' AND `date_end` >= '$end')
            )
        ";
        $where = 'WHERE ' . implode(' AND ', $filters);
        $sql = "
            SELECT COUNT(*)
            FROM `{$prefix}orders` o
            $where
        ";
        return (int) Db::getInstance()->getValue($sql);
    }
    
    private function getCapacityByCount(int $id_carrier, string $where): int {
        $id_cache = $this->getIdCache($id_carrier);
        if ($id_cache <= 0) {
            return $this->getLiveCapacityCount($id_carrier, $where);
        }

        $count = $this->getCachedCount($id_cache, $where);

        return $count ?? $this->getLiveCapacityCount($id_carrier, $where);
    }

    private function getCachedCount(int $id_cache, string $where): ?int
    {
        $prefix = _DB_PREFIX_;
        $sql = "
            SELECT COUNT(cl.`id_cache`) > 0 AS `cache_exists`, COUNT(co.`id_order`) AS `value`
            FROM `{$prefix}kfadeliverytime_cached_orders_list` cl
            LEFT JOIN `{$prefix}kfadeliverytime_cached_orders` co
                ON co.`id_cache` = cl.`id_cache` AND ($where)
            WHERE cl.`id_cache` = $id_cache
        ";
        $row = Db::getInstance()->getRow($sql, false);
        if (!$row || !(int) $row['cache_exists']) {
            return null;
        }

        return (int) $row['value'];
    }

    private function getLiveCapacityCount(int $id_carrier, string $where): int
    {
        $prefix = _DB_PREFIX_;
        $filters = $this->getCapacityInfoFilters($id_carrier);
        $filters[] = $this->getCacheAgeFilter();
        $filters[] = $where;
        $live_where = 'WHERE ' . implode(' AND ', $filters);
        $sql = "
            SELECT COUNT(*)
            FROM `{$prefix}orders` o
            INNER JOIN `{$prefix}kfadeliverytime_cart` kc USING(`id_cart`)
            $live_where
        ";

        return (int) Db::getInstance()->getValue($sql);
    }
    
    /**
     * 
     * @param int $id_carrier
     * @param string $where
     * @return float
     */
    private function getCapacityByTotalPaid(int $id_carrier, string $where): float {
        $id_cache = $this->getIdCache($id_carrier);
        if ($id_cache <= 0) {
            $rows = $this->getLiveTotalPaidRows($id_carrier, $where);
        } else {
            $rows = $this->getCachedTotalPaidRows($id_cache, $where);
            if ($rows === null) {
                $rows = $this->getLiveTotalPaidRows($id_carrier, $where);
            }
        }

        $default_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $value = 0;
        if ($rows) {
            foreach ($rows as $row) {
                if ($row['total_paid'] === null) {
                    continue;
                }
                if ($row['id_currency'] == $default_currency) {
                    $value += (float) $row['total_paid'];
                } else {
                    $value += Tools::convertPrice((float) $row['total_paid'], (int) $row['id_currency'], false);
                }
            }
        }
        return $value;
    }

    /** @return list<array<string, mixed>>|null */
    private function getCachedTotalPaidRows(int $id_cache, string $where): ?array
    {
        $prefix = _DB_PREFIX_;
        $sql = "
            SELECT cl.`id_cache` AS `cache_exists`, co.`total_paid`, co.`id_currency`
            FROM `{$prefix}kfadeliverytime_cached_orders_list` cl
            LEFT JOIN `{$prefix}kfadeliverytime_cached_orders` co
                ON co.`id_cache` = cl.`id_cache` AND ($where)
            WHERE cl.`id_cache` = $id_cache
        ";
        $rows = Db::getInstance()->executeS($sql);

        return $rows ?: null;
    }

    /** @return list<array<string, mixed>> */
    private function getLiveTotalPaidRows(int $id_carrier, string $where): array
    {
        $prefix = _DB_PREFIX_;
        $filters = $this->getCapacityInfoFilters($id_carrier);
        $filters[] = $this->getCacheAgeFilter();
        $filters[] = $where;
        $live_where = 'WHERE ' . implode(' AND ', $filters);
        $sql = "
            SELECT o.`total_paid`, o.`id_currency`
            FROM `{$prefix}orders` o
            INNER JOIN `{$prefix}kfadeliverytime_cart` kc USING(`id_cart`)
            $live_where
        ";

        return Db::getInstance()->executeS($sql) ?: [];
    }

    private function getCacheAgeFilter(): string
    {
        if (KfaDeliveryTimeCache::MAX_CACHE_DAYS <= 0) {
            return '1';
        }

        $date_limit = pSQL(date(
            'Y-m-d 00:00:00',
            strtotime('-' . KfaDeliveryTimeCache::MAX_CACHE_DAYS . ' days')
        ));

        return "o.`date_add` >= '$date_limit'";
    }
    
    public function getDeliveryTimeCapacityByCount(array $delivery_time, int $id_carrier): int {
        $start = $delivery_time['date_start'];
        $end = $delivery_time['date_end'];
        $where = "((`date_start` >= '$start' AND `date_end` <= '$end') OR (`date_start` <= '$start' AND `date_end` >= '$end'))";
        return $this->getCapacityByCount($id_carrier, $where);
    }
    
    public function getDeliveryTimeCapacityByTotalPaid(array $delivery_time, int $id_carrier): float {
        $start = $delivery_time['date_start'];
        $end = $delivery_time['date_end'];
        $where = "((`date_start` >= '$start' AND `date_end` <= '$end') OR (`date_start` <= '$start' AND `date_end` >= '$end'))";
        return $this->getCapacityByTotalPaid($id_carrier, $where);
    }
    
    public function getDayCapacityByCount(array $delivery_time, int $id_carrier): int {
        $start = date('Y-m-d 00:00:00', strtotime($delivery_time['date_start']));
        $end = date('Y-m-d 23:59:59', strtotime($delivery_time['date_end']));
        $where = "`date_start` >= '$start' AND `date_end` <= '$end'";
        return $this->getCapacityByCount($id_carrier, $where);
    }
    
    public function getDayCapacityByTotalPaid(array $delivery_time, int $id_carrier): float {
        $start = date('Y-m-d 00:00:00', strtotime($delivery_time['date_start']));
        $end = date('Y-m-d 23:59:59', strtotime($delivery_time['date_end']));
        $where = "`date_start` >= '$start' AND `date_end` <= '$end'";
        return $this->getCapacityByTotalPaid($id_carrier, $where);
    }
}
