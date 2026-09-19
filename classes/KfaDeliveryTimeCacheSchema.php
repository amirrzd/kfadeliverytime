<?php

declare(strict_types=1);

final class KfaDeliveryTimeCacheSchema
{
    private const INDEX_MATCHING = 'matching';
    private const INDEX_MISSING = 'missing';
    private const INDEX_MISMATCHED = 'mismatched';

    /** @var list<array{table: string, name: string, columns: list<string>}> */
    private const INDEXES = [
        [
            'table' => 'orders',
            'name' => 'idx_kfadt_orders_date_upd',
            'columns' => ['date_upd', 'id_order', 'id_cart'],
        ],
        [
            'table' => 'kfadeliverytime_cart',
            'name' => 'idx_kfadt_cart_date_upd',
            'columns' => ['date_upd', 'id_cart'],
        ],
        [
            'table' => 'kfadeliverytime_cart',
            'name' => 'idx_kfadt_cart_date_start',
            'columns' => ['date_start', 'id_cart'],
        ],
        [
            'table' => 'kfadeliverytime_cart',
            'name' => 'idx_kfadt_cart_date_process',
            'columns' => ['date_process', 'id_cart'],
        ],
        [
            'table' => 'kfadeliverytime_history',
            'name' => 'idx_kfadt_history_date_add',
            'columns' => ['date_add', 'id_cart'],
        ],
        [
            'table' => 'kfadeliverytime_cached_orders',
            'name' => 'idx_id_order',
            'columns' => ['id_order'],
        ],
        [
            'table' => 'kfadeliverytime_cached_orders',
            'name' => 'idx_id_cart',
            'columns' => ['id_cart'],
        ],
        [
            'table' => 'kfadeliverytime_cached_orders',
            'name' => 'idx_date_start',
            'columns' => ['date_start'],
        ],
        [
            'table' => 'kfadeliverytime_cached_orders',
            'name' => 'idx_date_end',
            'columns' => ['date_end'],
        ],
        [
            'table' => 'kfadeliverytime_cached_orders',
            'name' => 'idx_kfa_cache_dates',
            'columns' => ['id_cache', 'date_start', 'date_end'],
        ],
    ];

    public static function ensureIndexes(): bool
    {
        $changes_by_table = [];
        foreach (self::INDEXES as $index) {
            $state = self::indexState($index['table'], $index['name'], $index['columns']);
            if ($state === null) {
                return false;
            }
            if ($state === self::INDEX_MATCHING) {
                continue;
            }

            $changes_by_table[$index['table']][] = [
                'index' => $index,
                'drop' => $state === self::INDEX_MISMATCHED,
            ];
        }

        foreach ($changes_by_table as $table => $changes) {
            $prefix = _DB_PREFIX_;
            $clauses = [];
            foreach ($changes as $change) {
                $index = $change['index'];
                if ($change['drop']) {
                    $clauses[] = "DROP INDEX `{$index['name']}`";
                }
                $clauses[] = "ADD INDEX `{$index['name']}` (" . self::renderColumns($index['columns']) . ')';
            }
            $sql = "ALTER TABLE `{$prefix}{$table}` "
                . implode(', ', $clauses)
                . ', ALGORITHM=INPLACE, LOCK=NONE';
            if (Db::getInstance()->execute($sql)) {
                continue;
            }

            foreach ($changes as $change) {
                $index = $change['index'];
                if (self::indexState($table, $index['name'], $index['columns']) !== self::INDEX_MATCHING) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @param list<string> $expected_columns */
    private static function indexState(string $table, string $name, array $expected_columns): ?string
    {
        $prefix = _DB_PREFIX_;
        $rows = Db::getInstance()->executeS(
            "SHOW INDEX FROM `{$prefix}{$table}` WHERE `Key_name` = '$name'",
            true,
            false
        );
        if (!is_array($rows)) {
            return null;
        }
        if ($rows === []) {
            return self::INDEX_MISSING;
        }
        if (count($rows) !== count($expected_columns)) {
            return self::INDEX_MISMATCHED;
        }

        usort(
            $rows,
            static fn (array $left, array $right): int =>
                (int) ($left['Seq_in_index'] ?? 0) <=> (int) ($right['Seq_in_index'] ?? 0)
        );
        foreach ($expected_columns as $offset => $expected_column) {
            $row = $rows[$offset];
            if (!array_key_exists('Key_name', $row)
                || strcasecmp((string) $row['Key_name'], $name) !== 0
                || !array_key_exists('Seq_in_index', $row)
                || (int) $row['Seq_in_index'] !== $offset + 1
                || !array_key_exists('Column_name', $row)
                || strcasecmp((string) $row['Column_name'], $expected_column) !== 0
                || !array_key_exists('Sub_part', $row)
                || $row['Sub_part'] !== null
                || !array_key_exists('Non_unique', $row)
                || (int) $row['Non_unique'] !== 1
                || (array_key_exists('Ignored', $row) && strcasecmp((string) $row['Ignored'], 'NO') !== 0)) {
                return self::INDEX_MISMATCHED;
            }
        }

        return self::INDEX_MATCHING;
    }

    /** @param list<string> $columns */
    private static function renderColumns(array $columns): string
    {
        return implode(', ', array_map(
            static fn (string $column): string => "`$column`",
            $columns
        ));
    }
}
