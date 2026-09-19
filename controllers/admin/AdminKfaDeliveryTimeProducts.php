<?php
/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, January 2021
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

class AdminKfaDeliveryTimeProductsController extends ModuleAdminController
{
    private $additional_delivery_times;
    
    public function __construct() {
        $this->bootstrap = true;
        $this->table = 'product';
        $this->className = 'Product';
        $this->lang = true;
        $this->explicitSelect = true;
        $this->bulk_actions = array();
        if (!Tools::getValue('id_product')) {
            $this->multishop_context_group = false;
        }
        
        parent::__construct();
        
        $this->additional_delivery_times = array(
            0 => $this->l('ندارد'),
            1 => $this->l('پیش‌فرض'),
            2 => $this->l('اختصاصی'),
        );
        
        $this->list_no_link = true;
        $this->imageType = 'jpg';
        $this->_defaultOrderBy = 'id_product';
        
        if (Tools::getValue('reset_filter_category')) {
            $this->setCookieCategory(0);
        }
        
        if (Shop::isFeatureActive() && $this->getCookieCategory()) {
            $category = new Category($this->getCookieCategory());
            if (!$category->inShop()) {
                $this->setCookieCategory(0);
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminKfaDeliveryTimeProducts'));
            }
        }
        
        if (($id_current_category = (int) Tools::getValue('productFilter_cl!name'))) {
            $category = new Category($id_current_category);
            $_POST['productFilter_cl!name'] = $category->name[$this->context->language->id];
        } else {
            if (($id_current_category = (int) Tools::getValue('id_category'))) {
                $this->setCookieCategory($id_current_category);
            } else {
                $id_current_category = $this->getCookieCategory();
            }
            $category = new Category($id_current_category);
        }
        
        $prefix = _DB_PREFIX_;
        
        $stock_available_join = StockAvailable::addSqlShopRestriction(null, null, 'sa');
        $id_shop = Shop::isFeatureActive() && Shop::getContext() == Shop::CONTEXT_SHOP ? (int) $this->context->shop->id : 'a.`id_shop_default`';
        $this->_join .= "
                LEFT JOIN `{$prefix}manufacturer` manufacturer USING (`id_manufacturer`)
                LEFT JOIN `{$prefix}stock_available` sa ON (sa.`id_product` = a.`id_product` AND sa.`id_product_attribute` = 0 $stock_available_join)
                JOIN `{$prefix}product_shop` ps ON (a.`id_product` = ps.`id_product` AND ps.`id_shop` = $id_shop)
				LEFT JOIN `{$prefix}category_lang` cl ON (cl.`id_category` = ps.`id_category_default` AND cl.`id_lang` = b.`id_lang` AND cl.`id_shop` = $id_shop)
				LEFT JOIN `{$prefix}image_shop` image_shop ON (image_shop.`id_product` = a.`id_product` AND image_shop.`cover` = 1 AND image_shop.`id_shop` = $id_shop)
				LEFT JOIN `{$prefix}image` i ON (i.`id_image` = image_shop.`id_image`)
        ";
        
        $this->_select = '';
        
        if ($this->module->is17) {
            $this->_select .= "a.`additional_delivery_times`, IFNULL(b.`delivery_in_stock`, '') AS delivery_in_stock, IFNULL(b.`delivery_out_stock`, '') AS delivery_out_stock";
        } else {
            $this->_join .= "
				LEFT JOIN `{$prefix}kfadeliverytime_product` kp ON (kp.`id_product` = a.`id_product`)
				LEFT JOIN `{$prefix}kfadeliverytime_product_lang` kpl ON (kpl.`id_product` = a.`id_product` AND kpl.`id_shop` = $id_shop AND kpl.`id_lang` = b.`id_lang`)
            ";
            $this->_select .= "IFNULL(kp.`additional_delivery_times`, 0)AS additional_delivery_times, IFNULL(kpl.`delivery_in_stock`, '') AS delivery_in_stock, IFNULL(kpl.`delivery_out_stock`, '') AS delivery_out_stock";
        }
        
        $join_category = false;
        if (Validate::isLoadedObject($category) && empty($this->_filter)) {
            $join_category = true;
            $this->_join .= ' INNER JOIN `'._DB_PREFIX_.'category_product` cp ON (cp.`id_product` = a.`id_product` AND cp.`id_category` = '.(int)$category->id.') ';
            $this->_select .= ' , cp.`position`';
        }
        
        $this->_select .= "
            , manufacturer.`name` AS `manufacturer_name`
            , image_shop.`id_image` AS `id_image`
            , cl.`name` AS `name_category`
            , sa.`quantity` AS `sav_quantity`
            , ps.`active` AS is_active
            , IF(sa.`quantity` <= 0, 1, 0) AS `badge_danger`
            , CONCAT($id_shop, '_', b.`id_lang`, '_', a.`id_product`) AS id
        ";
        
        $this->_use_found_rows = false;
        $this->_group = '';

        $this->fields_list = array();
        $this->fields_list['id_product'] = array(
            'title' => $this->l('ش.'),
            'align' => 'center',
            'class' => 'fixed-width-xs',
            'type' => 'int'
        );
        $this->fields_list['image'] = array(
            'title' => $this->l('تصویر'),
            'align' => 'center',
            'image' => 'p',
            'orderby' => false,
            'filter' => false,
            'search' => false
        );
        $this->fields_list['name'] = array(
            'title' => $this->l('نام'),
            'filter_key' => 'b!name'
        );
        $this->fields_list['reference'] = array(
            'title' => $this->l('مرجع'),
            'align' => 'left',
        );
        $this->fields_list['name_category'] = array(
            'title' => $this->l('شاخه پیش‌فرض'),
            'filter_key' => 'cl!name',
        );
        $this->fields_list['manufacturer_name'] = array(
            'title' => $this->l('برند'),
            'filter_key' => 'manufacturer!name',
        );
        
        if (Configuration::get('PS_STOCK_MANAGEMENT')) {
            $this->fields_list['sav_quantity'] = array(
                'title' => $this->l('تعداد'),
                'type' => 'int',
                'align' => 'text-right',
                'filter_key' => 'sa!quantity',
                'orderby' => true,
                'badge_danger' => true,
            );
        }
        
        $this->fields_list['is_active'] = array(
            'title' => $this->l('فعال'),
            'align' => 'text-center',
            'type' => 'bool',
            'havingFilter' => true,
            'class' => 'fixed-width-sm',
            'callback' => 'printStatus',
        );
        
        $this->fields_list['additional_delivery_times'] = array(
            'title' => $this->l('زمان تحویل'),
            'align' => 'text-center',
            'type' => 'select',
            'list' => $this->additional_delivery_times,
            'havingFilter' => true,
            'filter_key' => ($this->module->is17 ? 'a!' : '') . 'additional_delivery_times',
            'class' => 'fixed-width-sm',
            'callback' => 'printAdditionalDeliveryTimes',
        );
        
        $this->fields_list['delivery_in_stock'] = array(
            'title' => $this->l('زمان تحویل موجود'),
            'align' => 'text-center',
            'type' => 'editable',
            'editable' => true,
            'havingFilter' => true,
            'class' => 'fixed-width-sm',
        );
        
        $this->fields_list['delivery_out_stock'] = array(
            'title' => $this->l('زمان تحویل پیش‌خرید'),
            'align' => 'text-center',
            'type' => 'editable',
            'editable' => true,
            'havingFilter' => true,
            'class' => 'fixed-width-sm',
        );
        
        if ($join_category && (int) $id_current_category) {
            $this->fields_list['position'] = array(
                'title' => $this->l('موقعیت'),
                'filter_key' => 'cp!position',
                'align' => 'center',
                'position' => 'position'
            );
        }
    }
    
