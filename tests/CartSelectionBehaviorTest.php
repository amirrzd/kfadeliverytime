<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CartSelectionBehaviorTest extends TestCase
{
    private KfaDeliveryTimeTestDb $db;

    protected function setUp(): void
    {
        $this->db = Db::getInstance();
        $this->db->reset();
        KfaDeliveryTimeHistory::$entries = [];
        KfaDeliveryTimeHistory::$result = true;
    }

    public function testDeleteCommitsSelectionAndHistoryMarkerTogether(): void
    {
        $this->db->getValueResults = [0];

        self::assertTrue(KfaDeliveryTimeCart::deleteOption(123));
        self::assertSame([
            'execute:START TRANSACTION',
            'delete:kfadeliverytime_cart',
            'execute:COMMIT',
        ], $this->db->operations);
        self::assertSame([
            ['id_cart' => 123, 'id_employee' => 0, 'value' => ''],
        ], KfaDeliveryTimeHistory::$entries);
    }

    public function testDeleteRollsBackWhenHistoryMarkerFails(): void
    {
        $this->db->getValueResults = [0];
        KfaDeliveryTimeHistory::$result = false;

        self::assertFalse(KfaDeliveryTimeCart::deleteOption(123));
        self::assertSame([
            'execute:START TRANSACTION',
            'delete:kfadeliverytime_cart',
            'execute:ROLLBACK',
        ], $this->db->operations);
    }

    public function testDeleteUsesSavepointInsideCallerTransaction(): void
    {
        $this->db->getValueResults = [1];
        KfaDeliveryTimeHistory::$result = false;

        self::assertFalse(KfaDeliveryTimeCart::deleteOption(123));
        self::assertSame([
            'execute:SAVEPOINT kfadt_delete_option_123',
            'delete:kfadeliverytime_cart',
            'execute:ROLLBACK TO SAVEPOINT kfadt_delete_option_123',
            'execute:RELEASE SAVEPOINT kfadt_delete_option_123',
        ], $this->db->operations);
    }

    public function testDeleteFailsClosedWhenTransactionStateIsUnknown(): void
    {
        $this->db->getValueResults = [false];

        self::assertFalse(KfaDeliveryTimeCart::deleteOption(123));
        self::assertSame([], $this->db->operations);
        self::assertSame([], KfaDeliveryTimeHistory::$entries);
        self::assertFalse($this->db->getValueUseCacheArgs[0]);
    }

    public function testNewSelectionIsInsertedAndRecordedInHistory(): void
    {
        $result = KfaDeliveryTimeCart::selectOption(
            123,
            'delivery-window',
            null,
            '2026-08-21 08:00:00',
            '2026-08-21 12:00:00',
            false
        );

        self::assertTrue($result);
        self::assertCount(1, $this->db->executedSql);
        $sql = $this->db->executedSql[0];
        self::assertStringContainsString('INSERT INTO `ps_kfadeliverytime_cart`', $sql);
        self::assertStringContainsString("(123, 'delivery-window', NULL, '2026-08-21 08:00:00'", $sql);
        self::assertStringContainsString("'2026-08-21 12:00:00'", $sql);
        self::assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $sql);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $sql);
        self::assertSame([], $this->db->getRowQueries);
        self::assertSame([
            ['id_cart' => 123, 'id_employee' => 0, 'value' => 'delivery-window'],
        ], KfaDeliveryTimeHistory::$entries);
    }

    public function testChangedExistingSelectionIsUpdatedAndRecordedInHistory(): void
    {
        $this->db->affectedRows = 2;

        $result = KfaDeliveryTimeCart::selectOption(
            123,
            'changed-window',
            null,
            '2026-08-22 08:00:00',
            '2026-08-22 12:00:00',
            false
        );

        self::assertTrue($result);
        self::assertCount(1, $this->db->executedSql);
        $sql = $this->db->executedSql[0];
        self::assertStringContainsString("(123, 'changed-window', NULL, '2026-08-22 08:00:00'", $sql);
        self::assertStringContainsString("'2026-08-22 12:00:00'", $sql);
        self::assertStringContainsString('BINARY `value` <=> BINARY VALUES(`value`)', $sql);
        self::assertLessThan(
            strpos($sql, '`value` = VALUES(`value`)'),
            strpos($sql, '`date_upd` = IF(')
        );
        self::assertSame([], $this->db->getRowQueries);
        self::assertSame([
            ['id_cart' => 123, 'id_employee' => 0, 'value' => 'changed-window'],
        ], KfaDeliveryTimeHistory::$entries);
    }

    public function testUnchangedExistingSelectionDoesNotWriteOrRecordHistory(): void
    {
        $this->db->affectedRows = 0;

        $result = KfaDeliveryTimeCart::selectOption(
            123,
            'delivery-window',
            null,
            '2026-08-21 08:00:00',
            '2026-08-21 12:00:00',
            false
        );

        self::assertTrue($result);
        self::assertCount(1, $this->db->executedSql);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $this->db->executedSql[0]);
        self::assertSame([], $this->db->getRowQueries);
        self::assertSame([], KfaDeliveryTimeHistory::$entries);
    }

    public function testDateOnlyProcessMatchesStoredMidnightWithoutWriting(): void
    {
        $this->db->affectedRows = 0;

        $result = KfaDeliveryTimeCart::selectOption(
            123,
            'approximate-window',
            '2026-08-20',
            '2026-08-20 00:00:00',
            '2026-08-20 23:59:59',
            true
        );

        self::assertTrue($result);
        self::assertStringContainsString("'2026-08-20 00:00:00'", $this->db->executedSql[0]);
        self::assertSame([], KfaDeliveryTimeHistory::$entries);
    }

    public function testDateOnlyProcessIsStoredAsCanonicalDateTime(): void
    {
        self::assertTrue(KfaDeliveryTimeCart::selectOption(
            123,
            'approximate-window',
            '2026-08-20',
            '2026-08-20 00:00:00',
            '2026-08-20 23:59:59',
            true
        ));

        self::assertStringContainsString("'2026-08-20 00:00:00'", $this->db->executedSql[0]);
    }

    public function testDateTimeCanonicalizationPreservesTimezoneNaiveWallClock(): void
    {
        $previousTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');

        try {
            self::assertTrue(KfaDeliveryTimeCart::selectOption(
                123,
                'approximate-window',
                '2026-03-29 02:30:00',
                '2026-03-29 00:00:00',
                '2026-03-29 23:59:59',
                true
            ));
        } finally {
            date_default_timezone_set($previousTimezone);
        }

        self::assertStringContainsString("'2026-03-29 02:30:00'", $this->db->executedSql[0]);
    }

    /** @dataProvider invalidProcessDateProvider */
    public function testInvalidProcessDateIsStoredAsNull(string $dateProcess): void
    {
        self::assertTrue(KfaDeliveryTimeCart::selectOption(
            123,
            'approximate-window',
            $dateProcess,
            '2026-08-20 00:00:00',
            '2026-08-20 23:59:59',
            true
        ));

        self::assertStringContainsString(
            "(123, 'approximate-window', NULL, '2026-08-20 00:00:00'",
            $this->db->executedSql[0]
        );
    }

    /** @return iterable<string, array{string}> */
    public static function invalidProcessDateProvider(): iterable
    {
        yield 'invalid hour' => ['2026-08-20 24:00:00'];
        yield 'invalid calendar date' => ['2026-02-30'];
    }

    /**
     * @dataProvider changedSelectionProvider
     *
     * @param array{value: string, date_process: ?string, date_start: string, date_end: string, approximate: bool} $incoming
     */
    public function testEachChangedSelectionFieldWritesAndRecordsHistory(array $incoming): void
    {
        $this->db->affectedRows = 2;

        $result = KfaDeliveryTimeCart::selectOption(
            123,
            $incoming['value'],
            $incoming['date_process'],
            $incoming['date_start'],
            $incoming['date_end'],
            $incoming['approximate']
        );

        self::assertTrue($result);
        self::assertCount(1, $this->db->executedSql);
        self::assertSame([], $this->db->getRowQueries);
        self::assertSame([
            ['id_cart' => 123, 'id_employee' => 0, 'value' => $incoming['value']],
        ], KfaDeliveryTimeHistory::$entries);
    }

    /** @return iterable<string, array{array{value: string, date_process: ?string, date_start: string, date_end: string, approximate: bool}}> */
    public static function changedSelectionProvider(): iterable
    {
        $incoming = [
            'value' => 'delivery-window',
            'date_process' => null,
            'date_start' => '2026-08-21 08:00:00',
            'date_end' => '2026-08-21 12:00:00',
            'approximate' => false,
        ];

        foreach (['value', 'date_process', 'date_start', 'date_end', 'approximate'] as $field) {
            $changed = $incoming;
            $changed[$field] = match ($field) {
                'value' => 'changed-window',
                'date_process' => '2026-08-20',
                'date_start' => '2026-08-22 08:00:00',
                'date_end' => '2026-08-22 12:00:00',
                'approximate' => true,
            };

            yield $field => [$changed];
        }
    }

    public function testFailedAtomicWriteDoesNotRecordHistory(): void
    {
        $this->db->executeResults = [false];

        self::assertFalse(KfaDeliveryTimeCart::selectOption(
            123,
            'delivery-window',
            null,
            '2026-08-21 08:00:00',
            '2026-08-21 12:00:00',
            false
        ));

        self::assertSame([], KfaDeliveryTimeHistory::$entries);
        self::assertSame([], $this->db->getRowQueries);
    }

    /** @dataProvider legacyScalarInputProvider */
    public function testLegacyScalarBoundaryInputsAreNormalized(
        string $idCart,
        bool $dateProcess,
        string $approximate,
        int $expectedApproximate
    ): void {
        self::assertTrue(KfaDeliveryTimeCart::selectOption(
            $idCart,
            'delivery-window',
            $dateProcess,
            '2026-08-21 08:00:00',
            '2026-08-21 12:00:00',
            $approximate
        ));

        self::assertStringContainsString(
            "(123, 'delivery-window', NULL, '2026-08-21 08:00:00'",
            $this->db->executedSql[0]
        );
        self::assertStringContainsString(", $expectedApproximate)\n", $this->db->executedSql[0]);
    }

    /** @return iterable<string, array{string, bool, string, int}> */
    public static function legacyScalarInputProvider(): iterable
    {
        yield 'numeric cart id and false-like approximate' => ['123', false, '0', 0];
        yield 'numeric cart id and true-like approximate' => ['123', false, '1', 1];
    }

    /** @dataProvider invalidLegacyCartIdProvider */
    public function testInvalidLegacyCartIdIsRejectedWithoutSql(int|string $idCart): void
    {
        self::assertFalse(KfaDeliveryTimeCart::selectOption(
            $idCart,
            'delivery-window',
            null,
            '2026-08-21 08:00:00',
            '2026-08-21 12:00:00',
            false
        ));

        self::assertSame([], $this->db->executedSql);
        self::assertSame([], KfaDeliveryTimeHistory::$entries);
    }

    /** @return iterable<string, array{int|string}> */
    public static function invalidLegacyCartIdProvider(): iterable
    {
        yield 'non-numeric string' => ['not-a-cart'];
        yield 'zero string' => ['000'];
        yield 'larger than unsigned database id' => ['4294967296'];
        yield 'integer larger than unsigned database id' => [4294967296];
        yield 'zero integer' => [0];
        yield 'negative integer' => [-1];
    }

    public function testStatisticsPlaceholderCartTreatsNullIdAsNoSelection(): void
    {
        $this->db->getValueResults = [false];

        self::assertFalse(KfaDeliveryTimeCart::getOption(null));
        self::assertStringContainsString('id_cart = 0', $this->db->getValueQueries[0]);
        self::assertFalse($this->db->getValueUseCacheArgs[0]);
    }

    public function testAdminListingPreservesNullableStoredValue(): void
    {
        $this->db->getRowResults = [[
            'id_cart' => 123,
            'value' => null,
            'date_process' => null,
            'approximate' => 0,
        ]];
        $approximate = false;

        self::assertNull(KfaDeliveryTimeCart::getOptionForAdminOrdersListing(123, $approximate));
        self::assertFalse($approximate);
    }

    public function testAdminListingTreatsMissingJoinedCartIdAsNoSelection(): void
    {
        $approximate = false;

        self::assertFalse(KfaDeliveryTimeCart::getOptionForAdminOrdersListing(null, $approximate));
        self::assertFalse($approximate);
        self::assertSame([], $this->db->getRowQueries);
    }
}
