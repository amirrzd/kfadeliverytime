<?php
/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, March 2020
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

class KfaDeliveryTimeCarrier {
    const LIST_FIELDS_ALL = 0;
    const LIST_FIELDS_PROGRAM = 1;
    const LIST_FIELDS_APPROXIMATE_ALL = 2;
    const LIST_FIELDS_APPROXIMATE_ADD = 3;
    
    static $cache_carrier_reference = array();
    static $cache_approximate_data = array();
    
    public static function getFields($list_type) {
        switch ($list_type) {
            case KfaDeliveryTimeCarrier::LIST_FIELDS_PROGRAM:
                return array(
                    'program',
                    'program_off',
                    'program_no_delivery',
                );
            case KfaDeliveryTimeCarrier::LIST_FIELDS_APPROXIMATE_ALL:
                return array(
                    'approximate_data',
                    'approximate_capacity',
                    'approximate_min_day',
                    'approximate_max_day',
                    'approximate_format_bo',
                    'approximate_format_fo',
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
            case KfaDeliveryTimeCarrier::LIST_FIELDS_APPROXIMATE_ADD:
                return array(
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
            default:
                return array(
                    'program',
                    'program_off',
                    'program_no_delivery',
                    'same_day_delivery',
                    'required',
                    'auto_select',
                    'scroll_active',
                    'scroll_selector',
                    'scroll_offset',
                    'scroll_speed',
                    'future_days',
                    'future_days_when_no_delivery',
                    'approximate_data',
                    'approximate_capacity',
                    'approximate_min_day',
                    'approximate_max_day',
                    'approximate_format_bo',
                    'approximate_format_fo',
                    'approximate_add_off',
                    'approximate_add_no_delivery',
                    'approximate_add_sat',
                    'approximate_add_sun',
                    'approximate_add_mon',
                    'approximate_add_tue',
                    'approximate_add_wed',
                    'approximate_add_thu',
                    'approximate_add_fri',
                    'fixed_option_active',
                    'fixed_option_value',
                    'fixed_option_date',
                );
        }
    }
    
    /**
     * لیست شناسه هایی که تا کنون برای حامل با این مرجع وجود داشته اند
     * @param int $id_reference
     * @return string
     */
    public static function getListOfIds($id_reference) {
        $prefix = _DB_PREFIX_;
        $sql = "SELECT `id_carrier` FROM `{$prefix}carrier` WHERE `id_reference` = " . (int) $id_reference;
        if (!($rows = Db::getInstance()->executeS($sql))) {
            return '';
        }
        
        $ids = array();
        foreach ($rows as $row) {
            $ids[] = (int) $row['id_carrier'];
        }
        return implode(',', $ids);
    }
    
    public static function getAvailabilityDataFromRawString($string) {
        $data = json_decode($string, true);
        if (!isset($data['availability_active'])) {
            $data['availability_active'] = 0;
        }
        if (!isset($data['availability_h'])) {
            $data['availability_h'] = '';
        }
        if (!isset($data['availability_m'])) {
            $data['availability_m'] = '';
        }
        if (!isset($data['availability_interval'])) {
            $data['availability_interval'] = '';
        }
        if (!isset($data['availability_unit'])) {
            $data['availability_unit'] = '';
        }
        return $data;
    }
    
    private static function getLangData() {
        $prefix = _DB_PREFIX_;
        $id_shop = Context::getContext()->shop->id;
        $sql = "SELECT * FROM `{$prefix}kfadeliverytime_carrier_lang` WHERE `id_shop` = $id_shop";
        $rows = Db::getInstance()->executeS($sql);
        
        $result = array();
        if ($rows) {
            foreach ($rows as $row) {
                $result[$row['id_reference']]['heading'][$row['id_lang']] = $row['heading'];
            }
        }
        return $result;
    }
    
    public static function getCarriers($id_lang, $default_text) {
        $carriers = Carrier::getCarriers($id_lang, false, false, false, null, Carrier::ALL_CARRIERS);
        $default_carrier = array(
            'id_reference' => 0,
            'name' => $default_text,
        );
        array_unshift($carriers, $default_carrier);
        
        $prefix = _DB_PREFIX_;
        $id_shop = Context::getContext()->shop->id;
        $programs = Db::getInstance()->executeS("SELECT * FROM `{$prefix}kfadeliverytime_carrier` WHERE `id_shop` = $id_shop");
        $fields_list = KfaDeliveryTimeCarrier::getFields(KfaDeliveryTimeCarrier::LIST_FIELDS_ALL);
        $lang_data = self::getLangData();
        foreach ($carriers as &$carrier) {
            if ($programs) {
                foreach ($programs as $program) {
                    if ($program['id_reference'] == $carrier['id_reference']) {
                        foreach ($fields_list as $field) {
                            $carrier[$field] = $program[$field];
                        }
                        break;
                    }
                }
            }
            
            foreach ($fields_list as $field) {
                if (!isset($carrier[$field])) {
                    switch ($field) {
                        case 'approximate_format_bo':
                        case 'approximate_format_fo':
                            $carrier[$field] = 'زمان تقریبی تحویل از {from_d} {from_mm} تا {to_d} {to_mm}';
                            break;
                        case 'approximate_data':
                        case 'approximate_capacity':
                        case 'fixed_option_value':
                        case 'scroll_selector':
                            $carrier[$field] = '';
                            break;
                        case 'fixed_option_date':
                            $carrier[$field] = KfaDeliveryTime::DEFAULT_DATE;
                            break;
                        case 'future_days':
                            $carrier[$field] = 3;
                            break;
                        default:
                            $carrier[$field] = 0;
                            break;
                    }
                }
            }
            
            $carrier['approximate_data'] = KfaDeliveryTimeCarrier::getAvailabilityDataFromRawString($carrier['approximate_data']);
            $carrier['heading'] = isset($lang_data[$carrier['id_reference']]['heading']) ? $lang_data[$carrier['id_reference']]['heading'] : array();
        }
        if (isset($carrier)) {
            unset($carrier);
        }
        
        return $carriers;
    }
    
    public static function update($id_reference, $field, $value) {
        if (!is_array($field)) {
            $field = array($field);
        }
        if (!is_array($value)) {
            $value = array($value);
        }
        
        $id_shop = Context::getContext()->shop->id;
        $result = true;
        for ($i = 0; $i < count($field); $i++) {
            $data = array(
                'id_reference' => $id_reference,
                'id_shop' => $id_shop,
                $field[$i] => pSQL($value[$i], true),
            );
            $result &= Db::getInstance()->insert('kfadeliverytime_carrier', $data, false, false, Db::ON_DUPLICATE_KEY);
        }
        return $result;
    }
    
    public static function updateLang($id_reference, $id_shop, $id_lang, $field, $value) {
        if (!is_array($field)) {
            $field = array($field);
        }
        if (!is_array($value)) {
            $value = array($value);
        }
        
        $result = true;
        for ($i = 0; $i < count($field); $i++) {
            $data = array(
                'id_reference' => $id_reference,
                'id_shop' => $id_shop,
                'id_lang' => $id_lang,
                $field[$i] => pSQL($value[$i], true),
            );
            $result &= Db::getInstance()->insert('kfadeliverytime_carrier_lang', $data, false, false, Db::ON_DUPLICATE_KEY);
        }
        return $result;
    }
    
    public static function getCarrierReference($id_carrier) {
        if (!is_numeric($id_carrier)) {
            return 0;
        }
        
        if (!isset(self::$cache_carrier_reference[$id_carrier])) {
            $prefix = _DB_PREFIX_;
            $sql = "SELECT `id_reference` FROM `{$prefix}carrier` WHERE `id_carrier` = $id_carrier";
            self::$cache_carrier_reference[$id_carrier] = (int) Db::getInstance()->getValue($sql);
        }
        
        return self::$cache_carrier_reference[$id_carrier];
    }
    
    private static function createSelect($id_reference, $fields, $id_shop = false) {
        $table = _DB_PREFIX_ . 'kfadeliverytime_carrier';
        if (!$id_shop) {
            $id_shop = Context::getContext()->shop->id;
        }
        $where = "WHERE `id_reference` = $id_reference AND `id_shop` = $id_shop";
        return "SELECT $fields FROM `$table` $where";
    }
    
    public static function getCarrierPrograms($id_carrier, $id_shop) {
        $id_reference = KfaDeliveryTimeCarrier::getCarrierReference($id_carrier);
        $fields_list = KfaDeliveryTimeCarrier::getFields(KfaDeliveryTimeCarrier::LIST_FIELDS_PROGRAM);
        $fields = '`' . implode('`, `', $fields_list) . '`';
        $sql = self::createSelect($id_reference, $fields, $id_shop);
        $result = Db::getInstance()->getRow($sql);
        if (!$result) {
            $result = array();
            foreach ($fields_list as $field) {
                $result[$field] = 0;
            }
        }
        return $result;
    }
    
    public static function getCarrierWeekdayProgram($id_carrier, $id_shop, $weekday) {
        $id_reference = KfaDeliveryTimeCarrier::getCarrierReference($id_carrier);
        return self::getCarrierWeekdayProgramByReference($id_reference, $id_shop, $weekday);
    }
    
    private static function getCachedCarrierWeekdayProgramByReference($id_reference, $id_shop, $weekday) {
        static $result = null;
        if (is_null($result)) {
            $prefix = _DB_PREFIX_;
            $sql = "
                SELECT `id_reference`, `id_shop`, `name`, `program`
                FROM `{$prefix}kfadeliverytime_day`
            ";
            if (($rows = Db::getInstance()->executeS($sql))) {
                foreach ($rows as $row) {
                    $result[(int) $row['id_shop']][(int) $row['id_reference']][$row['name']] = (int) $row['program'];
                }
            }
        }
        if (isset($result[$id_shop][$id_reference][$weekday])) {
            return $result[$id_shop][$id_reference][$weekday];
        }
        
        return 0;
    }
    
    public static function getCarrierWeekdayProgramByReference($id_reference, $id_shop, $weekday) {
        if (true) {
            // New way - fast
            $program = self::getCachedCarrierWeekdayProgramByReference($id_reference, $id_shop, $weekday);
        } else {
            // Old way - slow
            $prefix = _DB_PREFIX_;
            $sql = "SELECT `program` FROM `{$prefix}kfadeliverytime_day` WHERE `id_reference` = $id_reference AND `id_shop` = $id_shop AND `name` = '$weekday'";
            $program = (int) Db::getInstance()->getValue($sql);
        }
        return $program;
    }
    
    private static function getCachedCarrierProperty($id_reference, $property, $id_shop = false) {
        static $result = null;
        if (is_null($result)) {
            $prefix = _DB_PREFIX_;
            if (!$id_shop) {
                $id_shop = Context::getContext()->shop->id;
            }
            $sql = "
                SELECT *
                FROM `{$prefix}kfadeliverytime_carrier`
                WHERE `id_shop` = $id_shop
            ";
            if (($rows = Db::getInstance()->executeS($sql))) {
                foreach ($rows as $row) {
                    $result[(int) $row['id_reference']] = $row;
                }
            }
        }
        
        if (!array_key_exists($id_reference, $result)) {
            return null;
        }
        
        $carrier = $result[$id_reference];
        if (!array_key_exists($property, $carrier)) {
            throw new Exception("Carrier with reference $id_reference has not a property named '$property'.");
        }
        
        return $carrier[$property];
    }
    
    /**
     * Used in appsys3
     * @param int $id_carrier
     * @param int|false $id_shop
     * @return int
     */
    public static function getCarrierProgram($id_carrier, $id_shop = false) {
        $id_reference = KfaDeliveryTimeCarrier::getCarrierReference($id_carrier);
        return self::getCarrierProgramByReference($id_reference, $id_shop);
    }
    
    /**
     * @param string $id_reference
     * @param int|false $id_shop
     * @return int
     */
    public static function getCarrierProgramByReference($id_reference, $id_shop = false) {
        $sql = self::createSelect($id_reference, '`program`', $id_shop);
        return (int) Db::getInstance()->getValue($sql);
    }
    
    public static function getCarrierAutoSelect($id_reference) {
        $property = 'auto_select';
        if (true) {
            $value = self::getCachedCarrierProperty($id_reference, $property);
        } else {
            $sql = self::createSelect($id_reference, "`$property`");
            $value = (int) Db::getInstance()->getValue($sql);
        }
        return $value;
    }
    
    public static function getCarrierRequired($id_reference) {
        $property = 'required';
        if (true) {
            $value = self::getCachedCarrierProperty($id_reference, $property);
        } else {
            $sql = self::createSelect($id_reference, "`$property`");
            $value = (int) Db::getInstance()->getValue($sql);
        }
        return $value;
    }
    
    public static function getCarrierSameDayDelivery($id_reference) {
        $property = 'same_day_delivery';
        if (true) {
            $value = self::getCachedCarrierProperty($id_reference, $property);
        } else {
            $sql = self::createSelect($id_reference, "`$property`");
            $value = (int) Db::getInstance()->getValue($sql);
        }
        return $value;
    }
    
    public static function getCarrierFutureDays($id_reference) {
        $property = 'future_days';
        if (true) {
            $value = self::getCachedCarrierProperty($id_reference, $property);
        } else {
            $sql = self::createSelect($id_reference, "`$property`");
            $value = (int) Db::getInstance()->getValue($sql);
        }
        return $value;
    }
    
    public static function getCarrierFutureDaysWhenNoDelivery($id_reference) {
        $property = 'future_days_when_no_delivery';
        if (true) {
            $value = self::getCachedCarrierProperty($id_reference, $property);
        } else {
            $sql = self::createSelect($id_reference, "`$property`");
            $value = (int) Db::getInstance()->getValue($sql);
        }
        return $value;
    }
    
    public static function getCarrierFixedOption($id_reference) {
        $sql = self::createSelect($id_reference, '`fixed_option_active`, `fixed_option_value`, `fixed_option_date`');
        if (($row = Db::getInstance()->getRow($sql))) {
            return array(
                'active' => $row['fixed_option_active'],
                'value' => $row['fixed_option_value'],
                'date' => preg_match('~^\d{4}-\d{1,2}-\d{1,2}$~', $row['fixed_option_date']) ? $row['fixed_option_date'] : KfaDeliveryTime::DEFAULT_DATE,
            );
        }
        
        return array(
            'active' => 0,
            'value' => '',
            'date' => KfaDeliveryTime::DEFAULT_DATE,
        );
    }
    
    public static function getCarrierApproximateData($id_reference) {
        if (!isset(self::$cache_approximate_data[$id_reference])) {
            $fields_list = KfaDeliveryTimeCarrier::getFields(KfaDeliveryTimeCarrier::LIST_FIELDS_APPROXIMATE_ALL);
            $fields = '`' . implode('`, `', $fields_list) . '`';
            $sql = self::createSelect($id_reference, $fields);
            if (!($row = Db::getInstance()->getRow($sql))) {
                $row = array();
                foreach ($fields_list as $field) {
                    $row[$field] = 0;
                }
            }
            self::$cache_approximate_data[$id_reference] = $row;
        }
        return self::$cache_approximate_data[$id_reference];
    }
    
    public static function getCarrierScrollData($id_carrier) {
        $id_reference = KfaDeliveryTimeCarrier::getCarrierReference($id_carrier);
        $sql = self::createSelect($id_reference, '`scroll_active`, `scroll_selector`, `scroll_offset`, `scroll_speed`');
        if (($row = Db::getInstance()->getRow($sql))) {
            return array(
                'active' => $row['scroll_active'],
                'selector' => $row['scroll_selector'],
                'offset' => $row['scroll_offset'],
                'speed' => $row['scroll_speed'],
            );
        }
        
        return array(
            'active' => 0,
            'selector' => '',
            'offset' => 0,
            'speed' => 0,
        );
    }
    
    public static function getCarrierHeading($id_carrier, $id_shop, $id_lang) {
        $id_reference = KfaDeliveryTimeCarrier::getCarrierReference($id_carrier);
        $prefix = _DB_PREFIX_;
        $sql = "
            SELECT `heading`
            FROM `{$prefix}kfadeliverytime_carrier_lang`
            WHERE `id_reference` = $id_reference AND `id_shop` = $id_shop AND `id_lang` = $id_lang
        ";
        $value = Db::getInstance()->getValue($sql);
        return $value ? $value : '';
    }
    
    public static function getIdCarrierByIdOrder($id_order) {
        $sql = new DbQuery();
        $sql->select('id_carrier')->from('orders')->where('id_order = ' . (int) $id_order);
        return Db::getInstance()->getValue($sql);
    }
}
