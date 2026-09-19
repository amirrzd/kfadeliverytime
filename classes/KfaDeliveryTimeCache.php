<?php

declare(strict_types=1);

/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, June 2022
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

class KfaDeliveryTimeCache
{
    const LOGGING = false;
    /**
     * Limit cache to orders from the last N days.
     * Set to 0 to disable the limit.
     */
    const MAX_CACHE_DAYS = 30;
    private const BUILD_LOCK_TIMEOUT_SECONDS = 0;
    private const CACHE_RETENTION_GENERATIONS = 10;
    private const STAGING_RETENTION_MINUTES = 60;
    private const STAGING_VALID = 255;
    private const UNSIGNED_INT_MAX = '4294967295';
    private const NO_MATCH_FILTER = '-1';
    private array $settings;

    /**
     * 
     * @param array<string, mixed> $settings
     */
    public function __construct(array $settings)
    {
        $this->settings = $this->normalizeSettings($settings);
    }

    public function getIdCache(): int
    {
        $id_cache = $this->findLatestCacheId();
        if ($id_cache && $this->isValid($id_cache)) {
            return $id_cache;
        }

        $lock_name = self::getBuildLockName();
        $connection_id = self::acquireBuildLock($lock_name);
        if (!$connection_id) {
            $published_id = $this->findLatestCacheId();
            if ($published_id && $this->isValid($published_id)) {
                return $published_id;
            }

            self::log(__CLASS__ . '::' . __FUNCTION__ . ' build lock timed out');

            return 0;
        }

        try {
            $published_id = $this->findLatestCacheId();
            if ($published_id && $this->isValid($published_id)) {
                return $published_id;
            }

            $expired_staging_ids = $this->findExpiredStagingCacheIds();
            if ($expired_staging_ids === false) {
                return 0;
            }
            foreach ($expired_staging_ids as $expired_staging_id) {
                if (!self::deleteStagingCache($expired_staging_id, $lock_name, $connection_id)) {
                    return 0;
                }
            }

            $id_cache = $this->addCache($lock_name, $connection_id);
            if (!$id_cache) {
                return 0;
            }

            foreach ($this->findRetiredCacheIds() as $old_id_cache) {
                self::deleteCache($old_id_cache, $lock_name, $connection_id);
            }

            return $id_cache;
        } finally {
            $this->releaseBuildLock($lock_name);
        }
    }

    private function findLatestCacheId(): int
    {
        $prefix = _DB_PREFIX_;
        $where_sql = $this->getCacheWhereSql();
        $sql = "SELECT `id_cache` FROM `{$prefix}kfadeliverytime_cached_orders_list` "
            . "WHERE $where_sql ORDER BY `date_add` DESC, `id_cache` DESC";

        return (int) Db::getInstance()->getValue($sql);
    }

    /** @return list<int> */
    private function findRetiredCacheIds(): array
    {
        $prefix = _DB_PREFIX_;
        $where_sql = $this->getCacheWhereSql();
        $rows = Db::getInstance()->executeS(
            "SELECT `id_cache` FROM `{$prefix}kfadeliverytime_cached_orders_list` "
            . "WHERE $where_sql ORDER BY `date_add` DESC, `id_cache` DESC "
            . 'LIMIT ' . self::CACHE_RETENTION_GENERATIONS . ', 18446744073709551615'
        );

        return self::cacheIdsFromRows($rows);
    }

    /** @return list<int>|false */
    private function findExpiredStagingCacheIds(): array|false
    {
        $prefix = _DB_PREFIX_;
        $rows = Db::getInstance()->executeS(
            "SELECT `id_cache` FROM `{$prefix}kfadeliverytime_cached_orders_list` "
            . 'WHERE `valid` = ' . self::STAGING_VALID
            . ' AND `date_add` < DATE_SUB(NOW(), INTERVAL ' . self::STAGING_RETENTION_MINUTES . ' MINUTE)'
        );

        return $rows === false ? false : self::cacheIdsFromRows($rows);
    }

