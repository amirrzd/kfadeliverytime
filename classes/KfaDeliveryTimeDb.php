<?php
/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, March 2020
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

class KfaDeliveryTimeDb {
    private $prefix;
    private $db;
    private $conf;
    
    public function __construct($conf) {
        $this->prefix = _DB_PREFIX_ . 'kfadeliverytime_';
        $this->db = _DB_NAME_;
        $this->conf = $conf;
    }
    
    public function check() {
        $this->checkConfigurations();
        return $this->checkCart()
                && $this->checkDay()
                && $this->checkCarrier()
                && $this->checkHistory()
                && $this->checkRange()
                && $this->checkProductLang();
    }
    
    private function configurationExists($name) {
        $prefix = _DB_PREFIX_;
        return (int) Db::getInstance()->getValue("SELECT `id_configuration` FROM `{$prefix}configuration` WHERE `name` LIKE '$name'");
    }
    
    private function getConfigurationLangValues($id_configuration) {
        $prefix = _DB_PREFIX_;
        $sql = "SELECT `id_lang`, `value` FROM `{$prefix}configuration_lang` WHERE `id_configuration` = $id_configuration";
        $rows = Db::getInstance()->executeS($sql);
        $result = array();
        if ($rows) {
            foreach ($rows as $row) {
                $result[(int) $row['id_lang']] = $row['value'];
            }
        }
        return $result;
    }
    
    private function checkConfigurations() {
        $configs = array(
            'RANGE_COLOR_UNAVAILABLE'       => '#ffa500',
            'RANGE_COLOR_OUT_OF_CAPACITY'   => '#dc143c',
            'RANGE_COLOR_DISABLED'          => '#a9a9a9',
            'RANGE_COLOR_ENABLED'           => '#000000',
            'HISTORY_LIMIT'                 => 3,
        );
        foreach ($configs as $key => $value) {
            if (!($this->configurationExists($this->conf . $key))) {
                Configuration::updateValue($this->conf . $key, $value);
            }
        }
        
        $configs_lang = array(
            'HOOK_ADMIN_ORDERS_LIST_FORMAT'     => '{y}-{m}-{d} {from}-{to}',
            'HOOK_ADMIN_ORDER_DETAIL_FORMAT'    => '{w} {d}-{m}-{y} ساعت {from} تا {to}',
            'HOOK_ORDER_DETAIL_FORMAT'  => ' زمان تحویل سفارش: {w} {y}-{m}-{d} ساعت {from} تا {to}',
            'HOOK_PDF_FORMAT'           => ' زمان تحویل سفارش: {w} {d}-{m}-{y} ساعت {from} تا {to}',
            'HOOK_EMAIL_FORMAT'         => ' زمان تحویل سفارش: {w} {d}-{m}-{y} ساعت {from} تا {to}',
            'UNAVAILABLE_DESCRIPTION'   => 'خارج از دسترس',
            'NEXT_DAYS_TEXT'            => '{w} {d} {mm} {y}',
            'GROUPING_FORMAT_TAB_LINE1' => '{w}',
            'GROUPING_FORMAT_TAB_LINE2' => '{d} {mm}',
            'GROUPING_FORMAT_RANGE'     => 'ساعت {from} تا {to}',
            'SELECTION_EXPIRED'         => 'زمانی را که برای تحویل سفارش خود انتخاب کرده‌اید، منقضی شده است.', // 2020-07-07
            'SELECTION_NOT_AVAILABLE'   => 'زمانی را که برای تحویل سفارش خود انتخاب کرده‌اید، دیگر در دسترس نیست.', // 2020-07-07
            'SELECTION_NOT_DONE'        => 'زمان تحویل سفارش، انتخاب نشده است.', // 2020-07-07
            'TODAY_TEXT'                => 'امروز',
            'REMAINING_DAYS_TEXT'       => '%d روز دیگر', // 2021-10-29
        );
        $languages = Language::getLanguages(false);
        foreach ($configs_lang as $key => $value) {
            $name = $this->conf . $key;
            if (($id_configuration = $this->configurationExists($name))) {
                $current_values = $this->getConfigurationLangValues($id_configuration);
            } else {
                $current_values = array();
            }
            
            $values = array();
            foreach ($languages as $lang) {
                $id_lang = (int) $lang['id_lang'];
                if (array_key_exists($id_lang, $current_values)) {
                    $values[$id_lang] = $current_values[$id_lang];
                } else {
                    $values[$id_lang] = $value;
                }
            }
            Configuration::updateValue($name, $values);
        }
    }
    
