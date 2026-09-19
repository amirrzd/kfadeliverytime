<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CacheSameSecondInvalidationMariaDbTest extends TestCase
{
    private PDO $pdo;
    private string $snapshotTimestamp;

    protected function setUp(): void
    {
        $this->pdo = Db::getInstance()->pdo();
        $this->snapshotTimestamp = date('Y-m-d H:i:s');

        foreach ([
            'test_kfadeliverytime_cached_orders',
            'test_kfadeliverytime_cached_orders_list',
            'test_kfadeliverytime_history',
            'test_kfadeliverytime_cart',
            'test_orders',
        ] as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS `$table`");
        }

        $this->createTables();
        $this->seedSnapshot();
    }

    public function testIdenticalProjectionAtSnapshotSecondRemainsValid(): void
    {
        self::assertTrue($this->isValid());
    }

    public function testCartProjectionChangeAtSnapshotSecondInvalidates(): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE `test_kfadeliverytime_cart` SET `value` = ?, `date_upd` = ? WHERE `id_cart` = 10'
        );
        self::assertTrue($statement->execute(['delivery window', $this->snapshotTimestamp]));

        self::assertFalse($this->isValid());
    }

    public function testOrderProjectionChangeAtSnapshotSecondInvalidates(): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE `test_orders` SET `total_paid` = ?, `date_upd` = ? WHERE `id_order` = 20'
        );
        self::assertTrue($statement->execute(['25.000000', $this->snapshotTimestamp]));

        self::assertFalse($this->isValid());
    }

    public function testOrderLeavingFilterAtSnapshotSecondInvalidates(): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE `test_orders` SET `valid` = 0, `date_upd` = ? WHERE `id_order` = 20'
        );
        self::assertTrue($statement->execute([$this->snapshotTimestamp]));

        self::assertFalse($this->isValid());
    }

    public function testDeletedCartRecordedAtSnapshotSecondInvalidates(): void
    {
        self::assertSame(1, $this->pdo->exec('DELETE FROM `test_kfadeliverytime_cart` WHERE `id_cart` = 10'));
        $statement = $this->pdo->prepare(
            'INSERT INTO `test_kfadeliverytime_history` (`id_cart`, `date_add`) VALUES (10, ?)'
        );
        self::assertTrue($statement->execute([$this->snapshotTimestamp]));

        self::assertFalse($this->isValid());
    }

    private function createTables(): void
    {
        $this->pdo->exec(
            'CREATE TABLE `test_orders` (
                `id_order` int(10) unsigned NOT NULL,
                `id_cart` int(10) unsigned NOT NULL,
                `id_currency` int(10) unsigned NOT NULL,
                `total_paid` decimal(20,6) NOT NULL,
                `valid` tinyint(1) unsigned NOT NULL,
                `current_state` int(10) unsigned NOT NULL,
                `id_carrier` int(10) unsigned NOT NULL,
                `date_add` datetime NOT NULL,
                `date_upd` datetime NOT NULL,
                PRIMARY KEY (`id_order`),
                KEY `idx_id_cart` (`id_cart`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci'
        );
        $this->pdo->exec(
            'CREATE TABLE `test_kfadeliverytime_cart` (
                `id_cart` int(10) unsigned NOT NULL,
                `value` text DEFAULT NULL,
                `approximate` tinyint(3) unsigned NOT NULL DEFAULT 0,
                `date_process` datetime DEFAULT NULL,
                `date_start` datetime DEFAULT NULL,
                `date_end` datetime DEFAULT NULL,
                `date_add` datetime NOT NULL,
                `date_upd` datetime NOT NULL,
                PRIMARY KEY (`id_cart`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci'
        );
        $this->pdo->exec(
            'CREATE TABLE `test_kfadeliverytime_history` (
                `id_history` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `id_cart` int(10) unsigned NOT NULL,
                `date_add` datetime NOT NULL,
                PRIMARY KEY (`id_history`),
                KEY `idx_kfadt_history_date_add` (`date_add`, `id_cart`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci'
        );
        $this->pdo->exec(
            'CREATE TABLE `test_kfadeliverytime_cached_orders_list` (
                `id_cache` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `valid` tinyint(1) unsigned DEFAULT NULL,
                `current_state` varchar(1024) DEFAULT NULL,
                `id_carrier` varchar(1024) DEFAULT NULL,
                `date_add` datetime NOT NULL,
                PRIMARY KEY (`id_cache`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci'
        );
        $this->pdo->exec(
            'CREATE TABLE `test_kfadeliverytime_cached_orders` (
                `id_cache` int(10) unsigned NOT NULL,
                `id_order` int(10) unsigned NOT NULL,
                `id_cart` int(10) unsigned NOT NULL,
                `id_currency` int(10) unsigned NOT NULL,
                `total_paid` decimal(20,6) NOT NULL,
                `date_process` datetime DEFAULT NULL,
                `date_start` datetime DEFAULT NULL,
                `date_end` datetime DEFAULT NULL,
                `value` text DEFAULT NULL,
                PRIMARY KEY (`id_cache`, `id_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci'
        );
    }

    private function seedSnapshot(): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO `test_orders`
                (`id_order`, `id_cart`, `id_currency`, `total_paid`, `valid`, `current_state`,
                 `id_carrier`, `date_add`, `date_upd`)
             VALUES (20, 10, 1, 10.000000, 1, 2, 7, ?, ?)'
        );
        self::assertTrue($statement->execute([$this->snapshotTimestamp, $this->snapshotTimestamp]));

        $statement = $this->pdo->prepare(
            'INSERT INTO `test_kfadeliverytime_cart`
                (`id_cart`, `value`, `approximate`, `date_process`, `date_start`, `date_end`, `date_add`, `date_upd`)
             VALUES (10, ?, 0, NULL, ?, ?, ?, ?)'
        );
        self::assertTrue($statement->execute([
            'Delivery Window',
            $this->snapshotTimestamp,
            $this->snapshotTimestamp,
            $this->snapshotTimestamp,
            $this->snapshotTimestamp,
        ]));

        $statement = $this->pdo->prepare(
            'INSERT INTO `test_kfadeliverytime_cached_orders_list`
                (`id_cache`, `valid`, `current_state`, `id_carrier`, `date_add`)
             VALUES (1, 1, NULL, NULL, ?)'
        );
        self::assertTrue($statement->execute([$this->snapshotTimestamp]));

        $statement = $this->pdo->prepare(
            'INSERT INTO `test_kfadeliverytime_cached_orders`
                (`id_cache`, `id_order`, `id_cart`, `id_currency`, `total_paid`,
                 `date_process`, `date_start`, `date_end`, `value`)
             VALUES (1, 20, 10, 1, 10.000000, NULL, ?, ?, ?)'
        );
        self::assertTrue($statement->execute([
            $this->snapshotTimestamp,
            $this->snapshotTimestamp,
            'Delivery Window',
        ]));
    }

    private function isValid(): bool
    {
        $method = new ReflectionMethod(KfaDeliveryTimeCache::class, 'isValid');
        $method->setAccessible(true);

        return (bool) $method->invoke(new KfaDeliveryTimeCache(['valid' => 1]), 1);
    }
}
