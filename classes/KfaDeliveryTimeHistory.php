<?php
/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, May 2020
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

class KfaDeliveryTimeHistory extends ObjectModel
{
    public $id_cart;
    public $id_employee;
    public $value;
    public $date_add;
    
    public static $definition = array (
        'table' => 'kfadeliverytime_history',
        'primary' => 'id_history',
        'fields' => array(
            'id_cart' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
            ),
            'id_employee' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
            ),
            'value' => array(
                'type' => self::TYPE_STRING,
                'size' => 128,
            ),
            'date_add' => array(
                'type' => self::TYPE_DATE,
            ),
        ),
    );
    
    public static function addToHistory($id_cart, $id_employee, $value) {
        if ($id_employee || !self::cartExists($id_cart)) {
            $data = array(
                'id_cart' => (int) $id_cart,
                'id_employee' => (int) $id_employee,
                'value' => pSQL($value, true),
                'date_add' => date('Y-m-d H:i:s'),
            );
            return Db::getInstance()->insert(self::$definition['table'], $data);
        } else {
            $data = array(
                'id_employee' => 0,
                'value' => pSQL($value, true),
                'date_add' => date('Y-m-d H:i:s'),
            );
            return Db::getInstance()->update(self::$definition['table'], $data, '`id_cart` = ' . (int) $id_cart);
        }
    }
    
    private static function cartExists($id_cart) {
        $table = _DB_PREFIX_ . self::$definition['table'];
        return Db::getInstance()->getValue("SELECT COUNT(*) FROM `$table` WHERE `id_cart` = " . $id_cart);
    }
    
    public static function get($id_cart, $limit) {
        if ($limit > 0) {
            $limit = "LIMIT 0, $limit";
        } else {
            $limit = '';
        }
        
        $prefix = _DB_PREFIX_;
        $sql = "
            SELECT CONCAT(e.`firstname`, ' ', e.`lastname`) AS employee, h.`value`, h.`date_add`
            FROM `{$prefix}kfadeliverytime_history` h
            LEFT JOIN `{$prefix}employee` e USING(`id_employee`)
            WHERE `id_cart` = $id_cart
            ORDER BY `date_add` DESC
            $limit
        ";
        $rows = Db::getInstance()->executeS($sql);
        if (!$rows) {
            return array();
        }
        
        foreach ($rows as $index => $row) {
            $rows[$index]['date_add_persian'] = KfaPersianDate::gToS($row['date_add']);
        }
        return $rows;
    }
}