    public function postProcess() {
        parent::postProcess();
        if (Tools::isSubmit('submitMassEdit')) {
            return $this->processMassEdit();
        }
    }
    
    private function processMassEdit() {
        $this->getList($this->context->language->id, null, null, 0, false);
        $has_list = is_array($this->_list) && !empty($this->_list);
        
        $additional_delivery_times_active = Tools::getValue('additional_delivery_times_active');
        $delivery_in_stock_active = Tools::getValue('delivery_in_stock_active');
        $delivery_out_stock_active = Tools::getValue('delivery_out_stock_active');
        $has_active = $additional_delivery_times_active || $delivery_in_stock_active || $delivery_out_stock_active;
        
        if (!$has_list || !$has_active) {
            return Tools::redirectAdmin($this->context->link->getAdminLink('AdminKfaDeliveryTimeProducts'));
        }
        
        $updated = 0;
        $failed = 0;
        
        $is17 = $this->module->is17;
        $table = $is17 ? 'product' : 'kfadeliverytime_product';
        $table_lang = $is17 ? 'product_lang' : 'kfadeliverytime_product_lang';
        $languages = $this->getLanguages();
        
        $additional_delivery_times = (int) Tools::getValue('additional_delivery_times');
        $delivery_in_stock = array();
        $delivery_out_stock = array();
        foreach ($languages as $lang) {
            $id_lang = $lang['id_lang'];
            $delivery_in_stock[$id_lang] = Tools::getValue("delivery_in_stock_$id_lang");
            $delivery_out_stock[$id_lang] = Tools::getValue("delivery_out_stock_$id_lang");
        }
        
        foreach ($this->_list as $row) {
            $id_product = (int) $row['id_product'];
            $success = true;
            
            if ($additional_delivery_times_active) {
                $data = array(
                    'id_product' => $id_product,
                    'additional_delivery_times' => $additional_delivery_times,
                );
                $success = Db::getInstance()->insert($table, $data, false, false, Db::ON_DUPLICATE_KEY);
            }
            
            if ($delivery_in_stock_active || $delivery_out_stock_active) {
                foreach ($languages as $lang) {
                    $id_lang = $lang['id_lang'];
                    $data_lang = array(
                        'id_shop' => $this->context->shop->id,
                        'id_lang' => $id_lang,
                        'id_product' => $id_product,
                    );
                    if ($delivery_in_stock_active) {
                        $data_lang['delivery_in_stock'] = pSQL($delivery_in_stock[$id_lang]);
                    }
                    if ($delivery_out_stock_active) {
                        $data_lang['delivery_out_stock'] = pSQL($delivery_out_stock[$id_lang]);
                    }
                    $success &= Db::getInstance()->insert($table_lang, $data_lang, false, false, Db::ON_DUPLICATE_KEY);
                }
            }
            
            if ($success) {
                $updated++;
            } else {
                $failed++;
            }
        }
        
        $time = date('H:i:s');
        return Tools::redirectAdmin($this->context->link->getAdminLink('AdminKfaDeliveryTimeProducts') . "&mass_report=$time&updated=$updated&failed=$failed");
    }
    
