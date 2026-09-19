<?php
/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, January 2021
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

class KfaDeliveryTimeAdditionalDeliveryTimes {
    /**
     * 
     * @param KfaDeliveryTime $module
     * @param int $id_lang
     * @return array
     */
    public static function getDefaultLabels(KfaDeliveryTime $module, $id_lang) {
        if ($module->is17) {
            return array(
                'in' => Configuration::get('PS_LABEL_DELIVERY_TIME_AVAILABLE', $id_lang),
                'out' => Configuration::get('PS_LABEL_DELIVERY_TIME_OOSBOA', $id_lang),
            );
        } else {
            return array(
                'in' => Configuration::get($module->conf . 'LABEL_DELIVERY_TIME_AVAILABLE', $id_lang),
                'out' => Configuration::get($module->conf . 'LABEL_DELIVERY_TIME_OOSBOA', $id_lang),
            );
        }
    }
    
    /**
     * 
     * @param bool $is17
     * @param int $id_shop
     * @param int $id_lang
     * @param int $id_product
     * @return array|boolean
     */
    public static function getProduct($is17, $id_shop, $id_lang, $id_product) {
        $prefix = _DB_PREFIX_;
        if ($is17) {
            $product = Db::getInstance()->getRow("
                SELECT p.`additional_delivery_times`, pl.`delivery_in_stock`, pl.`delivery_out_stock`
                FROM `{$prefix}product` p
                LEFT JOIN `{$prefix}product_lang` pl
                    ON pl.`id_shop` = $id_shop AND pl.`id_lang` = $id_lang AND pl.`id_product` = p.`id_product`
                WHERE p.`id_product` = $id_product
            ");
        } else {
            $product = Db::getInstance()->getRow("
                SELECT p.`additional_delivery_times`, pl.`delivery_in_stock`, pl.`delivery_out_stock`
                FROM `{$prefix}kfadeliverytime_product` p
                LEFT JOIN `{$prefix}kfadeliverytime_product_lang` pl
                    ON pl.`id_shop` = $id_shop AND pl.`id_lang` = $id_lang AND pl.`id_product` = p.`id_product`
                WHERE p.`id_product` = $id_product
            ");
        }
        if (is_array($product) && !empty($product['additional_delivery_times'])) {
            return $product;
        }
        
        return false;
    }
    
    /**
     * 
     * @param bool $is17
     * @param int $id_shop
     * @param int $id_lang
     * @param int $id_cart
     * @return array|false
     */
    public static function getCartProducts($is17, $id_shop, $id_lang, $id_cart) {
        $prefix = _DB_PREFIX_;
        if ($is17) {
            $sql = "
                SELECT cp.`id_product`, cp.`id_product_attribute`,
                    p.`additional_delivery_times`,
                    IFNULL(sa.`quantity`, 0) as quantity, sa.`out_of_stock`,
                    pl.`delivery_in_stock`, pl.`delivery_out_stock`
                FROM `{$prefix}cart_product` cp
                INNER JOIN `{$prefix}product` p USING(`id_product`)
                LEFT JOIN `{$prefix}stock_available` sa ON sa.`id_product` = cp.`id_product` AND sa.`id_product_attribute` = cp.`id_product_attribute` AND sa.`id_shop` = $id_shop
                LEFT JOIN `{$prefix}product_lang` pl ON pl.`id_product` = p.`id_product` AND pl.`id_lang` = $id_lang AND pl.`id_shop` = $id_shop
                WHERE cp.`id_cart` = $id_cart AND p.`additional_delivery_times` > 0
            ";
        } else {
            $sql = "
                SELECT cp.`id_product`, cp.`id_product_attribute`,
                    p.`additional_delivery_times`,
                    IFNULL(sa.`quantity`, 0) as quantity, sa.`out_of_stock`,
                    pl.`delivery_in_stock`, pl.`delivery_out_stock`
                FROM `{$prefix}cart_product` cp
                INNER JOIN `{$prefix}kfadeliverytime_product` p USING(`id_product`)
                LEFT JOIN `{$prefix}stock_available` sa ON sa.`id_product` = cp.`id_product` AND sa.`id_product_attribute` = cp.`id_product_attribute` AND sa.`id_shop` = $id_shop
                LEFT JOIN `{$prefix}kfadeliverytime_product_lang` pl ON pl.`id_product` = p.`id_product` AND pl.`id_lang` = $id_lang AND pl.`id_shop` = $id_shop
                WHERE cp.`id_cart` = $id_cart AND p.`additional_delivery_times` > 0
            ";
        }
        $products = Db::getInstance()->executeS($sql);
        return $products ? $products : false;
    }
}
