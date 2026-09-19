<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CacheSchemaReconciliationMariaDbTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = Db::getInstance()->pdo();
        $this->pdo->exec('DROP TABLE IF EXISTS `test_kfadeliverytime_cached_orders`');
        $this->pdo->exec('DROP TABLE IF EXISTS `test_kfadeliverytime_history`');
        $this->pdo->exec('DROP TABLE IF EXISTS `test_kfadeliverytime_cart`');
        $this->pdo->exec('DROP TABLE IF EXISTS `test_orders`');

        $this->pdo->exec(
            'CREATE TABLE `test_orders` (
                `id_order` int(10) unsigned NOT NULL,
                `id_cart` int(10) unsigned NOT NULL,
                `date_upd` datetime NOT NULL,
                PRIMARY KEY (`id_order`),
                INDEX `idx_kfadt_orders_date_upd` (`id_order`)
            ) ENGINE=InnoDB'
        );
        $this->pdo->exec(
            'CREATE TABLE `test_kfadeliverytime_cart` (
                `id_cart` int(10) unsigned NOT NULL,
                `approximate` tinyint(1) unsigned NOT NULL DEFAULT 0,
                `date_process` datetime DEFAULT NULL,
                `date_start` datetime DEFAULT NULL,
                `date_upd` datetime NOT NULL,
                PRIMARY KEY (`id_cart`)
            ) ENGINE=InnoDB'
        );
        $this->pdo->exec(
            'CREATE TABLE `test_kfadeliverytime_history` (
                `id_history` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `id_cart` int(10) unsigned NOT NULL,
                `date_add` datetime NOT NULL,
                PRIMARY KEY (`id_history`)
            ) ENGINE=InnoDB'
        );
        $this->pdo->exec(
            'CREATE TABLE `test_kfadeliverytime_cached_orders` (
                `id_cache` int(10) unsigned NOT NULL,
                `id_order` int(10) unsigned NOT NULL,
                `id_cart` int(10) unsigned NOT NULL,
                `date_start` datetime DEFAULT NULL,
                `date_end` datetime DEFAULT NULL,
                PRIMARY KEY (`id_cache`, `id_order`),
                UNIQUE INDEX `idx_id_order` (`id_order`)
            ) ENGINE=InnoDB'
        );
    }

    public function testSameNameWrongDefinitionIsRebuiltExactly(): void
    {
        self::assertTrue(KfaDeliveryTimeCacheSchema::ensureIndexes());
        self::assertSame(
            ['date_upd', 'id_order', 'id_cart'],
            $this->indexColumns('test_orders', 'idx_kfadt_orders_date_upd')
        );
        self::assertSame(
            ['date_upd', 'id_cart'],
            $this->indexColumns('test_kfadeliverytime_cart', 'idx_kfadt_cart_date_upd')
        );
        self::assertSame(
            ['date_start', 'id_cart'],
            $this->indexColumns('test_kfadeliverytime_cart', 'idx_kfadt_cart_date_start')
        );
        self::assertSame(
            ['date_process', 'id_cart'],
            $this->indexColumns('test_kfadeliverytime_cart', 'idx_kfadt_cart_date_process')
        );
        self::assertSame(
            ['date_add', 'id_cart'],
            $this->indexColumns('test_kfadeliverytime_history', 'idx_kfadt_history_date_add')
        );
        self::assertSame(
            ['id_cache', 'date_start', 'date_end'],
            $this->indexColumns('test_kfadeliverytime_cached_orders', 'idx_kfa_cache_dates')
        );
        self::assertSame(1, $this->indexNonUnique('test_kfadeliverytime_cached_orders', 'idx_id_order'));

        self::assertTrue(KfaDeliveryTimeCacheSchema::ensureIndexes());
    }

    /** @return list<string> */
    private function indexColumns(string $table, string $name): array
    {
        $statement = $this->pdo->prepare(
            "SELECT `Column_name` FROM information_schema.statistics
             WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? AND `INDEX_NAME` = ?
             ORDER BY `SEQ_IN_INDEX`"
        );
        self::assertTrue($statement->execute([$table, $name]));

        return array_map(
            static fn (array $row): string => (string) $row['Column_name'],
            $statement->fetchAll()
        );
    }

    private function indexNonUnique(string $table, string $name): int
    {
        $statement = $this->pdo->prepare(
            'SELECT `NON_UNIQUE` FROM information_schema.statistics
             WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? AND `INDEX_NAME` = ?
             LIMIT 1'
        );
        self::assertTrue($statement->execute([$table, $name]));

        return (int) $statement->fetchColumn();
    }
}
