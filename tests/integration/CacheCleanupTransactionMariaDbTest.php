<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CacheCleanupTransactionMariaDbTest extends TestCase
{
    private KfaDeliveryTimeMariaDbAdapter $db;
    private PDO $pdo;
    private string $lockName;
    private int $connectionId;

    protected function setUp(): void
    {
        $this->db = Db::getInstance();
        $this->pdo = $this->db->pdo();
        $this->lockName = 'kfadeliverytime-cleanup-integration';

        $this->pdo->exec('DROP TABLE IF EXISTS `test_kfadeliverytime_cached_orders`');
        $this->pdo->exec('DROP TABLE IF EXISTS `test_kfadeliverytime_cached_orders_list`');
        $this->pdo->exec('DROP TABLE IF EXISTS `test_cache_cleanup_sentinel`');
        $this->pdo->exec(
            'CREATE TABLE `test_kfadeliverytime_cached_orders_list` (
                `id_cache` int(10) unsigned NOT NULL,
                PRIMARY KEY (`id_cache`)
            ) ENGINE=InnoDB'
        );
        $this->pdo->exec(
            'CREATE TABLE `test_kfadeliverytime_cached_orders` (
                `id_cache` int(10) unsigned NOT NULL,
                `id_order` int(10) unsigned NOT NULL,
                PRIMARY KEY (`id_cache`, `id_order`)
            ) ENGINE=InnoDB'
        );
        $this->pdo->exec(
            'CREATE TABLE `test_cache_cleanup_sentinel` (
                `id` int(10) unsigned NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB'
        );
        $this->pdo->exec('INSERT INTO `test_kfadeliverytime_cached_orders_list` (`id_cache`) VALUES (42)');
        $this->pdo->exec(
            'INSERT INTO `test_kfadeliverytime_cached_orders` (`id_cache`, `id_order`) VALUES (42, 20)'
        );

        $statement = $this->pdo->prepare('SELECT GET_LOCK(?, 0) AS acquired, CONNECTION_ID() AS connection_id');
        self::assertTrue($statement->execute([$this->lockName]));
        $row = $statement->fetch();
        self::assertIsArray($row);
        self::assertSame(1, (int) $row['acquired']);
        $this->connectionId = (int) $row['connection_id'];
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $statement = $this->pdo->prepare('DO RELEASE_LOCK(?)');
        $statement->execute([$this->lockName]);
    }

    public function testPublishedCleanupCommitsBothDeletes(): void
    {
        self::assertTrue($this->deleteCache());
        self::assertSame(0, $this->rowCount('test_kfadeliverytime_cached_orders'));
        self::assertSame(0, $this->rowCount('test_kfadeliverytime_cached_orders_list'));
    }

    public function testMetadataDeleteFailureRollsBackSnapshotDelete(): void
    {
        $this->db->failNextDeleteFor('kfadeliverytime_cached_orders_list');

        self::assertFalse($this->deleteCache());
        self::assertSame(1, $this->rowCount('test_kfadeliverytime_cached_orders'));
        self::assertSame(1, $this->rowCount('test_kfadeliverytime_cached_orders_list'));
    }

    public function testCleanupDoesNotCommitOrMutateCallerTransaction(): void
    {
        self::assertTrue($this->pdo->beginTransaction());
        self::assertSame(1, $this->pdo->exec('INSERT INTO `test_cache_cleanup_sentinel` (`id`) VALUES (1)'));

        self::assertFalse($this->deleteCache());
        self::assertTrue($this->pdo->inTransaction());
        self::assertSame(1, $this->rowCount('test_kfadeliverytime_cached_orders'));
        self::assertSame(1, $this->rowCount('test_kfadeliverytime_cached_orders_list'));

        self::assertTrue($this->pdo->rollBack());
        self::assertSame(0, $this->rowCount('test_cache_cleanup_sentinel'));
    }

    private function deleteCache(): bool
    {
        $method = new ReflectionMethod(KfaDeliveryTimeCache::class, 'deleteCache');
        $method->setAccessible(true);

        return (bool) $method->invoke(null, 42, $this->lockName, $this->connectionId);
    }

    private function rowCount(string $table): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    }
}
