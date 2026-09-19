<?php
/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, February 2020
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

class KfaDeliveryTimeDefaultModuleFrontController extends ModuleFrontController {
    public function postProcess() {
        if (empty($this->context->cart) || !Validate::isLoadedObject($this->context->cart)) {
            return;
        }
        
        KfaDeliveryTime::logCalculations(__CLASS__ . ' > ' . __FUNCTION__ . " > data: " . var_export(Tools::getAllValues(), true));
        
        switch (Tools::getValue('action')) {
            case 'set':
                $result = $this->processSet();
                break;
            case 'get':
                $result = $this->processGet();
                break;
            case 'reload':
                $result = $this->processReload();
                break;
            default:
                $result = null;
                break;
        }
        $json = json_encode($result, JSON_UNESCAPED_UNICODE);
        die($json);
    }
    
    private function processGet() {
        $delivery_times = $this->getModule()->getDeliveryTimes();
        $errors = $this->getModule()->getSelectedOptionErrors($delivery_times);
        
        if (!empty($errors)) {
            return array(
                'errors' => $errors,
                'success' => false,
            );
        }
        
        $id_carrier = $this->getModule()->detectIdCarrier($this->context->cart);
        $program = KfaDeliveryTimeCarrier::getCarrierProgram($id_carrier, $this->context->cart->id_shop);
        if ($program == KfaDeliveryTime::CARRIER_PROGRAM_UNAVAILABLE) {
            KfaDeliveryTimeCart::deleteOption($this->context->cart->id);
        } elseif ($program == KfaDeliveryTime::CARRIER_PROGRAM_APPROXIMATE) {
            if ($delivery_times) {
                KfaDeliveryTimeCart::selectOption($this->context->cart->id
                        , $delivery_times['value']
                        , $delivery_times['process']
                        , $delivery_times['from']
                        , $delivery_times['to']
                        , true);
            } else {
                KfaDeliveryTimeCart::deleteOption($this->context->cart->id);
            }
        }
        return array(
            'errors' => null,
            'success' => true,
        );
    }
    
    /**
     * 
     * @return string
     */
    private function processSet() {
        $insufficient_data = !Tools::getValue('ajax')
                || !is_string($value = Tools::getValue('value'))
                || !is_string($date_start = Tools::getValue('date_start'))
                || !is_string($date_end = Tools::getValue('date_end'));
        if ($insufficient_data) {
            return array('error' => $this->module->l('اطلاعات ارسالی کافی نیستند.', 'default'));
        }
        
        if (!Validate::isDate($date_start) || !Validate::isDate($date_end)) {
            return array('error' => $this->module->l('فرمت زمان تحویل معتبر نیست.', 'default'));
        }

        if (KfaDeliveryTimeCart::selectOption($this->context->cart->id
                , $value
                , null
                , $date_start
                , $date_end
                , false)) {
            
            $id_carrier = $this->getModule()->detectIdCarrier($this->context->cart);
            return array(
                'message' => $this->module->l('انتخاب شما ثبت شد.', 'default'),
                'scroll' => KfaDeliveryTimeCarrier::getCarrierScrollData($id_carrier),
            );
        } else {
            $error = sprintf($this->module->l('خطای پایگاه داده: %s', 'default'), Db::getInstance()->getMsgError());
            return array('error' => $error);
        }
    }
    
    private function processReload() {
        $html = $this->getModule()->displayHookContent();
        die($html);
    }
    
    /**
     * 
     * @return KfaDeliveryTime
     */
    private function getModule() {
        return $this->module;
    }
}
