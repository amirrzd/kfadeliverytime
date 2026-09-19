<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CacheSchemaBehaviorTest extends TestCase
{
    private KfaDeliveryTimeTestDb $db;

    protected function setUp(): void
    {
        $schemaClass = dirname(__DIR__) . '/classes/KfaDeliveryTimeCacheSchema.php';
        self::assertFileExists($schemaClass);
        require_once $schemaClass;

        $this->db = Db::getInstance();
        $this->db->reset();
    }

    public function testMissingIndexesAreAddedForCurrentAndFreshInstallTables(): void
    {
        $this->db->executeSResults = array_fill(0, 10, []);

        self::assertTrue(KfaDeliveryTimeCacheSchema::ensureIndexes());
        self::assertCount(4, $this->db->executedSql);
        self::assertContains(
            'ALTER TABLE `ps_orders` ADD INDEX `idx_kfadt_orders_date_upd` (`date_upd`, `id_order`, `id_cart`), ALGORITHM=INPLACE, LOCK=NONE',
            $this->db->executedSql
        );
        self::assertContains(
            'ALTER TABLE `ps_kfadeliverytime_cart` ADD INDEX `idx_kfadt_cart_date_upd` (`date_upd`, `id_cart`), '
                . 'ADD INDEX `idx_kfadt_cart_date_start` (`date_start`, `id_cart`), '
                . 'ADD INDEX `idx_kfadt_cart_date_process` (`date_process`, `id_cart`), '
                . 'ALGORITHM=INPLACE, LOCK=NONE',
            $this->db->executedSql
        );
        self::assertContains(
            'ALTER TABLE `ps_kfadeliverytime_history` ADD INDEX `idx_kfadt_history_date_add` (`date_add`, `id_cart`), ALGORITHM=INPLACE, LOCK=NONE',
            $this->db->executedSql
        );
        self::assertStringContainsString(
            'ADD INDEX `idx_id_order` (`id_order`), ADD INDEX `idx_id_cart` (`id_cart`)',
            $this->db->executedSql[3]
        );
        self::assertStringContainsString(
            'ADD INDEX `idx_kfa_cache_dates` (`id_cache`, `date_start`, `date_end`), ALGORITHM=INPLACE, LOCK=NONE',
            $this->db->executedSql[3]
        );
    }

    public function testExistingIndexesMakeReconciliationWriteFree(): void
    {
        $this->db->executeSResults = $this->matchingIndexResults();

        self::assertTrue(KfaDeliveryTimeCacheSchema::ensureIndexes());
        self::assertCount(10, $this->db->executeSQueries);
        self::assertSame(array_fill(0, 10, false), $this->db->executeSUseCacheArgs);
        self::assertSame([], $this->db->executedSql);
    }

    public function testSameNameIndexWithWrongColumnsIsRebuilt(): void
    {
        $this->db->executeSResults = [[[
            'Key_name' => 'idx_kfadt_orders_date_upd',
            'Seq_in_index' => 1,
            'Column_name' => 'id_order',
            'Sub_part' => null,
        ]]];
        $this->db->executeSResults = array_merge($this->db->executeSResults, array_fill(0, 9, []));

        self::assertTrue(KfaDeliveryTimeCacheSchema::ensureIndexes());
        self::assertContains(
            'ALTER TABLE `ps_orders` DROP INDEX `idx_kfadt_orders_date_upd`, '
                . 'ADD INDEX `idx_kfadt_orders_date_upd` (`date_upd`, `id_order`, `id_cart`), '
                . 'ALGORITHM=INPLACE, LOCK=NONE',
            $this->db->executedSql
        );
    }

    public function testSameNameUniqueIndexIsRebuiltAsNonUnique(): void
    {
        $this->db->executeSResults = $this->matchingIndexResults();
        foreach ($this->db->executeSResults[5] as &$row) {
            $row['Non_unique'] = 0;
        }
        unset($row);

        self::assertTrue(KfaDeliveryTimeCacheSchema::ensureIndexes());
        self::assertContains(
            'ALTER TABLE `ps_kfadeliverytime_cached_orders` DROP INDEX `idx_id_order`, '
                . 'ADD INDEX `idx_id_order` (`id_order`), ALGORITHM=INPLACE, LOCK=NONE',
            $this->db->executedSql
        );
    }

    public function testSameNameIgnoredIndexIsRebuiltAsUsable(): void
    {
        $this->db->executeSResults = $this->matchingIndexResults();
        foreach ($this->db->executeSResults[5] as &$row) {
            $row['Ignored'] = 'YES';
        }
        unset($row);

        self::assertTrue(KfaDeliveryTimeCacheSchema::ensureIndexes());
        self::assertContains(
            'ALTER TABLE `ps_kfadeliverytime_cached_orders` DROP INDEX `idx_id_order`, '
                . 'ADD INDEX `idx_id_order` (`id_order`), ALGORITHM=INPLACE, LOCK=NONE',
            $this->db->executedSql
        );
    }

    public function testFailedAlterIsAcceptedOnlyWhenConcurrentInstallerCreatedIndex(): void
    {
        $this->db->executeSResults = array_fill(0, 10, []);
        $this->db->executeSResults[] = $this->indexRows(
            'idx_kfadt_orders_date_upd',
            ['date_upd', 'id_order', 'id_cart']
        );
        $this->db->executeResults = [false];

        self::assertTrue(KfaDeliveryTimeCacheSchema::ensureIndexes());
        self::assertCount(11, $this->db->executeSQueries);
        self::assertSame(array_fill(0, 11, false), $this->db->executeSUseCacheArgs);
        self::assertCount(4, $this->db->executedSql);
    }

    public function testFailedAlterAndFailedRecheckStopUpgrade(): void
    {
        $this->db->executeSResults = array_fill(0, 10, []);
        $this->db->executeSResults[] = false;
        $this->db->executeResults = [false];

        self::assertFalse(KfaDeliveryTimeCacheSchema::ensureIndexes());
        self::assertCount(11, $this->db->executeSQueries);
        self::assertCount(1, $this->db->executedSql);
    }

    public function testFailedAlterWithSameNameWrongDefinitionStopsUpgrade(): void
    {
        $this->db->executeSResults = array_fill(0, 10, []);
        $this->db->executeSResults[] = $this->indexRows('idx_kfadt_orders_date_upd', ['id_order']);
        $this->db->executeResults = [false];

        self::assertFalse(KfaDeliveryTimeCacheSchema::ensureIndexes());
        self::assertCount(11, $this->db->executeSQueries);
        self::assertCount(1, $this->db->executedSql);
    }

    /** @return list<list<array<string, mixed>>> */
    private function matchingIndexResults(): array
    {
        return [
            $this->indexRows('idx_kfadt_orders_date_upd', ['date_upd', 'id_order', 'id_cart']),
            $this->indexRows('idx_kfadt_cart_date_upd', ['date_upd', 'id_cart']),
            $this->indexRows('idx_kfadt_cart_date_start', ['date_start', 'id_cart']),
            $this->indexRows('idx_kfadt_cart_date_process', ['date_process', 'id_cart']),
            $this->indexRows('idx_kfadt_history_date_add', ['date_add', 'id_cart']),
            $this->indexRows('idx_id_order', ['id_order']),
            $this->indexRows('idx_id_cart', ['id_cart']),
            $this->indexRows('idx_date_start', ['date_start']),
            $this->indexRows('idx_date_end', ['date_end']),
            $this->indexRows('idx_kfa_cache_dates', ['id_cache', 'date_start', 'date_end']),
        ];
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
