<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CapacityFallbackBehaviorTest extends TestCase
{
    private KfaDeliveryTimeTestDb $db;
    private KfaDeliveryTime $module;

    protected function setUp(): void
    {
        $this->db = Db::getInstance();
        $this->db->reset();
        KfaDeliveryTimeCapacityHelper::$id_cache_list = [];
        KfaDeliveryTimeCapacityHelper::$use_cache = true;
        $this->module = new KfaDeliveryTime();
    }

    public function testUnavailableCacheFallsBackToLiveOrderCount(): void
    {
        $this->db->getValueResults = [false, 0, false, 4];
        $deliveryTime = [
            'date' => '2026-08-21',
            'date_start' => '2026-08-21 08:00:00',
            'date_end' => '2026-08-21 12:00:00',
        ];

        $count = (new KfaDeliveryTimeCapacityHelper($this->module))->getNbOrders($deliveryTime, 0);

        self::assertSame(4, $count);
        self::assertStringContainsString('FROM `ps_orders` o', $this->db->getValueQueries[3]);
        self::assertStringContainsString('`ps_kfadeliverytime_cart`', $this->db->getValueQueries[3]);
        self::assertStringNotContainsString('cached_orders', $this->db->getValueQueries[3]);
        self::assertStringContainsString('o.`date_add` >=', $this->db->getValueQueries[3]);
    }

    public function testUnavailableCacheFallsBackToLiveTotalPaidRows(): void
    {
        $this->db->getValueResults = [false, 0, false];
        $this->db->executeSResults = [[
            ['total_paid' => '10.25', 'id_currency' => 1],
            ['total_paid' => '5.75', 'id_currency' => 1],
        ]];
        $deliveryTime = [
            'date_start' => '2026-08-21 08:00:00',
            'date_end' => '2026-08-21 12:00:00',
        ];

        $total = (new KfaDeliveryTimeCapacityHelper($this->module))
            ->getDeliveryTimeCapacityByTotalPaid($deliveryTime, 0);

        self::assertSame(16.0, $total);
        self::assertStringContainsString('FROM `ps_orders` o', $this->db->executeSQueries[0]);
        self::assertStringContainsString('`ps_kfadeliverytime_cart`', $this->db->executeSQueries[0]);
        self::assertStringNotContainsString('cached_orders', $this->db->executeSQueries[0]);
        self::assertStringContainsString('o.`date_add` >=', $this->db->executeSQueries[0]);
    }

    public function testUnavailableCacheFallsBackToLiveCapacityCount(): void
    {
        $this->db->getValueResults = [false, 0, false, 3];
        $deliveryTime = [
            'date_start' => '2026-08-21 08:00:00',
            'date_end' => '2026-08-21 12:00:00',
        ];

        $count = (new KfaDeliveryTimeCapacityHelper($this->module))
            ->getDeliveryTimeCapacityByCount($deliveryTime, 0);

        self::assertSame(3, $count);
        self::assertStringContainsString('FROM `ps_orders` o', $this->db->getValueQueries[3]);
        self::assertStringContainsString('INNER JOIN `ps_kfadeliverytime_cart`', $this->db->getValueQueries[3]);
        self::assertStringNotContainsString('cached_orders', $this->db->getValueQueries[3]);
        self::assertStringContainsString('o.`date_add` >=', $this->db->getValueQueries[3]);
    }

    public function testValidCacheRemainsThePreferredCapacitySource(): void
    {
        $this->db->getValueResults = [55, date('Y-m-d') . ' 10:00:00', false];
        $this->db->getRowResults = [['cache_exists' => 1, 'value' => 3]];
        $deliveryTime = [
            'date_start' => '2026-08-21 08:00:00',
            'date_end' => '2026-08-21 12:00:00',
        ];

        $count = (new KfaDeliveryTimeCapacityHelper($this->module))
            ->getDeliveryTimeCapacityByCount($deliveryTime, 0);

        self::assertSame(3, $count);
        self::assertStringContainsString('FROM `ps_kfadeliverytime_cached_orders_list`', $this->db->getRowQueries[0]);
        self::assertStringContainsString('LEFT JOIN `ps_kfadeliverytime_cached_orders`', $this->db->getRowQueries[0]);
        self::assertStringContainsString('cl.`id_cache` = 55', $this->db->getRowQueries[0]);
    }

    public function testRetiredCachedCountFallsBackToLiveQueryWithinSameRequest(): void
    {
        KfaDeliveryTimeCapacityHelper::$id_cache_list = [0 => 55];
        $this->db->getRowResults = [['cache_exists' => 0, 'value' => 0]];
        $this->db->getValueResults = [4];
        $deliveryTime = [
            'date_start' => '2026-08-21 08:00:00',
            'date_end' => '2026-08-21 12:00:00',
        ];

        $count = (new KfaDeliveryTimeCapacityHelper($this->module))
            ->getDeliveryTimeCapacityByCount($deliveryTime, 0);

        self::assertSame(4, $count);
        self::assertStringContainsString('cached_orders_list', $this->db->getRowQueries[0]);
        self::assertStringContainsString('FROM `ps_orders` o', $this->db->getValueQueries[0]);
    }

    public function testExistingEmptyCachedCountDoesNotTriggerLiveFallback(): void
    {
        KfaDeliveryTimeCapacityHelper::$id_cache_list = [0 => 55];
        $this->db->getRowResults = [['cache_exists' => 1, 'value' => 0]];
        $deliveryTime = [
            'date_start' => '2026-08-21 08:00:00',
            'date_end' => '2026-08-21 12:00:00',
        ];

        $count = (new KfaDeliveryTimeCapacityHelper($this->module))
            ->getDeliveryTimeCapacityByCount($deliveryTime, 0);

        self::assertSame(0, $count);
        self::assertSame([], $this->db->getValueQueries);
        self::assertCount(1, $this->db->getRowQueries);
    }

    public function testRetiredCachedTotalFallsBackToLiveRowsWithinSameRequest(): void
    {
        KfaDeliveryTimeCapacityHelper::$id_cache_list = [0 => 55];
        $this->db->executeSResults = [
            [],
            [['total_paid' => '12.50', 'id_currency' => 1]],
        ];
        $deliveryTime = [
            'date_start' => '2026-08-21 08:00:00',
            'date_end' => '2026-08-21 12:00:00',
        ];

        $total = (new KfaDeliveryTimeCapacityHelper($this->module))
            ->getDeliveryTimeCapacityByTotalPaid($deliveryTime, 0);

        self::assertSame(12.5, $total);
        self::assertStringContainsString('cached_orders_list', $this->db->executeSQueries[0]);
        self::assertStringContainsString('FROM `ps_orders` o', $this->db->executeSQueries[1]);
    }

    public function testExistingEmptyCachedTotalDoesNotTriggerLiveFallback(): void
    {
        KfaDeliveryTimeCapacityHelper::$id_cache_list = [0 => 55];
        $this->db->executeSResults = [[
            ['cache_exists' => 55, 'total_paid' => null, 'id_currency' => null],
        ]];
        $deliveryTime = [
            'date_start' => '2026-08-21 08:00:00',
            'date_end' => '2026-08-21 12:00:00',
        ];

        $total = (new KfaDeliveryTimeCapacityHelper($this->module))
            ->getDeliveryTimeCapacityByTotalPaid($deliveryTime, 0);

        self::assertSame(0.0, $total);
        self::assertCount(1, $this->db->executeSQueries);
    }
}