    /**
     * @param list<array<string, mixed>>|false $rows
     * @return list<int>
     */
    private static function cacheIdsFromRows(array|false $rows): array
    {
        if (!$rows) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['id_cache'],
            $rows
        )));
    }

    private function getCacheWhereSql(): string
    {
        $filters = [];
        if (array_key_exists('valid', $this->settings)) {
            $filters[] = '`valid` = ' . (int) $this->settings['valid'];
        } else {
            $filters[] = '`valid` IS NULL';
        }

        foreach (['current_state', 'id_carrier'] as $setting) {
            if (array_key_exists($setting, $this->settings)) {
                $value = pSQL((string) $this->settings[$setting]);
                $filters[] = "`$setting` = '$value'";
            } else {
                $filters[] = "`$setting` IS NULL";
            }
        }

        return implode(' AND ', $filters);
    }

    private static function getBuildLockName(): string
    {
        $scope = [
            'database' => defined('_DB_NAME_') ? (string) _DB_NAME_ : '',
            'prefix' => _DB_PREFIX_,
        ];
        $encoded_settings = json_encode($scope);

        return 'kfadt_cache_' . sha1($encoded_settings === false ? serialize($scope) : $encoded_settings);
    }

    /** @param array<string, mixed> $settings
     *  @return array{valid?: int, current_state?: string, id_carrier?: string}
     */
    private function normalizeSettings(array $settings): array
    {
        $normalized = [];
        if (array_key_exists('valid', $settings)) {
            $normalized['valid'] = (int) $settings['valid'];
        }
        foreach (['current_state', 'id_carrier'] as $setting) {
            if (!array_key_exists($setting, $settings)) {
                continue;
            }

            $normalized[$setting] = self::normalizeUnsignedIntegerList(
                $settings[$setting],
                $setting === 'current_state'
            );
        }

        return $normalized;
    }

    private static function normalizeUnsignedIntegerList(mixed $value, bool $allow_no_match_sentinel): string
    {
        $normalized = [];
        foreach (explode(',', (string) $value) as $token) {
            $token = trim($token);
            if ($allow_no_match_sentinel && $token === self::NO_MATCH_FILTER) {
                $normalized[] = self::NO_MATCH_FILTER;

                continue;
            }
            if (!preg_match('/^\d+$/D', $token)) {
                continue;
            }

            $token = ltrim($token, '0');
            $token = $token === '' ? '0' : $token;
            if (strlen($token) > strlen(self::UNSIGNED_INT_MAX)
                || (strlen($token) === strlen(self::UNSIGNED_INT_MAX)
                    && strcmp($token, self::UNSIGNED_INT_MAX) > 0)) {
                continue;
            }

            $normalized[] = $token;
        }

        if (!$normalized) {
            return self::NO_MATCH_FILTER;
        }

        $normalized = array_values(array_unique($normalized));
        usort($normalized, static function (string $left, string $right): int {
            if ($left === self::NO_MATCH_FILTER || $right === self::NO_MATCH_FILTER) {
                return $left === $right ? 0 : ($left === self::NO_MATCH_FILTER ? -1 : 1);
            }

            return strlen($left) <=> strlen($right) ?: strcmp($left, $right);
        });

        return implode(',', $normalized);
    }

    private static function acquireBuildLock(string $lock_name): int
    {
        $escaped_lock_name = pSQL($lock_name);
        $sql = "SELECT IF(GET_LOCK('$escaped_lock_name', "
            . self::BUILD_LOCK_TIMEOUT_SECONDS
            . '), CONNECTION_ID(), 0)';

        return (int) Db::getInstance()->getValue($sql, false);
    }

    private static function connectionOwnsBuildLock(string $lock_name, int $connection_id): bool
    {
        $escaped_lock_name = pSQL($lock_name);
        $sql = "SELECT CONNECTION_ID() = $connection_id "
            . "AND IS_USED_LOCK('$escaped_lock_name') = $connection_id";

        return (int) Db::getInstance()->getValue($sql, false) === 1;
    }

    private static function getLockOwnershipCondition(string $lock_name, int $connection_id): string
    {
        $escaped_lock_name = pSQL($lock_name);

        return "CONNECTION_ID() = $connection_id "
            . "AND IS_USED_LOCK('$escaped_lock_name') = $connection_id";
    }

    private static function releaseBuildLock(string $lock_name): void
    {
        $escaped_lock_name = pSQL($lock_name);
        try {
            if (!Db::getInstance()->execute("DO RELEASE_LOCK('$escaped_lock_name')")) {
                error_log(__CLASS__ . '::' . __FUNCTION__ . ' returned false');
            }
        } catch (Throwable $exception) {
            error_log(__CLASS__ . '::' . __FUNCTION__ . ' failed: ' . $exception->getMessage());
        }
    }
    private function isValid(int $id_cache): bool
    {
        $prefix = _DB_PREFIX_;
        $cache_date_add = self::getCacheDateAdd($id_cache);
        if (!is_string($cache_date_add) || $cache_date_add === '') {
            return false;
        }
        if (self::MAX_CACHE_DAYS > 0 && substr($cache_date_add, 0, 10) !== date('Y-m-d')) {
            return false;
        }

        $cache_date_add = pSQL($cache_date_add);
        $current_order_filters = $this->getOrderFilterSql();
        $current_order_match = $current_order_filters === '' ? '1' : $current_order_filters;
        $sql = "
            SELECT (
                EXISTS(
                    SELECT 1
                    FROM `{$prefix}kfadeliverytime_history` h
                    INNER JOIN `{$prefix}kfadeliverytime_cached_orders` co
                        ON co.`id_cache` = $id_cache AND co.`id_cart` = h.`id_cart`
                    LEFT JOIN `{$prefix}kfadeliverytime_cart` kc ON kc.`id_cart` = co.`id_cart`
                    WHERE h.`date_add` >= '$cache_date_add'
                        AND kc.`id_cart` IS NULL
                    LIMIT 1
                )
                OR EXISTS(
                    SELECT 1
                    FROM `{$prefix}kfadeliverytime_cart` kc
                    INNER JOIN `{$prefix}orders` o ON o.`id_cart` = kc.`id_cart`
                    LEFT JOIN `{$prefix}kfadeliverytime_cached_orders` co
                        ON co.`id_cache` = $id_cache AND co.`id_order` = o.`id_order`
                    WHERE kc.`date_upd` >= '$cache_date_add'
                        AND (
                            (co.`id_order` IS NULL AND ($current_order_match))
                            OR (
                                co.`id_order` IS NOT NULL
                                AND NOT (
                                    BINARY co.`value` <=> BINARY kc.`value`
                                    AND co.`date_process` <=> kc.`date_process`
                                    AND co.`date_start` <=> kc.`date_start`
                                    AND co.`date_end` <=> kc.`date_end`
                                )
                            )
                        )
                    LIMIT 1
                )
                OR EXISTS(
                    SELECT 1
                    FROM `{$prefix}orders` o
                    LEFT JOIN `{$prefix}kfadeliverytime_cart` kc ON kc.`id_cart` = o.`id_cart`
                    LEFT JOIN `{$prefix}kfadeliverytime_cached_orders` co
                        ON co.`id_cache` = $id_cache AND co.`id_order` = o.`id_order`
                    WHERE o.`date_upd` >= '$cache_date_add'
                        AND (
                            (
                                co.`id_order` IS NULL
                                AND kc.`id_cart` IS NOT NULL
                                AND ($current_order_match)
                            )
                            OR (
                                co.`id_order` IS NOT NULL
                                AND (
                                    NOT ($current_order_match)
                                    OR NOT (
                                        co.`id_cart` <=> o.`id_cart`
                                        AND co.`id_currency` <=> o.`id_currency`
                                        AND co.`total_paid` <=> o.`total_paid`
                                    )
                                )
                            )
                        )
                    LIMIT 1
                )
            )
        ";

        return !(bool) Db::getInstance()->getValue($sql);
    }

    private function getOrderWhereSql(): string
    {
        $filters = $this->getOrderFilterSql();

        return $filters === '' ? '' : 'WHERE ' . $filters;
    }

    private function getOrderFilterSql(): string
    {
        $filters = [];
        if (array_key_exists('valid', $this->settings)) {
            $filters[] = 'o.`valid` = ' . (int) $this->settings['valid'];
        }
        if (array_key_exists('current_state', $this->settings)) {
            $filters[] = 'o.`current_state` IN(' . $this->settings['current_state'] . ')';
        }
        if (array_key_exists('id_carrier', $this->settings)) {
            $filters[] = 'o.`id_carrier` IN(' . $this->settings['id_carrier'] . ')';
        }
        if (self::MAX_CACHE_DAYS > 0) {
            $date_limit = pSQL(date(
                'Y-m-d 00:00:00',
                strtotime('-' . self::MAX_CACHE_DAYS . ' days')
            ));
            $filters[] = "o.`date_add` >= '$date_limit'";
        }

        return implode(' AND ', $filters);
    }

    private static function getCacheDateAdd(int $id_cache): string|false|null
    {
        $prefix = _DB_PREFIX_;

        return Db::getInstance()->getValue("SELECT `date_add` FROM `{$prefix}kfadeliverytime_cached_orders_list` WHERE `id_cache` = $id_cache");
    }

    private static function deleteCache(int $id_cache, string $lock_name, int $connection_id): bool
    {
        if (!$id_cache) {
            return true;
        }

        $db = Db::getInstance();
        $ownership = self::getLockOwnershipCondition($lock_name, $connection_id);
        $where = '`id_cache` = ' . (int) $id_cache . " AND $ownership";
        $result = self::executeTransaction(static function () use (
            $db,
            $where,
            $lock_name,
            $connection_id
        ): bool {
            if (!$db->delete('kfadeliverytime_cached_orders', $where)
                || !$db->delete('kfadeliverytime_cached_orders_list', $where)
                || (int) $db->Affected_Rows() !== 1) {
                return false;
            }

            return self::connectionOwnsBuildLock($lock_name, $connection_id);
        });
        self::log(__CLASS__ . '::' . __FUNCTION__ . " id_cache = $id_cache > " . ($result ? 'true' : 'false'));

        return $result;
    }

    private static function deleteStagingCache(int $id_cache, string $lock_name, int $connection_id): bool
    {
        if (!$id_cache) {
            return true;
        }

        $db = Db::getInstance();
        $ownership = self::getLockOwnershipCondition($lock_name, $connection_id);
        $prefix = _DB_PREFIX_;
        $published_guard = "NOT EXISTS ("
            . "SELECT 1 FROM `{$prefix}kfadeliverytime_cached_orders_list` staging_metadata "
            . "WHERE staging_metadata.`id_cache` = $id_cache "
            . 'AND NOT (staging_metadata.`valid` <=> ' . self::STAGING_VALID . '))';
        $child_where = '`id_cache` = ' . $id_cache . " AND $published_guard AND $ownership";
        if (!$db->delete('kfadeliverytime_cached_orders', $child_where)
            || !self::connectionOwnsBuildLock($lock_name, $connection_id)) {
            return false;
        }

        // Staging rows are invisible, so child-first deletion avoids permanent orphans after reconnects.
        $metadata_where = '`id_cache` = ' . $id_cache
            . ' AND `valid` = ' . self::STAGING_VALID
            . " AND $ownership";
        if (!$db->delete('kfadeliverytime_cached_orders_list', $metadata_where)) {
            return false;
        }

        if ((int) $db->Affected_Rows() !== 1) {
            if (!self::connectionOwnsBuildLock($lock_name, $connection_id)) {
                return false;
            }

            $metadata_rows = $db->executeS(
                "SELECT 1 FROM `{$prefix}kfadeliverytime_cached_orders_list` "
                . "WHERE `id_cache` = $id_cache",
                true,
                false
            );
            if ($metadata_rows === false || $metadata_rows) {
                return false;
            }
        }

        $result = self::connectionOwnsBuildLock($lock_name, $connection_id);
        self::log(__CLASS__ . '::' . __FUNCTION__ . " id_cache = $id_cache > " . ($result ? 'true' : 'false'));

        return $result;
    }

    private function addCache(string $lock_name, int $connection_id): int
    {
        $db = Db::getInstance();
        $id_cache = 0;
        $published = false;
        $staging_token = 'building:' . substr(sha1($lock_name . microtime(true)), 0, 32);
        $staging_data = [
            'valid' => self::STAGING_VALID,
            'current_state' => $staging_token,
            'id_carrier' => null,
            'date_add' => date('Y-m-d H:i:s'),
        ];
        if (!$db->insert('kfadeliverytime_cached_orders_list', $staging_data, true)) {
            return 0;
        }

        try {
            $id_cache = (int) $db->Insert_ID();
            if (!$id_cache || !self::connectionOwnsBuildLock($lock_name, $connection_id)) {
                return 0;
            }

            $where = $this->getOrderWhereSql();
            $prefix = _DB_PREFIX_;
            $sql = "
                INSERT INTO `{$prefix}kfadeliverytime_cached_orders`
                    (`id_cache`, `id_order`, `id_cart`, `id_currency`, `total_paid`,
                     `date_process`, `date_start`, `date_end`, `value`)
                SELECT $id_cache, o.`id_order`, o.`id_cart`, o.`id_currency`, o.`total_paid`,
                       kc.`date_process`, kc.`date_start`, kc.`date_end`, kc.`value`
                FROM `{$prefix}orders` o
                INNER JOIN `{$prefix}kfadeliverytime_cart` kc USING(`id_cart`)
                $where
                ON DUPLICATE KEY UPDATE `id_order` = VALUES(`id_order`)
            ";
            if (!$db->execute($sql) || !self::connectionOwnsBuildLock($lock_name, $connection_id)) {
                return 0;
            }

            $published_data = [
                'valid' => array_key_exists('valid', $this->settings) ? $this->settings['valid'] : null,
                'current_state' => $this->settings['current_state'] ?? null,
                'id_carrier' => $this->settings['id_carrier'] ?? null,
            ];
            $ownership = self::getLockOwnershipCondition($lock_name, $connection_id);
            if (!$db->update(
                'kfadeliverytime_cached_orders_list',
                $published_data,
                '`id_cache` = ' . $id_cache
                    . ' AND `valid` = ' . self::STAGING_VALID
                    . " AND $ownership",
                1,
                true
            ) || (int) $db->Affected_Rows() !== 1) {
                return 0;
            }

            $published = true;

            return $id_cache;
        } finally {
            if ($id_cache && !$published && self::connectionOwnsBuildLock($lock_name, $connection_id)) {
                self::deleteStagingCache($id_cache, $lock_name, $connection_id);
            }
        }
    }

    public static function clear(): bool
    {
        $lock_name = self::getBuildLockName();
        $connection_id = self::acquireBuildLock($lock_name);
        if (!$connection_id) {
            return false;
        }

        try {
            $db = Db::getInstance();
            $ownership = self::getLockOwnershipCondition($lock_name, $connection_id);
            $result = self::executeTransaction(static function () use (
                $db,
                $ownership,
                $lock_name,
                $connection_id
            ): bool {
                if (!$db->delete('kfadeliverytime_cached_orders', $ownership)
                    || !self::connectionOwnsBuildLock($lock_name, $connection_id)
                    || !$db->delete('kfadeliverytime_cached_orders_list', $ownership)) {
                    return false;
                }

                return self::connectionOwnsBuildLock($lock_name, $connection_id);
            });
        } finally {
            self::releaseBuildLock($lock_name);
        }
        self::log(__CLASS__ . '::' . __FUNCTION__ . ' > ' . ($result ? 'true' : 'false'));

        return $result;
    }

    /** @param callable(): bool $operation */
    private static function executeTransaction(callable $operation): bool
    {
        $db = Db::getInstance();
        $caller_transaction = $db->getValue('SELECT @@in_transaction', false);
        if ($caller_transaction !== 0 && $caller_transaction !== '0') {
            return false;
        }

        $transaction_started = false;
        try {
            if (!$db->execute('START TRANSACTION')) {
                return false;
            }
            $transaction_started = true;

            if (!$operation() || !$db->execute('COMMIT')) {
                return false;
            }
            $transaction_started = false;

            return true;
        } catch (Throwable $exception) {
            self::log(__CLASS__ . '::' . __FUNCTION__ . ' failed: ' . $exception->getMessage());

            return false;
        } finally {
            if ($transaction_started) {
                try {
                    if (!$db->execute('ROLLBACK')) {
                        self::log(__CLASS__ . '::' . __FUNCTION__ . ' rollback returned false');
                    }
                } catch (Throwable $rollback_exception) {
                    self::log(
                        __CLASS__ . '::' . __FUNCTION__ . ' rollback failed: ' . $rollback_exception->getMessage()
                    );
                }
            }
        }
    }

    private static function log(string $message): void
    {
        if (self::LOGGING) {
            error_log($message);
        }
    }
}
