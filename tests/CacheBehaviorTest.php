<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CacheBehaviorTest extends TestCase
{
    private KfaDeliveryTimeTestDb $db;

    protected function setUp(): void
    {
        $this->db = Db::getInstance();
        $this->db->reset();
    }

    public function testValidSnapshotIsReusedWithoutWrites(): void
    {
        $snapshotTimestamp = date('Y-m-d') . ' 10:00:00';
        $this->db->getValueResults = [41, $snapshotTimestamp, false];

        $idCache = (new KfaDeliveryTimeCache([
            'valid' => 1,
            'current_state' => '2,3',
            'id_carrier' => '7,8',
        ]))->getIdCache();

        self::assertSame(41, $idCache);
        self::assertSame([], $this->db->inserts);
        self::assertSame([], $this->db->updates);
        self::assertSame([], $this->db->deletes);
        self::assertSame([], $this->db->executedSql);
        self::assertSame([], $this->db->getValueResults);
        self::assertStringContainsString('ORDER BY `date_add` DESC, `id_cache` DESC', $this->db->getValueQueries[0]);
    }

    public function testValidityProbeTargetsOnlyCachedOrCurrentlyMatchingOrders(): void
    {
        $snapshotTimestamp = date('Y-m-d') . ' 10:00:00';
        $this->db->getValueResults = [$snapshotTimestamp, false];
        $cache = new KfaDeliveryTimeCache([
            'valid' => 1,
            'current_state' => '2,3',
            'id_carrier' => '7,8',
        ]);

        self::assertTrue($this->invokeIsValid($cache, 41));

        $probeSql = $this->db->getValueQueries[1];
        self::assertSame(3, substr_count($probeSql, 'EXISTS('));
        self::assertStringContainsString('co.`id_cache` = 41', $probeSql);
        self::assertStringContainsString("h.`date_add` >= '$snapshotTimestamp'", $probeSql);
        self::assertStringContainsString('kc.`id_cart` IS NULL', $probeSql);
        self::assertStringContainsString("kc.`date_upd` >= '$snapshotTimestamp'", $probeSql);
        self::assertStringContainsString("o.`date_upd` >= '$snapshotTimestamp'", $probeSql);
        self::assertStringContainsString('co.`id_order` IS NOT NULL', $probeSql);
        self::assertStringContainsString('NOT (o.`valid` = 1', $probeSql);
        self::assertStringContainsString('o.`valid` = 1', $probeSql);
        self::assertStringContainsString('o.`current_state` IN(2,3)', $probeSql);
        self::assertStringContainsString('o.`id_carrier` IN(7,8)', $probeSql);
        self::assertStringContainsString('o.`date_add` >=', $probeSql);
        self::assertStringContainsString('BINARY co.`value` <=> BINARY kc.`value`', $probeSql);
        self::assertStringContainsString('co.`date_process` <=> kc.`date_process`', $probeSql);
        self::assertStringContainsString('co.`date_start` <=> kc.`date_start`', $probeSql);
        self::assertStringContainsString('co.`date_end` <=> kc.`date_end`', $probeSql);
        self::assertStringContainsString('co.`id_cart` <=> o.`id_cart`', $probeSql);
        self::assertStringContainsString('co.`id_currency` <=> o.`id_currency`', $probeSql);
        self::assertStringContainsString('co.`total_paid` <=> o.`total_paid`', $probeSql);
        self::assertDoesNotMatchRegularExpression(
            '/FROM `ps_kfadeliverytime_cached_orders` co\s+LEFT JOIN `ps_orders`/',
            $probeSql
        );
        self::assertStringNotContainsString('kc.`date_upd` > ', $probeSql);
        self::assertStringNotContainsString('o.`date_upd` > ', $probeSql);
    }

    public function testSnapshotFromPreviousDayExpiresBeforeAnySourceProbe(): void
    {
        $this->db->getValueResults = [date('Y-m-d H:i:s', strtotime('-1 day'))];

        self::assertFalse($this->invokeIsValid(new KfaDeliveryTimeCache(['valid' => 1]), 41));
        self::assertCount(1, $this->db->getValueQueries);
    }

    public function testRelevantSourceChangeInvalidatesSnapshot(): void
    {
        $this->db->getValueResults = [date('Y-m-d') . ' 10:00:00', 1];

        self::assertFalse($this->invokeIsValid(new KfaDeliveryTimeCache(['valid' => 1]), 41));
        self::assertCount(2, $this->db->getValueQueries);
    }

    public function testMissingSnapshotMetadataIsInvalid(): void
    {
        $this->db->getValueResults = [false];

        self::assertFalse($this->invokeIsValid(new KfaDeliveryTimeCache(['valid' => 1]), 41));
        self::assertCount(1, $this->db->getValueQueries);
    }

    public function testStaleSnapshotIsPublishedBeforeExpiredGenerationCleanup(): void
    {
        $snapshotTimestamp = date('Y-m-d') . ' 10:00:00';
        $this->db->getValueResults = [
            41,
            $snapshotTimestamp,
            123,
            701,
            41,
            $snapshotTimestamp,
            123,
            1,
            1,
            0,
            1,
        ];
        $this->db->executeSResults = [[], [['id_cache' => 40]]];
        $this->db->insertId = 42;

        $idCache = (new KfaDeliveryTimeCache(['valid' => 1]))->getIdCache();

        self::assertSame(42, $idCache);
        self::assertSame(255, $this->db->inserts[0]['data']['valid']);
        self::assertStringStartsWith('building:', $this->db->inserts[0]['data']['current_state']);
        self::assertSame(1, $this->db->updates[0]['data']['valid']);
        self::assertStringStartsWith('`id_cache` = 42 AND `valid` = 255', $this->db->updates[0]['where']);
        self::assertStringContainsString('CONNECTION_ID() = 701', $this->db->updates[0]['where']);
        self::assertSame('kfadeliverytime_cached_orders', $this->db->deletes[0]['table']);
        self::assertSame('kfadeliverytime_cached_orders_list', $this->db->deletes[1]['table']);
        self::assertStringStartsWith('`id_cache` = 40', $this->db->deletes[0]['where']);
        self::assertStringContainsString('IS_USED_LOCK', $this->db->deletes[0]['where']);
        self::assertSame('insert:kfadeliverytime_cached_orders_list', $this->db->operations[0]);
        self::assertStringStartsWith('execute:INSERT INTO', $this->db->operations[1]);
        self::assertSame('update:kfadeliverytime_cached_orders_list', $this->db->operations[2]);
        self::assertSame('execute:START TRANSACTION', $this->db->operations[3]);
        self::assertSame('delete:kfadeliverytime_cached_orders', $this->db->operations[4]);
        self::assertSame('delete:kfadeliverytime_cached_orders_list', $this->db->operations[5]);
        self::assertSame('execute:COMMIT', $this->db->operations[6]);
        self::assertStringStartsWith('execute:DO RELEASE_LOCK', $this->db->operations[7]);
        self::assertStringContainsString('`valid` = 255', $this->db->executeSQueries[0]);
        self::assertStringContainsString('INTERVAL 60 MINUTE', $this->db->executeSQueries[0]);
        self::assertStringContainsString('LIMIT 10, 18446744073709551615', $this->db->executeSQueries[1]);
    }

    public function testColdCacheStagesRowsThenPublishesOneFilteredSnapshot(): void
    {
        $this->db->getValueResults = [false, 701, false, 1, 1];
        $this->db->executeSResults = [[]];
        $this->db->insertId = 42;

        $idCache = (new KfaDeliveryTimeCache([
            'valid' => 1,
            'current_state' => '2,3',
            'id_carrier' => '7,8',
        ]))->getIdCache();

        self::assertSame(42, $idCache);
        self::assertCount(1, $this->db->inserts);
        self::assertCount(1, $this->db->updates);
        self::assertSame(255, $this->db->inserts[0]['data']['valid']);
        self::assertSame(1, $this->db->updates[0]['data']['valid']);
        self::assertSame('2,3', $this->db->updates[0]['data']['current_state']);
        self::assertSame('7,8', $this->db->updates[0]['data']['id_carrier']);
        self::assertArrayNotHasKey('date_add', $this->db->updates[0]['data']);

        $snapshotSql = $this->db->executedSql[0];
        self::assertStringContainsString('INSERT INTO `ps_kfadeliverytime_cached_orders`', $snapshotSql);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $snapshotSql);
        self::assertStringContainsString('o.`valid` = 1', $snapshotSql);
        self::assertStringContainsString('o.`current_state` IN(2,3)', $snapshotSql);
        self::assertStringContainsString('o.`id_carrier` IN(7,8)', $snapshotSql);
        self::assertStringContainsString('o.`date_add` >=', $snapshotSql);
        self::assertStringNotContainsString('LEFT JOIN `ps_kfadeliverytime_cached_orders`', $snapshotSql);
        self::assertStringContainsString('CONNECTION_ID()', $this->db->getValueQueries[1]);
        self::assertStringContainsString('IS_USED_LOCK', $this->db->getValueQueries[3]);
        self::assertStringContainsString('IS_USED_LOCK', $this->db->getValueQueries[4]);
    }

    public function testAdvisoryLockProbesBypassDbQueryCache(): void
    {
        $this->db->getValueResults = [false, 701, false, 1, 1];
        $this->db->executeSResults = [[]];
        $this->db->insertId = 42;

        self::assertSame(42, (new KfaDeliveryTimeCache(['valid' => 1]))->getIdCache());

        $lockProbeIndexes = [];
        foreach ($this->db->getValueQueries as $index => $query) {
            if (str_contains($query, 'GET_LOCK') || str_contains($query, 'IS_USED_LOCK')) {
                $lockProbeIndexes[] = $index;
                self::assertFalse($this->db->getValueUseCacheArgs[$index]);
            }
        }
        self::assertSame([1, 3, 4], $lockProbeIndexes);
    }

    public function testNumericFiltersAreCanonicalizedBeforeSqlInterpolation(): void
    {
        $this->db->getValueResults = [false, 701, false, 1, 1];
        $this->db->executeSResults = [[]];
        $this->db->insertId = 42;

        $idCache = (new KfaDeliveryTimeCache([
            'current_state' => '003, 2, 2, -1, +4, 1e2, 5) OR 1=1 --, 4294967296',
            'id_carrier' => '008, 7, 07, -1, 7.0, 4294967295, 4294967296',
        ]))->getIdCache();

        self::assertSame(42, $idCache);
        self::assertSame('-1,2,3', $this->db->updates[0]['data']['current_state']);
        self::assertSame('7,8,4294967295', $this->db->updates[0]['data']['id_carrier']);

        $snapshotSql = $this->db->executedSql[0];
        self::assertStringContainsString('o.`current_state` IN(-1,2,3)', $snapshotSql);
        self::assertStringContainsString('o.`id_carrier` IN(7,8,4294967295)', $snapshotSql);
        self::assertStringNotContainsString('OR 1=1', $snapshotSql);
        self::assertStringNotContainsString('1e2', $snapshotSql);
        self::assertStringNotContainsString('7.0', $snapshotSql);
        self::assertStringNotContainsString('4294967296', $snapshotSql);
    }

    public function testAllInvalidNumericFiltersUseNoMatchSentinel(): void
    {
        $this->db->getValueResults = [false, 701, false, 1, 1];
        $this->db->executeSResults = [[]];
        $this->db->insertId = 42;

        $idCache = (new KfaDeliveryTimeCache([
            'current_state' => 'invalid, +1, 1.0, -2',
            'id_carrier' => 'invalid, +7, 7.0, -1',
        ]))->getIdCache();

        self::assertSame(42, $idCache);
        self::assertSame('-1', $this->db->updates[0]['data']['current_state']);
        self::assertSame('-1', $this->db->updates[0]['data']['id_carrier']);
        self::assertStringContainsString('o.`current_state` IN(-1)', $this->db->executedSql[0]);
        self::assertStringContainsString('o.`id_carrier` IN(-1)', $this->db->executedSql[0]);
    }

    public function testFailedStagingMetadataInsertNeverPopulatesOrPublishes(): void
    {
        $this->db->getValueResults = [false, 701, false];
        $this->db->insertResults = [false];

        $idCache = (new KfaDeliveryTimeCache(['valid' => 1]))->getIdCache();

        self::assertSame(0, $idCache);
        self::assertSame([], $this->db->updates);
        self::assertSame([], $this->db->deletes);
        self::assertCount(1, $this->db->executedSql);
        self::assertStringContainsString('RELEASE_LOCK', $this->db->executedSql[0]);
    }

    public function testFailedExpiredStagingQueryDoesNotCreateAnotherGeneration(): void
    {
        $this->db->getValueResults = [false, 701, false];
        $this->db->executeSResults = [false];

        $idCache = (new KfaDeliveryTimeCache(['valid' => 1]))->getIdCache();

        self::assertSame(0, $idCache);
        self::assertSame([], $this->db->inserts);
        self::assertSame([], $this->db->updates);
        self::assertSame([], $this->db->deletes);
        self::assertCount(1, $this->db->executedSql);
        self::assertStringContainsString('RELEASE_LOCK', $this->db->executedSql[0]);
    }

    public function testExpiredStagingIsCleanedBeforeFailedBuildAttempt(): void
    {
        $this->db->getValueResults = [false, 701, false, 1, 1];
        $this->db->executeSResults = [[['id_cache' => 40]]];
        $this->db->insertResults = [false];

        $idCache = (new KfaDeliveryTimeCache(['valid' => 1]))->getIdCache();

        self::assertSame(0, $idCache);
        self::assertCount(2, $this->db->deletes);
        self::assertSame('kfadeliverytime_cached_orders', $this->db->deletes[0]['table']);
        self::assertStringContainsString('NOT EXISTS', $this->db->deletes[0]['where']);
        self::assertSame('kfadeliverytime_cached_orders_list', $this->db->deletes[1]['table']);
        self::assertStringContainsString('`id_cache` = 40 AND `valid` = 255', $this->db->deletes[1]['where']);
        self::assertSame('delete:kfadeliverytime_cached_orders', $this->db->operations[0]);
        self::assertSame('delete:kfadeliverytime_cached_orders_list', $this->db->operations[1]);
        self::assertSame('insert:kfadeliverytime_cached_orders_list', $this->db->operations[2]);
        self::assertStringContainsString('`valid` = 255', $this->db->executeSQueries[0]);
        self::assertStringContainsString('INTERVAL 60 MINUTE', $this->db->executeSQueries[0]);
    }

    public function testLockLoserReusesSnapshotPublishedByWinnerWithoutWrites(): void
    {
        $this->db->getValueResults = [false, 0, 55, date('Y-m-d') . ' 10:00:00', false];

        $idCache = (new KfaDeliveryTimeCache(['valid' => 1]))->getIdCache();

        self::assertSame(55, $idCache);
        self::assertSame([], $this->db->inserts);
        self::assertSame([], $this->db->updates);
        self::assertSame([], $this->db->deletes);
        self::assertSame([], $this->db->executedSql);
    }

    public function testLockWinnerRechecksSnapshotPublishedWhileWaiting(): void
    {
        $this->db->getValueResults = [false, 701, 55, date('Y-m-d') . ' 10:00:00', false];

        $idCache = (new KfaDeliveryTimeCache(['valid' => 1]))->getIdCache();

        self::assertSame(55, $idCache);
        self::assertSame([], $this->db->inserts);
        self::assertSame([], $this->db->updates);
        self::assertCount(1, $this->db->executedSql);
        self::assertStringContainsString('RELEASE_LOCK', $this->db->executedSql[0]);
    }

    public function testColdLockTimeoutDoesNotBuildWithoutOwnership(): void
    {
        $this->db->getValueResults = [false, 0, false];

        $idCache = (new KfaDeliveryTimeCache(['valid' => 1]))->getIdCache();

        self::assertSame(0, $idCache);
        self::assertSame([], $this->db->inserts);
        self::assertSame([], $this->db->updates);
        self::assertSame([], $this->db->executedSql);
        self::assertMatchesRegularExpression('/GET_LOCK\([^,]+, 0\)/', $this->db->getValueQueries[1]);
    }

    public function testStaleLockTimeoutDoesNotReturnKnownInvalidSnapshot(): void
    {
        $snapshotTimestamp = date('Y-m-d') . ' 10:00:00';
        $this->db->getValueResults = [
            41,
            $snapshotTimestamp,
            123,
            0,
            41,
            $snapshotTimestamp,
            123,
        ];

        self::assertSame(0, (new KfaDeliveryTimeCache(['valid' => 1]))->getIdCache());
        self::assertSame([], $this->db->inserts);
        self::assertSame([], $this->db->updates);
    }

    public function testConnectionLossAfterMetadataLeavesOnlyInvisibleStagingRow(): void
    {
        $this->db->getValueResults = [false, 701, false, 0, 0];
        $this->db->insertId = 42;

        $idCache = (new KfaDeliveryTimeCache(['valid' => 1]))->getIdCache();

        self::assertSame(0, $idCache);
        self::assertSame(255, $this->db->inserts[0]['data']['valid']);
        self::assertSame([], $this->db->updates);
        self::assertSame([], $this->db->deletes);
        self::assertCount(1, $this->db->executedSql);
        self::assertStringContainsString('RELEASE_LOCK', $this->db->executedSql[0]);
    }

    public function testConnectionLossAfterSnapshotDoesNotPublishStagingRow(): void
    {
        $this->db->getValueResults = [false, 701, false, 1, 0, 0];
        $this->db->insertId = 42;

        $idCache = (new KfaDeliveryTimeCache(['valid' => 1]))->getIdCache();

        self::assertSame(0, $idCache);
        self::assertCount(2, $this->db->executedSql);
        self::assertStringContainsString('INSERT INTO', $this->db->executedSql[0]);
        self::assertStringContainsString('RELEASE_LOCK', $this->db->executedSql[1]);
        self::assertSame([], $this->db->updates);
        self::assertSame([], $this->db->deletes);
    }

    public function testFailedPublicationLeavesCompletedSnapshotInvisible(): void
    {
        $this->db->getValueResults = [false, 701, false, 1, 1, 1, 1, 1];
        $this->db->insertId = 42;
        $this->db->updateResults = [false];

        $idCache = (new KfaDeliveryTimeCache(['valid' => 1]))->getIdCache();

        self::assertSame(0, $idCache);
        self::assertSame(255, $this->db->inserts[0]['data']['valid']);
        self::assertCount(1, $this->db->updates);
        self::assertCount(2, $this->db->deletes);
        self::assertSame('kfadeliverytime_cached_orders', $this->db->deletes[0]['table']);
        self::assertStringContainsString('NOT EXISTS', $this->db->deletes[0]['where']);
        self::assertStringContainsString('`id_cache` = 42 AND `valid` = 255', $this->db->deletes[1]['where']);
    }

    public function testMissingStagingMetadataCannotBeReportedAsPublished(): void
    {
        $this->db->getValueResults = [false, 701, false, 1, 1, 1, 1, 1];
        $this->db->executeSResults = [[], []];
        $this->db->insertId = 42;
        $this->db->affectedRows = 0;

        $idCache = (new KfaDeliveryTimeCache(['valid' => 1]))->getIdCache();

        self::assertSame(0, $idCache);
        self::assertCount(1, $this->db->updates);
        self::assertCount(2, $this->db->deletes);
        self::assertSame('kfadeliverytime_cached_orders', $this->db->deletes[0]['table']);
        self::assertStringContainsString('NOT EXISTS', $this->db->deletes[0]['where']);
        self::assertStringContainsString('`id_cache` = 42 AND `valid` = 255', $this->db->deletes[1]['where']);
        self::assertStringContainsString(
            'SELECT 1 FROM `ps_kfadeliverytime_cached_orders_list` WHERE `id_cache` = 42',
            $this->db->executeSQueries[1]
        );
        self::assertStringNotContainsString('LIMIT', $this->db->executeSQueries[1]);
        self::assertFalse($this->db->executeSUseCacheArgs[1]);
        self::assertSame([], $this->db->getValueResults);
    }

    public function testFailedMissingMetadataProbeFailsCleanupClosed(): void
    {
        $this->db->getValueResults = [1, 1];
        $this->db->executeSResults = [false];
        $this->db->affectedRows = 0;

        self::assertFalse($this->invokeDeleteStagingCache(42, 'test-lock', 701));

        self::assertCount(2, $this->db->deletes);
        self::assertSame('kfadeliverytime_cached_orders', $this->db->deletes[0]['table']);
        self::assertSame('kfadeliverytime_cached_orders_list', $this->db->deletes[1]['table']);
        self::assertCount(1, $this->db->executeSQueries);
        self::assertStringNotContainsString('LIMIT', $this->db->executeSQueries[0]);
        self::assertFalse($this->db->executeSUseCacheArgs[0]);
        self::assertSame([], $this->db->getValueResults);
    }

    public function testFailedSnapshotPopulationDoesNotPublishMetadata(): void
    {
        $this->db->getValueResults = [false, 701, false, 1, 1, 1, 1];
        $this->db->insertId = 42;
        $this->db->executeResults = [false, true];

        $idCache = (new KfaDeliveryTimeCache(['valid' => 1]))->getIdCache();

        self::assertSame(0, $idCache);
        self::assertSame(255, $this->db->inserts[0]['data']['valid']);
        self::assertSame([], $this->db->updates);
        self::assertCount(2, $this->db->deletes);
        self::assertSame('kfadeliverytime_cached_orders', $this->db->deletes[0]['table']);
        self::assertStringContainsString('NOT EXISTS', $this->db->deletes[0]['where']);
        self::assertStringContainsString('`id_cache` = 42 AND `valid` = 255', $this->db->deletes[1]['where']);
    }

    public function testEquivalentSettingsUseSameScopedLockIdentity(): void
    {
        $this->db->getValueResults = [false, 0, false];
        (new KfaDeliveryTimeCache([
            'valid' => '1',
            'current_state' => '3,2',
            'id_carrier' => '8,7',
        ]))->getIdCache();
        $variantLockQuery = $this->db->getValueQueries[1];

        $this->db->reset();
        $this->db->getValueResults = [false, 0, false];
        (new KfaDeliveryTimeCache([
            'valid' => 1,
            'current_state' => '2,3',
            'id_carrier' => '7,8',
        ]))->getIdCache();

        self::assertSame($variantLockQuery, $this->db->getValueQueries[1]);
    }

    public function testPublishedCacheCleanupRollsBackWhenMetadataDeleteFails(): void
    {
        $this->db->getValueResults = [0];
        $this->db->deleteResults = [true, false];

        self::assertFalse($this->invokeDeleteCache(42, 'test-lock', 701));
        self::assertSame([
            'execute:START TRANSACTION',
            'delete:kfadeliverytime_cached_orders',
            'delete:kfadeliverytime_cached_orders_list',
            'execute:ROLLBACK',
        ], $this->db->operations);
    }

    public function testPublishedCacheCleanupRefusesToNestCallerTransaction(): void
    {
        $this->db->getValueResults = [1];

        self::assertFalse($this->invokeDeleteCache(42, 'test-lock', 701));
        self::assertSame([], $this->db->deletes);
        self::assertSame([], $this->db->operations);
        self::assertSame('SELECT @@in_transaction', $this->db->getValueQueries[0]);
        self::assertFalse($this->db->getValueUseCacheArgs[0]);
    }

    public function testClearRemovesSnapshotAndMetadataInOneTransaction(): void
    {
        $this->db->getValueResults = [701, 0, 1, 1];

        self::assertTrue(KfaDeliveryTimeCache::clear());
        self::assertSame('kfadeliverytime_cached_orders', $this->db->deletes[0]['table']);
        self::assertSame('kfadeliverytime_cached_orders_list', $this->db->deletes[1]['table']);
        self::assertStringContainsString('CONNECTION_ID() = 701', $this->db->deletes[0]['where']);
        self::assertStringContainsString('IS_USED_LOCK', $this->db->deletes[1]['where']);
        self::assertSame('execute:START TRANSACTION', $this->db->operations[0]);
        self::assertSame('execute:COMMIT', $this->db->operations[3]);
        self::assertStringStartsWith('execute:DO RELEASE_LOCK', $this->db->operations[4]);
    }

    public function testClearRollsBackWhenSnapshotDeleteFails(): void
    {
        $this->db->getValueResults = [701, 0];
        $this->db->deleteResults = [false];

        self::assertFalse(KfaDeliveryTimeCache::clear());
        self::assertSame('kfadeliverytime_cached_orders', $this->db->deletes[0]['table']);
        self::assertStringContainsString('CONNECTION_ID() = 701', $this->db->deletes[0]['where']);
        self::assertSame('execute:START TRANSACTION', $this->db->operations[0]);
        self::assertSame('execute:ROLLBACK', $this->db->operations[2]);
    }

    public function testClearDoesNothingWhenBuildLockIsUnavailable(): void
    {
        $this->db->getValueResults = [0];

        self::assertFalse(KfaDeliveryTimeCache::clear());
        self::assertSame([], $this->db->deletes);
        self::assertSame([], $this->db->executedSql);
    }

    public function testClearRollsBackBeforeMetadataWhenConnectionOwnershipIsLost(): void
    {
        $this->db->getValueResults = [701, 0, 0];

        self::assertFalse(KfaDeliveryTimeCache::clear());
        self::assertSame('kfadeliverytime_cached_orders', $this->db->deletes[0]['table']);
        self::assertCount(1, $this->db->deletes);
        self::assertStringContainsString('CONNECTION_ID() = 701', $this->db->deletes[0]['where']);
        self::assertStringContainsString('ROLLBACK', $this->db->executedSql[1]);
        self::assertStringContainsString('RELEASE_LOCK', $this->db->executedSql[2]);
    }

    private function invokeIsValid(KfaDeliveryTimeCache $cache, int $idCache): bool
    {
        $method = new ReflectionMethod(KfaDeliveryTimeCache::class, 'isValid');
        $method->setAccessible(true);

        return (bool) $method->invoke($cache, $idCache);
    }

    private function invokeDeleteStagingCache(int $idCache, string $lockName, int $connectionId): bool
    {
        $method = new ReflectionMethod(KfaDeliveryTimeCache::class, 'deleteStagingCache');
        $method->setAccessible(true);

        return (bool) $method->invoke(null, $idCache, $lockName, $connectionId);
    }

    private function invokeDeleteCache(int $idCache, string $lockName, int $connectionId): bool
    {
        $method = new ReflectionMethod(KfaDeliveryTimeCache::class, 'deleteCache');
        $method->setAccessible(true);

        return (bool) $method->invoke(null, $idCache, $lockName, $connectionId);
    }
}
