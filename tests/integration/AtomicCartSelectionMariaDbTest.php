<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AtomicCartSelectionMariaDbTest extends TestCase
{
    private KfaDeliveryTimeMariaDbAdapter $db;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->db = Db::getInstance();
        $this->pdo = $this->db->pdo();
        $this->pdo->exec('DROP TABLE IF EXISTS `test_kfadeliverytime_cart`');
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
        KfaDeliveryTimeHistory::$entries = [];
    }

    public function testInsertPersistsCanonicalSelectionAndReportsOneAffectedRow(): void
    {
        self::assertTrue(KfaDeliveryTimeCart::selectOption(
            123,
            'Delivery Window',
            null,
            '2026-08-21 08:00:00',
            '2026-08-21 12:00:00',
            false
        ));

        self::assertSame(1, $this->db->Affected_Rows());
        self::assertSame([
            'id_cart' => 123,
            'value' => 'Delivery Window',
            'approximate' => 0,
            'date_process' => null,
            'date_start' => '2026-08-21 08:00:00',
            'date_end' => '2026-08-21 12:00:00',
        ], $this->selection(123));
        self::assertCount(1, KfaDeliveryTimeHistory::$entries);
    }

    public function testChangedSelectionUpdatesProjectionButPreservesDateAdd(): void
    {
        $this->seedSelection('Old Window', null, '2026-08-20 08:00:00', '2026-08-20 12:00:00', 0);

        self::assertTrue(KfaDeliveryTimeCart::selectOption(
            123,
            'Changed Window',
            '2026-08-22',
            '2026-08-22 08:00:00',
            '2026-08-22 12:00:00',
            true
        ));

        self::assertSame(2, $this->db->Affected_Rows());
        $row = $this->row(123);
        self::assertSame('2026-08-01 09:00:00', $row['date_add']);
        self::assertNotSame('2026-08-01 09:00:00', $row['date_upd']);
        self::assertSame('Changed Window', $row['value']);
        self::assertSame('2026-08-22 00:00:00', $row['date_process']);
        self::assertSame(1, (int) $row['approximate']);
        self::assertCount(1, KfaDeliveryTimeHistory::$entries);
    }

    public function testUnchangedSelectionPreservesTimestampsAndSkipsHistory(): void
    {
        $this->seedSelection(
            'Delivery Window',
            '2026-08-21 00:00:00',
            '2026-08-21 08:00:00',
            '2026-08-21 12:00:00',
            1
        );

        self::assertTrue(KfaDeliveryTimeCart::selectOption(
            123,
            'Delivery Window',
            '2026-08-21',
            '2026-08-21 08:00:00',
            '2026-08-21 12:00:00',
            true
        ));

        self::assertSame(0, $this->db->Affected_Rows());
        $row = $this->row(123);
        self::assertSame('2026-08-01 09:00:00', $row['date_add']);
        self::assertSame('2026-08-01 09:00:00', $row['date_upd']);
        self::assertSame([], KfaDeliveryTimeHistory::$entries);
    }

    public function testCaseOnlyValueChangeIsNotHiddenByTableCollation(): void
    {
        $this->seedSelection('Delivery Window', null, '2026-08-21 08:00:00', '2026-08-21 12:00:00', 0);

        self::assertTrue(KfaDeliveryTimeCart::selectOption(
            123,
            'delivery window',
            null,
            '2026-08-21 08:00:00',
            '2026-08-21 12:00:00',
            false
        ));

        self::assertSame(2, $this->db->Affected_Rows());
        self::assertSame('delivery window', $this->row(123)['value']);
        self::assertCount(1, KfaDeliveryTimeHistory::$entries);
    }

    public function testEscapedTextRoundTripsAndThenRemainsANoOp(): void
    {
        $value = "O'Reilly \\ Window <b>08:00</b>";

        self::assertTrue(KfaDeliveryTimeCart::selectOption(
            123,
            $value,
            null,
            '2026-08-21 08:00:00',
            '2026-08-21 12:00:00',
            false
        ));
        self::assertSame($value, $this->row(123)['value']);

        self::assertTrue(KfaDeliveryTimeCart::selectOption(
            123,
            $value,
            null,
            '2026-08-21 08:00:00',
            '2026-08-21 12:00:00',
            false
        ));

        self::assertSame(0, $this->db->Affected_Rows());
        self::assertSame($value, $this->row(123)['value']);
        self::assertCount(1, KfaDeliveryTimeHistory::$entries);
    }

    public function testLegacyScalarBoundaryInputsReachTheAtomicWrite(): void
    {
        self::assertTrue(KfaDeliveryTimeCart::selectOption(
            '456',
            'Delivery Window',
            false,
            '2026-08-21 08:00:00',
            '2026-08-21 12:00:00',
            '1'
        ));

        $row = $this->row(456);
        self::assertSame(456, (int) $row['id_cart']);
        self::assertNull($row['date_process']);
        self::assertSame(1, (int) $row['approximate']);
    }

    /** @return array{id_cart: int, value: string, approximate: int, date_process: ?string, date_start: string, date_end: string} */
    private function selection(int $idCart): array
    {
        $row = $this->row($idCart);

        return [
            'id_cart' => (int) $row['id_cart'],
            'value' => (string) $row['value'],
            'approximate' => (int) $row['approximate'],
            'date_process' => $row['date_process'] === null ? null : (string) $row['date_process'],
            'date_start' => (string) $row['date_start'],
            'date_end' => (string) $row['date_end'],
        ];
    }

    /** @return array<string, mixed> */
    private function row(int $idCart): array
    {
        $statement = $this->pdo->query(
            'SELECT * FROM `test_kfadeliverytime_cart` WHERE `id_cart` = ' . $idCart
        );
        self::assertInstanceOf(PDOStatement::class, $statement);
        $row = $statement->fetch();
        self::assertIsArray($row);

        return $row;
    }

    private function seedSelection(
        string $value,
        ?string $dateProcess,
        string $dateStart,
        string $dateEnd,
        int $approximate
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO `test_kfadeliverytime_cart`
                (`id_cart`, `value`, `approximate`, `date_process`, `date_start`, `date_end`, `date_add`, `date_upd`)
             VALUES (123, ?, ?, ?, ?, ?, ?, ?)'
        );
        self::assertTrue($statement->execute([
            $value,
            $approximate,
            $dateProcess,
            $dateStart,
            $dateEnd,
            '2026-08-01 09:00:00',
            '2026-08-01 09:00:00',
        ]));
    }
}
