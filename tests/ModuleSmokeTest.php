<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ModuleSmokeTest extends TestCase
{
    public function testCacheAndCartClassesLoad(): void
    {
        self::assertTrue(class_exists(KfaDeliveryTimeCache::class));
        self::assertTrue(class_exists(KfaDeliveryTimeCart::class));
    }

    public function testFreshInstallDeclaresBothCacheTableContracts(): void
    {
        $schema = file_get_contents(dirname(__DIR__) . '/install.sql');

        self::assertIsString($schema);
        self::assertMatchesRegularExpression(
            '/CREATE TABLE IF NOT EXISTS `PREFIX_kfadeliverytime_cached_orders_list` \(.*?`id_cache`.*?`date_add`.*?PRIMARY KEY \(`id_cache`\).*?\) ENGINE=/s',
            $schema
        );
        self::assertMatchesRegularExpression(
            '/CREATE TABLE IF NOT EXISTS `PREFIX_kfadeliverytime_cached_orders` \(.*?`id_cache`.*?`id_order`.*?`id_cart`.*?`date_start`.*?`date_end`.*?PRIMARY KEY \(`id_cache`,`id_order`\).*?\) ENGINE=/s',
            $schema
        );
        self::assertStringContainsString(
            'KEY `idx_kfadt_cart_date_upd` (`date_upd`,`id_cart`)',
            $schema
        );
        self::assertStringContainsString(
            'KEY `idx_kfadt_cart_date_start` (`date_start`,`id_cart`)',
            $schema
        );
        self::assertStringContainsString(
            'KEY `idx_kfadt_cart_date_process` (`date_process`,`id_cart`)',
            $schema
        );
        self::assertStringContainsString(
            'KEY `idx_kfadt_history_date_add` (`date_add`,`id_cart`)',
            $schema
        );
        self::assertStringContainsString('KEY `idx_id_order` (`id_order`)', $schema);
        self::assertStringContainsString('KEY `idx_id_cart` (`id_cart`)', $schema);
        self::assertStringContainsString('KEY `idx_date_start` (`date_start`)', $schema);
        self::assertStringContainsString('KEY `idx_date_end` (`date_end`)', $schema);
        self::assertStringContainsString(
            'KEY `idx_kfa_cache_dates` (`id_cache`,`date_start`,`date_end`)',
            $schema
        );
    }

    public function testModuleMetadataAndUpgradeWiringUseOneVersion(): void
    {
        $module = file_get_contents(dirname(__DIR__) . '/kfadeliverytime.php');
        $config = file_get_contents(dirname(__DIR__) . '/config.xml');
        $configFa = file_get_contents(dirname(__DIR__) . '/config_fa.xml');
        $changelog = file_get_contents(dirname(__DIR__) . '/kfadeliverytime-changelog.txt');
        $upgrade = file_get_contents(dirname(__DIR__) . '/upgrade/upgrade-1.38.4.php');

        self::assertIsString($module);
        self::assertIsString($config);
        self::assertIsString($configFa);
        self::assertIsString($changelog);
        self::assertIsString($upgrade);
        self::assertStringContainsString('declare(strict_types=1);', $module);
        self::assertStringContainsString("MODULE_VERSION = '1.38.4'", $module);
        self::assertStringContainsString('KfaDeliveryTimeCacheSchema::ensureIndexes()', $module);
        self::assertStringContainsString('upgrade_module_1_38_4', $upgrade);
        self::assertStringContainsString('KfaDeliveryTimeCacheSchema::ensureIndexes()', $upgrade);
        self::assertStringContainsString('<version><![CDATA[1.38.4]]></version>', $config);
        self::assertStringContainsString('<version><![CDATA[1.38.4]]></version>', $configFa);
        self::assertStringStartsWith('1405-06-11 1.38.4', ltrim($changelog, "\xEF\xBB\xBF"));
    }
}
