<?php
/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, October 2020
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

class KfaDeliveryTimeWebserviceModuleFrontController extends ModuleFrontController {
    public function postProcess() {
        $action = Tools::getValue('action');
        $method_name = 'process' . Tools::toCamelCase($action, true);
        $password = Configuration::get($this->module->conf . 'WEBSERVICE_PASSWORD');
        
        if (empty($password)) {
            $this->errors[] = 'رمز وب سرویس تعریف نشده است.';
        } elseif (Tools::getValue('password') != $password) {
            $this->errors[] = 'رمز وب سرویس درست نیست.';
        } elseif (!$action || !in_array($action, array('get_by_id_cart', 'get_by_id_order')) || !method_exists($this, $method_name)) {
            $this->errors[] = 'متد درخواستی تعریف نشده است.';
        }
        
        if (empty($this->errors)) {
            $result = $this->{$method_name}();
        } else {
            $result = array();
        }
        
        if (ob_get_length() > 0) {
            ob_clean();
        }
        
        header('X-Robots-Tag: noindex, nofollow', true);
        header('Content-type: application/json');
        if (!empty($this->errors)) {
            $result['errors'] = $this->errors;
        }
        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        die($json);
    }
    
    protected function processGetByIdCart() {
        $id_cart = (int) Tools::getValue('id_cart');
        if ($id_cart < 1) {
            $this->errors[] = 'شناسه سبد خرید باید بزرگتر از صفر باشد.';
        } elseif (!Cart::existsInDatabase($id_cart, 'cart')) {
            $this->errors[] = sprintf('سبد خرید با شناسه %d وجود ندارد.', $id_cart);
        } elseif (!KfaDeliveryTimeCart::exists($id_cart)) {
            $this->errors[] = 'زمان تحویل برای این سبد خرید انتخاب نشده است.';
        } else {
            $result = KfaDeliveryTimeCart::getByIdCart($id_cart);
        }
        
        if (!isset($result)) {
            $result = array();
        }
        $result['id_cart'] = $id_cart;
        
        return $result;
    }
    
    protected function processGetByIdOrder() {
        $id_order = (int) Tools::getValue('id_order');
        $id_cart = Order::getCartIdStatic($id_order);
        if ($id_order < 1) {
            $this->errors[] = 'شناسه سفارش باید بزرگتر از صفر باشد.';
        } elseif (!self::orderExists($id_order)) {
            $this->errors[] = sprintf('سفارش با شناسه %d وجود ندارد.', $id_order);
        } elseif (!KfaDeliveryTimeCart::exists($id_cart)) {
            $this->errors[] = 'زمان تحویل برای این سفارش انتخاب نشده است.';
        } else {
            $result = KfaDeliveryTimeCart::getByIdCart($id_cart);
        }
        
        if (!isset($result)) {
            $result = array();
        }
        $result['id_cart'] = $id_cart;
        $result['id_order'] = $id_order;
        
        return $result;
    }
    
    private static function orderExists($id_order) {
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_order` = ' . (int) $id_order;
        return (bool) Db::getInstance()->getValue($sql, false);
    }
}