    private function columnExists($table, $column) {
        return !empty(Db::getInstance()->executeS("
            SHOW COLUMNS FROM `{$this->prefix}$table`
            LIKE '$column'
        "));
    }
    
    private function addColumns($table, $columns) {
        if (!is_array($columns)) {
            $columns = array($columns);
        }
        
        $result = true;
        foreach ($columns as $column) {
            if ($this->columnExists($table, $column['name'])) {
                continue;
            }
            
            $name = $column['name'];
            $info = $column['info'];
            $result &= Db::getInstance()->execute("ALTER TABLE `{$this->prefix}$table` ADD `$name` $info;");
        }
        return $result;
    }
    
    private function dropColumns($table, $columns) {
        if (!is_array($columns)) {
            $columns = array($columns);
        }
        
        $result = true;
        foreach ($columns as $column) {
            if (!$this->columnExists($table, $column)) {
                continue;
            }
            $result &= Db::getInstance()->execute("ALTER TABLE `{$this->prefix}$table` DROP COLUMN `$column`;");
        }
        return $result;
    }
    
    private function checkCart() {
        $add = array(
            array(
                'name' => 'approximate',
                'info' => 'tinyint(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `value`',
            ),
            array(
                'name' => 'date_process',
                'info' => 'datetime DEFAULT NULL AFTER `approximate`',
            ),
        );
        return $this->addColumns('cart', $add) && Db::getInstance()->execute("ALTER TABLE `{$this->prefix}cart` CHANGE `value` `value` text;");
    }
    
    private function checkDay() {
        $columns = array(
            array(
                'name' => 'id_reference',
                'info' => 'int(10) UNSIGNED NOT NULL',
            ),
            array(
                'name' => 'id_shop',
                'info' => 'int(10) UNSIGNED NOT NULL DEFAULT 1 AFTER `id_reference`',
            ),
            array(
                'name' => 'capacity',
                'info' => 'varchar(16) DEFAULT NULL',
            ),
        );
        $result = $this->addColumns('day', $columns);
        
        $keys = Db::getInstance()->executeS("SHOW KEYS FROM `{$this->prefix}day`");
        if (!is_array($keys) || count($keys) < 3) {
            $result &= Db::getInstance()->execute("ALTER TABLE `{$this->prefix}day` DROP PRIMARY KEY, ADD PRIMARY KEY (`id_reference`,`id_shop`,`name`);");
        }
        
        return $result;
    }
    
    private function checkCarrier() {
        $table = "{$this->prefix}carrier";
        
        $drop = array(
            'fixed_option',
        );
        
        $add = array(
            array(
                'name' => 'program_off',
                'info' => 'tinyint(1) UNSIGNED NOT NULL DEFAULT 0',
            ),
            array(
                'name' => 'program_no_delivery',
                'info' => 'tinyint(1) UNSIGNED NOT NULL DEFAULT 0',
            ),
            array(
                'name' => 'approximate_format_bo',
                'info' => 'text AFTER `approximate_max_day`',
            ),
            array(
                'name' => 'fixed_option_active',
                'info' => 'tinyint(1) UNSIGNED NOT NULL DEFAULT 0',
            ),
            array(
                'name' => 'fixed_option_value',
                'info' => 'varchar(1024) DEFAULT NULL',
            ),
            array(
                'name' => 'fixed_option_date',
                'info' => 'varchar(10) DEFAULT NULL',
            ),
            array(
                'name' => 'same_day_delivery',
                'info' => 'tinyint(1) UNSIGNED NOT NULL DEFAULT 1',
            ),
            // 2021-07-29
            array(
                'name' => 'required',
                'info' => 'tinyint(1) UNSIGNED NOT NULL DEFAULT ' . (Configuration::get('KFADELIVERYTIME_REQUIRED') ? '1' : '0'),
            ),
            // 2021-07-29
            array(
                'name' => 'auto_select',
                'info' => 'tinyint(1) UNSIGNED NOT NULL DEFAULT ' . (Configuration::get('KFADELIVERYTIME_AUTO_SELECT_FIRST_OPTION') ? '1' : '0'),
            ),
            array(
                'name' => 'scroll_active',
                'info' => 'tinyint(1) UNSIGNED NOT NULL DEFAULT 0',
            ),
            array(
                'name' => 'scroll_selector',
                'info' => 'varchar(255) DEFAULT NULL AFTER `scroll_active`',
            ),
            array(
                'name' => 'scroll_offset',
                'info' => 'int(10) NOT NULL DEFAULT 0',
            ),
            array(
                'name' => 'scroll_speed',
                'info' => 'int(10) UNSIGNED NOT NULL DEFAULT 1000',
            ),
            array(
                'name' => 'approximate_capacity',
                'info' => 'varchar(16) DEFAULT NULL',
            ),
            array(
                'name' => 'approximate_data',
                'info' => 'text',
            ),
            array(
                'name' => 'future_days',
                'info' => 'tinyint(1) UNSIGNED NOT NULL DEFAULT ' . (int) Configuration::get('KFADELIVERYTIME_FUTURE_DAYS'),
            ),
            array(
                'name' => 'future_days_when_no_delivery',
                'info' => 'tinyint(1) UNSIGNED NOT NULL DEFAULT 0',
            ),
        );
        
        if (!$this->columnExists('carrier', 'approximate_format_bo')) {
            $update_approximate_format = true;
        }
        
        if ($this->columnExists('carrier', 'approximate_format')) {
            $result = Db::getInstance()->execute("ALTER TABLE `$table` CHANGE `approximate_format` `approximate_format_fo` text;");
        } else {
            $result = true;
            $add[] = array(
                'name' => 'approximate_format_fo',
                'info' => 'text AFTER `approximate_format_bo`',
            );
        }
        
        $approximate_fields = array(
            'approximate_min_day',
            'approximate_max_day',
            'approximate_add_off',
            'approximate_add_no_delivery',
            'approximate_add_sat',
            'approximate_add_sun',
            'approximate_add_mon',
            'approximate_add_tue',
            'approximate_add_wed',
            'approximate_add_thu',
            'approximate_add_fri',
        );
        foreach ($approximate_fields as $field) {
            $add[] = array(
                'name' => $field,
                'info' => 'tinyint(1) UNSIGNED NOT NULL DEFAULT 0',
            );
        }
        
        $keys = Db::getInstance()->executeS("SHOW KEYS FROM `$table`");
        if (is_array($keys) && count($keys) === 1) {
            $add[] = array(
                'name' => 'id_shop',
                'info' => 'int(10) UNSIGNED NOT NULL DEFAULT 1 AFTER `id_reference`',
            );
            $update_primary_key = true;
        }
        
        $result &= $this->addColumns('carrier', $add);
        
        if (!empty($update_approximate_format)) {
            $result &= Db::getInstance()->execute("UPDATE `$table` SET `approximate_format_bo` = `approximate_format_fo`");
        }
        
        if (!empty($update_primary_key)) {
            $result &= Db::getInstance()->execute("ALTER TABLE `$table` DROP PRIMARY KEY, ADD PRIMARY KEY (`id_reference`, `id_shop`);");
        }
        
        return $this->dropColumns('carrier', $drop) && $result;
    }
    
    private function checkHistory() {
        return Db::getInstance()->execute("ALTER TABLE `{$this->prefix}history` CHANGE `value` `value` text;");
    }
    
    private function checkRange() {
        $add = array(
            array(
                'name' => 'id_reference',
                'info' => 'int(10) UNSIGNED NOT NULL',
            ),
            array(
                'name' => 'id_shop',
                'info' => 'int(10) UNSIGNED NOT NULL DEFAULT 1 AFTER `id_reference`',
            ),
            array(
                'name' => 'data',
                'info' => 'text',
            ),
        );
        return $this->addColumns('range', $add);
    }
    
    private function checkProductLang() {
        if (!empty(Db::getInstance()->executeS("SHOW KEYS FROM `{$this->prefix}product_lang`"))) {
            return true;
        }
        
        return Db::getInstance()->execute("ALTER TABLE `{$this->prefix}product_lang` ADD PRIMARY KEY (`id_product`, `id_shop`, `id_lang`);");
    }
}