    public function setMedia($isNewTheme = false) {
        parent::setMedia($isNewTheme);
        $this->context->controller->addJS($this->module->getPathUri() . 'views/js/admin-products.js');
    }
    
    public function getList($id_lang, $order_by = null, $order_way = null, $start = 0, $limit = null, $id_lang_shop = false) {
        if (Shop::isFeatureActive()) {
            $id_lang_shop = true;
        }
        return parent::getList($id_lang, $order_by, $order_way, $start, $limit, $id_lang_shop);
    }
    
    private function setCookieCategory($id_category) {
        $this->context->cookie->admin_kfadeliverytime_products_id_category = (int) $id_category;
    }
    
    private function getCookieCategory() {
        if (!empty($this->context->cookie->admin_kfadeliverytime_products_id_category)) {
            return (int) $this->context->cookie->admin_kfadeliverytime_products_id_category;
        }
        
        return 0;
    }
    
    private function renderMassEdit() {
        $query = array();
        foreach ($this->additional_delivery_times as $key => $value) {
            $query[] = array(
                'id' => $key,
                'name' => $value,
            );
        }
        
        $fields_forms = array();
        $fields_forms[] = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('تنظیم گروهی زمان تحویل محصولات'),
                    'icon' => '',
                ),
                'input' => array(
                    array(
                        'type' => 'select',
                        'label' => $this->l('زمان تحویل'),
                        'name' => 'additional_delivery_times',
                        'options' => array(
                            'id' => 'id',
                            'name' => 'name',
                            'query' => $query,
                        ),
                    ),
                    KfaDeliveryTimeUIKit::createSwitch($this->l('ویرایش شود'), 'additional_delivery_times_active'),
                    KfaDeliveryTimeUIKit::createSeparator(),
                    array(
                        'type' => 'text',
                        'label' => $this->l('زمان تحویل موجود'),
                        'name' => 'delivery_in_stock',
                        'lang' => true,
                    ),
                    KfaDeliveryTimeUIKit::createSwitch($this->l('ویرایش شود'), 'delivery_in_stock_active'),
                    KfaDeliveryTimeUIKit::createSeparator(),
                    array(
                        'type' => 'text',
                        'label' => $this->l('زمان تحویل ناموجود'),
                        'name' => 'delivery_out_stock',
                        'lang' => true,
                    ),
                    KfaDeliveryTimeUIKit::createSwitch($this->l('ویرایش شود'), 'delivery_out_stock_active'),
                ),
                'submit' => array(
                    'title' => $this->l('ذخیره'),
                    'class' => 'btn btn-default pull-right'
                ),
            ),
        );
        
        $delivery_in_stock = array();
        $delivery_out_stock = array();
        foreach ($this->getLanguages() as $lang) {
            $id_lang = $lang['id_lang'];
            $delivery_in_stock[$id_lang] = '';
            $delivery_out_stock[$id_lang] = '';
        }
        $fields_value = array(
            'additional_delivery_times' => 0,
            'additional_delivery_times_active' => 1,
            'delivery_in_stock' => $delivery_in_stock,
            'delivery_in_stock_active' => 1,
            'delivery_out_stock' => $delivery_out_stock,
            'delivery_out_stock_active' => 1,
        );
        
        return $this->renderConfigurationForm($fields_forms, 'submitMassEdit', $fields_value);
    }
    
    private function renderConfigurationForm($fields_forms, $submit_action, $fields_value = null) {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = '';
        $helper->module = $this->module;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = '';
        $helper->submit_action = $submit_action;
        $helper->currentIndex = self::$currentIndex;
        $helper->token = $this->token;
        $helper->fields_value = $fields_value;
        $helper->tpl_vars = array(
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );
        return $helper->generateForm($fields_forms);
    }
    
    public function renderList() {
        if (!($list = parent::renderList())) {
            return $list;
        }
        
        return $this->renderMassEdit() . $list;
    }
    
    public function printStatus($is_active) {
        return $is_active
                ? '<i class="icon-check list-action-enable action-enabled"></i>'
                : '<i class="icon-times list-action-enable action-disabled"></i>';
    }
    
    public function printAdditionalDeliveryTimes($echo, $tr) {
        $options = '';
        foreach ($this->additional_delivery_times as $value => $label) {
            $selected = (int) $echo === $value ? ' selected' : '';
            $options .= "<option value='$value'$selected>$label</option>";
        }
        
        $id = "additional_delivery_times_$tr[id_product]";
        return "<select name=\"$id\" class=\"additional_delivery_times\">$options</select>";
    }
    
    public function initPageHeaderToolbar() {
        $back_to_module =
            $this->context->link->getAdminLink('AdminModules') .
            '&configure=' . Tools::safeOutput($this->module->name) .
            '&active_panel=tab-panel-order';
        
        if ($this->display) {
            $back_to_list = self::$currentIndex . "&token=$this->token";
            $this->page_header_toolbar_btn['back_to_list'] = array(
                'href' => $back_to_list,
                'desc' => $this->l('بازگشت به لیست'),
                'icon' => 'process-icon-back',
            );
        } else {
            $this->toolbar_btn['back'] = array(
                'href' => $back_to_module,
                'desc' => $this->l('بازگشت به ماژول'),
                'class' => 'process-icon-back',
            );
        }
        $this->page_header_toolbar_btn['back_to_module'] = array(
            'href' => $back_to_module,
            'desc' => $this->l('بازگشت به ماژول'),
            'class' => 'process-icon- icon-arrow-circle-up',
        );
        parent::initPageHeaderToolbar();
    }
    
    public function setHelperDisplay(Helper $helper) {
        parent::setHelperDisplay($helper);
        if (isset($this->helper->toolbar_btn['new'])) {
            unset($this->helper->toolbar_btn['new']);
        }
    }
    
    protected function ajaxProcessUpdateField() {
        $is17 = $this->module->is17;
        $value = Tools::getValue('value');
        $name = Tools::getValue('name');
        $name_parts = explode('_', $name);
        
        if (strpos($name, 'additional_delivery_times') === 0) {
            $table = $is17 ? 'product' : 'kfadeliverytime_product';
            $data = array(
                'id_product' => $name_parts[3],
                'additional_delivery_times' => (int) $value,
            );
        } elseif (strpos($name, 'delivery_in_stock') === 0) {
            $table = $is17 ? 'product_lang' : 'kfadeliverytime_product_lang';
            $data = array(
                'id_shop' => $name_parts[3],
                'id_lang' => $name_parts[4],
                'id_product' => $name_parts[5],
                'delivery_in_stock' => pSQL($value),
            );
        } elseif (strpos($name, 'delivery_out_stock') === 0) {
            $table = $is17 ? 'product_lang' : 'kfadeliverytime_product_lang';
            $data = array(
                'id_shop' => $name_parts[3],
                'id_lang' => $name_parts[4],
                'id_product' => $name_parts[5],
                'delivery_out_stock' => pSQL($value),
            );
        } else {
            $error = $this->l('ورودی‌ها معتبر نیستند.');
            $result = array('error' => $error);
            die(json_encode($result));
        }
        
        if (Db::getInstance()->insert($table, $data, false, false, Db::ON_DUPLICATE_KEY)) {
            $message = $this->l('به روز رسانی اطلاعات با موفقیت انجام شد.');
            $result = array('message' => $message);
        } else {
            $error = $this->l('به روز رسانی اطلاعات با خطا مواجه شد.');
            if (($db_error = Db::getInstance()->getMsgError())) {
                $error .= '<br>' . $db_error;
            }
            $result = array('error' => $error);
        }
        
        die(json_encode($result));
    }
}
