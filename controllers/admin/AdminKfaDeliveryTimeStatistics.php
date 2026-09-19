<?php
/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, May 2020
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

class AdminKfaDeliveryTimeStatisticsController extends ModuleAdminController
{
    public function __construct() {
        $this->bootstrap = true;
        parent::__construct();
    }
    
    public function renderList() {
        $_GET['from'] = -1;
        $_GET['to'] = -1;
        $url = $this->context->link->getAdminLink('AdminKfaDeliveryTimeStatistics');
        $this->context->smarty->assign(array(
            'url' => $url,
            'html' => $this->module->ajaxProcessGetStatistics(),
        ));
        $tpl = $this->module->getLocalPath() . 'views/templates/admin/admin_kfadeliverytime_statistics.tpl';
        return $this->context->smarty->fetch($tpl);
    }
    
    public function setMedia($isNewTheme = false) {
        parent::setMedia($isNewTheme);
        $this->context->controller->addCSS($this->module->getPathUri() . 'views/css/admin.css');
        $this->context->controller->addJS($this->module->getPathUri() . 'views/js/admin.js');
    }
    
    public function initPageHeaderToolbar() {
        $back_to_module =
            $this->context->link->getAdminLink('AdminModules') .
            '&configure=' . Tools::safeOutput($this->module->name) .
            '&active_panel=tab-panel-order';
        
        $this->toolbar_btn['back'] = array(
            'href' => $back_to_module,
            'desc' => $this->l('بازگشت به ماژول'),
            'class' => 'process-icon-back',
        );
        
        $this->page_header_toolbar_btn['back_to_module'] = array(
            'href' => $back_to_module,
            'desc' => $this->l('بازگشت به ماژول'),
            'class' => 'process-icon- icon-arrow-circle-up',
        );
        
        parent::initPageHeaderToolbar();
    }
    
    public function ajaxProcessGetStatistics() {
        return $this->module->ajaxProcessGetStatistics();
    }
    
    public function ajaxProcessDisplayOrders() {
        return $this->module->ajaxProcessDisplayOrders();
    }
}
