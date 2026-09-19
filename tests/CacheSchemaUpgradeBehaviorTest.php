<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CacheSchemaUpgradeBehaviorTest extends TestCase
{
    private KfaDeliveryTimeTestDb $db;

    protected function setUp(): void
    {
        $schemaClass = dirname(__DIR__) . '/classes/KfaDeliveryTimeCacheSchema.php';
        $upgrade = dirname(__DIR__) . '/upgrade/upgrade-1.38.4.php';
        self::assertFileExists($schemaClass);
        self::assertFileExists($upgrade);
        require_once $schemaClass;
        require_once $upgrade;

        $this->db = Db::getInstance();
        $this->db->reset();
    }

    public function testStandardPrestashopUpgradeAddsCodWarningDateIndexes(): void
    {
        $this->db->executeSResults = [
            $this->indexRows('idx_kfadt_orders_date_upd', ['date_upd', 'id_order', 'id_cart']),
            $this->indexRows('idx_kfadt_cart_date_upd', ['date_upd', 'id_cart']),
            [],
            [],
            $this->indexRows('idx_kfadt_history_date_add', ['date_add', 'id_cart']),
            $this->indexRows('idx_id_order', ['id_order']),
            $this->indexRows('idx_id_cart', ['id_cart']),
            $this->indexRows('idx_date_start', ['date_start']),
            $this->indexRows('idx_date_end', ['date_end']),
            $this->indexRows('idx_kfa_cache_dates', ['id_cache', 'date_start', 'date_end']),
        ];

        self::assertTrue(upgrade_module_1_38_4((object) []));
        self::assertCount(10, $this->db->executeSQueries);
        self::assertSame([
            'ALTER TABLE `ps_kfadeliverytime_cart` '
                . 'ADD INDEX `idx_kfadt_cart_date_start` (`date_start`, `id_cart`), '
                . 'ADD INDEX `idx_kfadt_cart_date_process` (`date_process`, `id_cart`), '
                . 'ALGORITHM=INPLACE, LOCK=NONE',
        ], $this->db->executedSql);
    }

    /**
     * @param list<string> $columns
     * @return list<array<string, mixed>>
     */
    private function indexRows(string $name, array $columns): array
    {
        return array_map(
            static fn (string $column, int $offset): array => [
                'Key_name' => $name,
                'Seq_in_index' => $offset + 1,
                'Column_name' => $column,
                'Sub_part' => null,
                'Non_unique' => 1,
                'Ignored' => 'NO',
            ],
            $columns,
            array_keys($columns)
        );
    }
}
