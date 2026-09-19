<?php
/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, September 2019
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */
class KfaDeliveryTimeCore extends Module {
    const PROGRAM_DEFAULT = 0;
    const PROGRAM_CUSTOM = 1;
    const PROGRAM_DEFAULT_OFF = 2;
    const PROGRAM_NO_DELIVERY = 3;
    
    const CARRIER_PROGRAM_DEFAULT = 0;
    const CARRIER_PROGRAM_UNAVAILABLE = 1;
    const CARRIER_PROGRAM_CUSTOM = 2;
    const CARRIER_PROGRAM_APPROXIMATE = 3;
    
    const CARRIER_EVENTS_DEFAULT = 0;
    const CARRIER_EVENTS_EMPTY = 1;
    const CARRIER_EVENTS_CUSTOM = 2;
    
    const SHOW_DESCRIPTION_DEFAULT = 0;
    const SHOW_DESCRIPTION_ALWAYS = 1;
    const SHOW_DESCRIPTION_NEVER = 2;
    
    const ORDER_CHECKING_BY_VALIDITY = 0;
    const ORDER_CHECKING_BY_STATE = 1;
    
    const DISABLED_DAYS_IGNORE = 0;
    const DISABLED_DAYS_DISPLAY = 1;
    const DISABLED_DAYS_DISPLAY_AND_COUNT = 2;
    
    const PREPARATION_TIME_DEFAULT = 0;
    const PREPARATION_TIME_BY_QTY = 1;
    const PREPARATION_TIME_BY_ROWS = 2;
    
    const DEFAULT_DATE = '2000-01-01';
    
    const LTR_DIGITS = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
    const RTL_DIGITS_FA = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
    const RTL_DIGITS_AR = array('٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩');
    
    public $conf;
    public $is17;
    
    private $languages;
    private $default_form_language;
    private $allow_employee_form_lang;
    private $active_panel = false;
    private $panels;
    private $showDeliveryTimeForAllOrders = true;
    private $deliveryOptionsCache = array();
    
    /**
     *
     * @var KfaDeliveryTimeOrderListing
     */
    private $orderListingInstance = null;
    
    private $hooks = array(
        'actionEmailAddAfterContent'                => false,
        'actionAdminOrdersListingFieldsModifier'    => array('<'    => '1.7.7'),
        'actionOrderGridQueryBuilderModifier'       => array('>='   => '1.7.7'),
        'actionOrderGridDefinitionModifier'         => array('>='   => '1.7.7'),
        'actionOrderStatusPostUpdate'               => false,
        'displayAdminOrderTabShip'                  => array('<'    => '1.7.7'),
        'displayAdminOrderContentShip'              => array('<'    => '1.7.7'),
        'displayAdminOrderTabLink'                  => array('>='   => '1.7.7'),
        'displayAdminOrderTabContent'               => array('>='   => '1.7.7'),
        'displayHeader'                                    => false,
        'displayBackOfficeHeader'                   => false,
        'displayAfterCarrier'                       => array('>='   => '1.7'),
        'displayCarrierList'                        => array('<'    => '1.7'),
        'displayOrderDetail'                        => false,
        'displayOrderConfirmation'                  => array('>='   => '1.7'),
        'displayPDFInvoice'                         => false,
        'displayProductDeliveryTime'                => array('<'    => '1.7'),
        'displayKfaDeliveryTimeOption'              => false,
    );
    
    public function __construct() {
        $this->name = 'kfadeliverytime';
        $this->tab = 'shipping_logistics';
        $this->version = '1.38.2';
        $this->author = 'Kolbeh Fanavari (systemiha.ir)';
        $this->author_uri = 'https://systemiha.ir/';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->controllers = array(
            'default',
            'webservice',
        );
        
        parent::__construct();
        
        $this->getLanguages();
        $this->initPanels();
        $this->displayName = $this->l('زمان تحویل سفارش');
        $this->description = '';
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->conf = Tools::strtoupper($this->name) . '_';
        $this->is17 = version_compare(_PS_VERSION_, '1.7.0') >= 0;
        
        if (!class_exists('KfaPersianDate')) {
            require_once $this->getLocalPath() . 'classes/KfaPersianDate.php';
        }
        require_once $this->getLocalPath() . 'classes/KfaDeliveryTimeAdditionalDeliveryTimes.php';
        require_once $this->getLocalPath() . 'classes/KfaDeliveryTimeCapacityHelper.php';
        require_once $this->getLocalPath() . 'classes/KfaDeliveryTimeCart.php';
        require_once $this->getLocalPath() . 'classes/KfaDeliveryTimeCarrier.php';
        require_once $this->getLocalPath() . 'classes/KfaDeliveryTimeEvent.php';
        require_once $this->getLocalPath() . 'classes/KfaDeliveryTimeFormatHelper.php';
        require_once $this->getLocalPath() . 'classes/KfaDeliveryTimeHistory.php';
        require_once $this->getLocalPath() . 'classes/KfaDeliveryTimeRangeHelper.php';
        require_once $this->getLocalPath() . 'classes/KfaDeliveryTimeUIKit.php';
        require_once $this->getLocalPath() . 'classes/KfaDeliveryTimeCache.php';
        
        KfaDeliveryTimeUIKit::$yes = $this->l('بله');
        KfaDeliveryTimeUIKit::$no = $this->l('خیر');
    }
    
    private function getLanguages() {
        if (!empty($this->languages)) {
            return $this->languages;
        }
        
        $cookie = $this->context->cookie;
        $this->allow_employee_form_lang = (int)Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        if ($this->allow_employee_form_lang && !$cookie->employee_form_lang) {
            $cookie->employee_form_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        }
        
        $lang_exists = false;
        $this->languages = Language::getLanguages(false);
        foreach ($this->languages as $lang) {
            if (isset($cookie->employee_form_lang) && $cookie->employee_form_lang == $lang['id_lang']) {
                $lang_exists = true;
            }
        }
        
        $this->default_form_language = $lang_exists ? (int)$cookie->employee_form_lang : (int)Configuration::get('PS_LANG_DEFAULT');
        foreach ($this->languages as $k => $language) {
            $this->languages[$k]['is_default'] = (int)($language['id_lang'] == $this->default_form_language);
        }
        
        return $this->languages;
    }
    
    private function initPanels() {
        $this->panels = array(
            array(
                'id' => 'settings',
                'title' => $this->l('تنظیمات'),
                'icon' => 'icon-cogs',
            ),
            array(
                'id' => 'delay_management',
                'title' => $this->l('مدیریت تأخیر'),
                'icon' => 'icon-plane',
            ),
            array(
                'id' => 'daily_capacity',
                'title' => $this->l('ظرفیت تحویل'),
                'icon' => 'icon-beaker',
            ),
            array(
                'id' => 'ranges',
                'title' => $this->l('بازه‌های زمانی'),
                'icon' => 'icon-time',
            ),
            array(
                'id' => 'vacations',
                'title' => $this->l('روزهای تعطیل'),
                'icon' => 'icon-coffee',
            ),
            array(
                'id' => 'no_deliveries',
                'title' => $this->l('روزهای بدون ارسال'),
                'icon' => 'icon-minus-circle',
            ),
            array(
                'id' => 'hooks',
                'title' => $this->l('زمان تحویل در هوک‌ها'),
                'icon' => 'icon-anchor',
            ),
            array(
                'id' => 'webservice',
                'title' => 'وب سرویس',
                'icon' => 'icon-plug',
            ),
            array(
                'id' => 'custom_codes',
                'title' => 'کدهای سفارشی',
                'icon' => 'icon-code',
            ),
            array(
                'id' => 'statistics',
                'title' => 'وضعیت بازه‌ها',
                'icon' => 'icon-bar-chart',
                'onclick' => 'kfadeliverytimeGetStatistics(true)',
            ),
            array(
                'id' => 'information',
                'title' => 'اطلاعات',
                'icon' => 'icon-info',
                'onclick' => 'kfaGetUpdates()',
            ),
        );
    }
    
    /**
     * 
     * @staticvar bool $result
     * @return bool
     */
    public static function calendarIsPersian() {
        static $result = null;
        if (is_null($result)) {
            $language = new Language(Configuration::get('PS_LANG_DEFAULT'));
            $result = $language->iso_code == 'fa';
        }
        return $result;
    }
    
    private function isActive() {
        return Module::isInstalled($this->name) && Module::isEnabled($this->name);
    }
    
    private function checkLicense() {
        require_once(_PS_MODULE_DIR_ . $this->name . '/classes/KfaLic.php');
        $class_name = get_class($this) . 'Lic';
        $activation_key = $class_name::getActivationKey($this);
        
        if (empty($activation_key)) {
            $ok = false;
        } else {
            $ok = $class_name::validate($activation_key, '0MP1J0EhQpXqvZLIq1tgjQ==', $this->name);
        }
        
        if (!$ok) {
            $this->disable();
        } elseif (Tools::isSubmit("submit_{$this->name}_activation")) {
            $this->enable();
            Tools::redirectAdmin(AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'));
        }
        return $ok;
    }
    
    public function getConf($key, $id_lang = null) {
        return Configuration::get($this->conf . $key, $id_lang);
    }
    
    public static function correctDigits($param) {
        return str_replace(self::RTL_DIGITS_FA, self::LTR_DIGITS, str_replace(self::RTL_DIGITS_AR, self::LTR_DIGITS, $param));
    }
    
    public static function logCalculations($message) {
        static $logging = null;
        if (is_null($logging)) {
            $logging = Configuration::get('KFADELIVERYTIME_LOGGING');
            $version = Configuration::getGlobalValue('KFADELIVERYTIME_VERSION');
            $now = date('Y-m-d H:i:s');
            $message = "\n\n____________________ $now _ $version ___________________\n\n$message";
        }
        if (!$logging) {
            return;
        }
        
        static $filename = null;
        if (is_null($filename)) {
            if (!file_exists($filename = _PS_MODULE_DIR_ . 'kfadeliverytime/logs')) {
                if (mkdir($filename)) {
                    $logging = false;
                    error_log("mkdir($filename) failed.");
                    return false;
                }
            }
            
            $context = Context::getContext();
            
            if (empty($context->employee->id)) {
                $prefix = '';
            } else {
                $prefix = 'employee_' . $context->employee->id . '_';
            }
            
            if (empty($context->cart)) {
                $filename .= "/{$prefix}general.log";
            } else {
                $filename .= "/{$prefix}" . $context->cart->id . '.log';
            }
        }
        
        $handle = fopen($filename, 'a');
        fwrite($handle, $message . "\n");
        fclose($handle);
    }
    
    private function getShopContextWarning() {
        if (Shop::getContext() == Shop::CONTEXT_SHOP) {
            return false;
        }
        
        return $this->display(dirname(__FILE__), 'views/templates/admin/shop_context_warning.tpl');
    }
    
    private function createTables() {
        $filename = $this->getLocalPath() . 'install.sql';
        if (!file_exists($filename) || !($sql = Tools::file_get_contents($filename))) {
            return false;
        }
        
        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), $sql);
        $clean_sql = preg_split("/;\s*[\r\n]+/", trim($sql));

        foreach ($clean_sql as $query) {
            if (!Db::getInstance()->execute(trim($query))) {
                return false;
            }
        }
        return true;
    }
    
    private function registerHooks() {
        $result = true;
        foreach ($this->hooks as $hook => $compatibility) {
            $compatible = true;
            if (is_array($compatibility)) {
                foreach ($compatibility as $operator => $version2) {
                    $compatible &= version_compare(_PS_VERSION_, $version2, $operator);
                }
            }
            if ($compatible) {
                $result &= $this->registerHook($hook);
            }
        }
        return $result;
    }
    
    /**
     * 
     * @param string $function
     * @return bool
     */
    private function runTabManager($function) {
        require_once $this->getLocalPath() . 'classes/KfaDeliveryTimeAdminTabManager.php';
        $adminTabManager = KfaDeliveryTimeAdminTabManager::getInstance($this->languages, $this->name, $this->displayName);
        switch ($function) {
            case 'install':
                return $adminTabManager->addTabs();
            case 'uninstall':
                return $adminTabManager->removeTabs();
            default:
                return false;
        }
    }
    
    public function install() {
        if (parent::install() && $this->runTabManager(__FUNCTION__) && $this->registerHooks() && $this->createTables()) {
            if (!$this->getConf('KEEP_DATA')) {
                $this->setDefaultConfigurations();
            }
            return true;
        }
        
        Module::disableByName($this->name);
        return false;
    }
    
    public function uninstall() {
        if ($this->getConf('KEEP_DATA')) {
            $result = true;
        } else {
            $result = $this->deleteAllConfigurations() && $this->deleteTables();
        }
        
        return $result && $this->runTabManager(__FUNCTION__) && parent::uninstall();
    }
    
    private function setDefaultConfigurations() {
        $result = true;
        
        $forms = array(
            $this->getSettingsForm(),
            $this->getCustomCodesForm(),
        );
        foreach ($forms as $form) {
            foreach ($form as $form_fields) {
                foreach ($form_fields['form']['input'] as $input) {
                    if (!empty($input['default'])) {
                        $value = $input['default'];
                    } elseif (!empty($input['default_value'])) {
                        $value = $input['default_value'];
                    } else {
                        continue;
                    }
                    
                    $key = $input['name'];
                    
                    if (empty($input['lang'])) {
                        $result &= Configuration::updateValue($key, $value);
                    } else {
                        $values = array();
                        foreach ($this->languages as $lang) {
                            $iso_code = $lang['iso_code'];
                            $id_lang = $lang['id_lang'];
                            if (isset($value[$iso_code])) {
                                $values[$id_lang] = $value[$iso_code];
                            } elseif (isset($value['all'])) {
                                $values[$id_lang] = $value['all'];
                            }
                        }
                        if (!empty($values)) {
                            $result &= Configuration::updateValue($key, $values);
                        }
                    }
                }
            }
        }
        
        return $result;
    }
    
    private function deleteAllConfigurations() {
        $result = true;
        
        $forms = array(
            $this->getSettingsForm(),
            $this->getCustomCodesForm(),
        );
        foreach ($forms as $form) {
            foreach ($form as $form_fields) {
                foreach ($form_fields['form']['input'] as $input) {
                    if ($input['type'] == 'checkbox') {
                        foreach ($input['values']['query'] as $checkbox) {
                            $key = $input['name'] . '_' . $checkbox[$input['values']['id']];
                            Configuration::deleteByName($key);
                        }
                    } elseif ($input['type'] != 'html') {
                        $result &= Configuration::deleteByName($input['name']);
                    }
                }
            }
        }
        
        $other_configurations = array(
            'VERSION',
            'USE_WEEKDAY_NAMES',
            'NEXT_DAYS_STYLE',
            'YEARLY_VACATIONS',
            'TEMPORARY_VACATIONS',
            'YEARLY_NO_DELIVERIES',
            'TEMPORARY_NO_DELIVERIES',
            'SAME_DAY',
            'FUTURE_DAYS',
            'REQUIRED',
        );
        foreach ($other_configurations as $configuration) {
            $result &= Configuration::deleteByName($this->conf . $configuration);
        }
        
        return $result;
    }
    
    private function deleteTables() {
        $tables = array(
            'kfadeliverytime_carrier',
            'kfadeliverytime_cart',
            'kfadeliverytime_day',
            'kfadeliverytime_event',
            'kfadeliverytime_history',
            'kfadeliverytime_range',
        );
        $sql = 'DROP TABLE IF EXISTS ';
        $prefix = _DB_PREFIX_;
        $max_index = count($tables) - 1;
        for ($i = 0; $i < count($tables); $i++) {
            $table = $tables[$i];
            $postfix = $i == $max_index ? '' : ', ';
            $sql .= "`{$prefix}{$table}`{$postfix}";
        }
        return Db::getInstance()->execute($sql);
    }
    
    private function deleteOldFiles() {
        $files = array(
            'views/templates/admin/off_events.tpl',
            'views/templates/admin/temporary_events_help.tpl',
            'views/templates/admin/yearly_events_help.tpl',
            'views/templates/hook/before_carrier.tpl',
            'views/templates/hook/before_carrier_prestacart.tpl',
        );
        foreach ($files as $file) {
            $filename = $this->getLocalPath() . $file;
            if (file_exists($filename) && is_file($filename)) {
                unlink($filename);
            }
        }
    }
    
    private function getDailyCapacity($date) {
        switch (date('w', strtotime($date))) {
            case 0:
                return $this->getConf('DAILY_CAPACITY_SUN');
            case 1:
                return $this->getConf('DAILY_CAPACITY_MON');
            case 2:
                return $this->getConf('DAILY_CAPACITY_TUE');
            case 3:
                return $this->getConf('DAILY_CAPACITY_WED');
            case 4:
                return $this->getConf('DAILY_CAPACITY_THU');
            case 5:
                return $this->getConf('DAILY_CAPACITY_FRI');
            case 6:
                return $this->getConf('DAILY_CAPACITY_SAT');
        }
        return '';
    }
    
    public function getCapacityInfo($delivery_time, $id_carrier, $full) {
        $helper = new KfaDeliveryTimeCapacityHelper($this);
        $nb_orders = $full ? $helper->getNbOrders($delivery_time, $id_carrier) : 0;
        $daily_capacity = $this->getDailyCapacity($delivery_time['date_start']);
        
        if (empty($delivery_time['capacity']) && empty($daily_capacity)) {
            return array(
                'out_of_capacity'   => false,
                'unlimited'         => true,
                'remaining'         => $this->l('ظرفیت باقی مانده: نامحدود'),
                'col_target'        => '',
                'col_filled'        => '',
                'col_remaining'     => '',
                'nb_orders'         => $nb_orders,
            );
        }
        
        $out_of_capacity = false;
        
        if (!empty($delivery_time['capacity'])) {
            if (strpos($delivery_time['capacity'], '#') === 0) {
                $value = $helper->getDeliveryTimeCapacityByCount($delivery_time, $id_carrier);
                $target = (int) substr($delivery_time['capacity'], 1);
                $out_of_capacity = $value >= $target;
                $col_target = number_format($target) . ' ' . $this->l('سفارش');
                $col_filled = $value ? $value . ' ' . $this->l('سفارش') : '--';
                $col_remaining = number_format($target - $value) . ' ' . $this->l('سفارش');
                $remaining = sprintf($this->l('ظرفیت باقی مانده: %1s از %2s سفارش'), number_format($target - $value), number_format($target));
            } else {
                $value = $helper->getDeliveryTimeCapacityByTotalPaid($delivery_time, $id_carrier);
                $target = (int) $delivery_time['capacity'];
                $out_of_capacity = $value >= $target;
                $col_target = Tools::displayPrice($target);
                $col_filled = $value ? Tools::displayPrice($value) : '--';
                $col_remaining = Tools::displayPrice($target - $value);
                $remaining = sprintf($this->l('ظرفیت باقی مانده: %1s از %2s'), Tools::displayPrice($target - $value), Tools::displayPrice($target));
            }
        }
        
        if (!$out_of_capacity && !empty($daily_capacity)) {
            if (strpos($daily_capacity, '#') === 0) {
                $value = $helper->getDayCapacityByCount($delivery_time, $id_carrier);
                $target = (int) substr($daily_capacity, 1);
                $out_of_capacity = $value >= $target;
                $col_target = number_format($target) . ' ' . $this->l('سفارش');
                $col_filled = $value ? $value . ' ' . $this->l('سفارش') : '--';
                $col_remaining = number_format($target - $value) . ' ' . $this->l('سفارش');
                $remaining = sprintf($this->l('ظرفیت باقی مانده: %1s از %2s سفارش'), number_format($target - $value), number_format($target));
            } else {
                $value = $helper->getDayCapacityByTotalPaid($delivery_time, $id_carrier);
                $target = (int) $daily_capacity;
                $out_of_capacity = $value >= $target;
                $col_target = Tools::displayPrice($target);
                $col_filled = $value ? Tools::displayPrice($value) : '--';
                $col_remaining = Tools::displayPrice($target - $value);
                $remaining = sprintf($this->l('ظرفیت باقی مانده: %1s از %2s'), Tools::displayPrice($target - $value), Tools::displayPrice($target));
            }
        }
        
        return array(
            'out_of_capacity'   => $out_of_capacity,
            'unlimited'         => false,
            'remaining'         => $remaining,
            'col_target'        => $col_target,
            'col_filled'        => $col_filled,
            'col_remaining'     => $col_remaining,
            'nb_orders'         => $nb_orders,
        );
    }
    
    protected function doUpdates() {
        $this->deleteOldFiles();
        require_once $this->getLocalPath() . 'classes/KfaDeliveryTimeDb.php';
        $db = new KfaDeliveryTimeDb($this->conf);
        return $this->registerHooks() && $this->createTables() && $db->check() && $this->runTabManager('install');
    }
    
    private function addMediaWithVersion($path) {
        $info = pathinfo($path);
        if (empty($info['extension'])) {
            return;
        }
        
        switch ($info['extension']) {
            case 'css':
                $this->context->controller->addCSS("$path", 'all');
                break;
            case 'js':
                $this->context->controller->addJS("$path");
                break;
        }
    }
    
    public function getContent() {
        $this->context->controller->addCSS($this->getPathUri() . "views/css/admin.css?v=$this->version", 'all', null, false);
        $this->context->controller->addJS($this->getPathUri() . "views/js/admin.js?v=$this->version");
        
        /*if (!$this->checkLicense()) {
            $class_name = get_class($this) . 'Lic';
            return $class_name::generateForm($this, $this->context);
        }*/
        
        $html = $this->postProcess();
        
        if (version_compare(Configuration::getGlobalValue($this->conf . 'VERSION'), $this->version) == -1) {
            if ($this->doUpdates()) {
                $html .= $this->displayConfirmation($this->l('تنظیم مجدد انجام شد.'));
                Configuration::updateGlobalValue($this->conf . 'VERSION', $this->version);
            } else {
                $html .= $this->displayError($this->l('تنظیم مجدد انجام نشد.'));
            }
        }
        
        $html .= $this->getPrestacartWarning();
        
        foreach ($this->panels as $panel) {
            if (Tools::isSubmit($panel['id'] . '_saved')) {
                $confirmation = sprintf($this->l('%s با موفقیت به روز شد.'), $panel['title']);
                $html .= $this->displayConfirmation($confirmation);
                $this->active_panel = $panel['id'];
            }
        }
        
        if (Tools::isSubmit('active_panel')) {
            $this->active_panel = Tools::getValue('active_panel');
        }
        
        return $html . $this->renderDefault();
    }
    
    private function getPrestacartWarning() {
        if (!(Module::isInstalled('psf_prestacart') && Module::isEnabled('psf_prestacart'))) {
            return '';
        }
        
        $path = _PS_MODULE_DIR_ . 'psf_prestacart/themes/default/assets/js/prestacart.js';
        if (!file_exists($path)) {
            return '';
        }
        
        $content = file_get_contents($path);
        $needle = "$(document).on('click', '#psy_continue_steps'";
        if (strpos($content, $needle) !== false) {
            return '';
        }
        
        return $this->display(dirname(__FILE__), 'views/templates/admin/prestacart_compatibility.tpl');
    }
    
    private function renderDefault() {
        $url = $this->context->link->getAdminLink('AdminModules') . '&configure=' . Tools::safeOutput($this->name);
        $info =
            '<div style="text-align: center;">' .
            '	<p><a href="http://systemiha.ir/" target="_blank" title="سیستمی‌ها"><img src="' . $this->getPathUri() . 'logo.png" /></a></p>' .
            '	<p><strong>سیستمی‌ها</strong></p>' .
            '	<p>PHP version: ' . phpversion() . '</p>' .
            '	<p>Module version: ' . $this->version . '</p>' .
            '	<hr>' .
            '   <form id="kfa_updates_form" action="' . $url . '">' .
            '       <div id="kfa_updates_response">'.
            '       </div>' .
            '       <div>' .
            '           <img id="kfa_updates_wait" style="display: none;" src="' . $this->getPathUri() . 'views/img/wait.gif">' .
            '       </div>' .
            '   <form>' .
            '	<p>تمامی حقوق این نرم افزار برای <a href="http://systemiha.ir/" target="_blank">سیستمی‌ها</a> محفوظ است.</p>' .
            '</div>';
        $statistics =
            '<div>' .
            '   <form id="kfadeliverytime-statistics-form" action="' . $url . '">' .
            '       <div id="kfadeliverytime-statistics-response">'.
            '       </div>' .
            '       <div style="text-align: center;">' .
            '           <img id="kfadeliverytime-statistics-wait" style="display: none;" src="' . $this->getPathUri() . 'views/img/wait.gif">' .
            '       </div>' .
            '   </form>' .
            '</div>';
        
        $active_tab_found = false;
        foreach ($this->panels as &$panel) {
            if (!$active_tab_found && $panel['id'] == $this->active_panel) {
                $panel['active'] = true;
                $active_tab_found = true;
            }
            switch ($panel['id']) {
                case 'statistics':
                    $panel['content'] = $statistics;
                    break;
                case 'information':
                    $panel['content'] = $info;
                    break;
                default:
                    if (method_exists($this, $method_name = 'render' . Tools::toCamelCase($panel['id'], true) . 'Form')) {
                        $panel['content'] = $this->{$method_name}();
                    } elseif (method_exists($this, $method_name = 'get' . Tools::toCamelCase($panel['id'], true) . 'Form')) {
                        $panel['content'] = $this->renderConfigurationForm($this->{$method_name}(), "submit_$panel[id]");
                    } else {
                        $panel['content'] = sprintf($this->l('متد «%s» در ماژول تعریف نشده است.'), $method_name);
                    }
                    break;
            }
        }
        if (!$active_tab_found) {
            $this->panels[0]['active'] = true;
        }

        $this->context->smarty->assign('tabs', $this->panels);
        return $this->display(dirname(__FILE__), 'views/templates/admin/tabs.tpl');
    }
    
    /* POST PROCESS */
    
    private function postProcess() {
        $submitted = '';
        foreach ($this->panels as $panel) {
            $id = $panel['id'];
            if (Tools::isSubmit("submit_$id")) {
                $this->active_panel = $id;
                if (method_exists($this, $method_name = 'process' . Tools::toCamelCase($id, true))) {
                    $errors = $this->$method_name();
                } elseif (method_exists($this, $method_name = 'get' . Tools::toCamelCase($id, true) . 'Form')) {
                    $errors = $this->autoSetFieldsValue($this->{$method_name}());
                } else {
                    $errors = array(
                        sprintf($this->l('متدی برای ذخیره‌ی اطلاعات سربرگ «%s» در فایل اصلی ماژول تعریف نشده است.'), $panel['title']),
                    );
                }
                $submitted = "{$id}_saved";
            }
        }
        
        if (isset($errors) && count($errors)) {
            return $this->displayError(implode('<br>', $errors));
        } elseif (!empty($submitted)) {
            $this->redirectAfter($submitted);
        }
    }
    
    private function redirectAfter($submitted) {
        $token = Tools::getAdminTokenLite('AdminModules');
        $url = AdminController::$currentIndex ."&configure=$this->name&token=$token&$submitted";
        Tools::redirectAdmin($url);
    }
    
    /* FORM HOOKS */
    
    protected function getHooksForm() {
        $fields_forms = array();
        
        $fields_forms['hooks'] = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('نمایش زمان تحویل سفارش'),
                    'icon' => '',
                ),
                'description' => $this->l('کدهای قابل استفاده در قالب‌های نمایش زمان تحویل سفارش:') . '<br>{y} سال<br>{m} ماه<br>{mm} نام ماه<br>{d} روز<br>{w} روز هفته<br>{from} زمان شروع بازه<br>{to} زمان پایان بازه',
                'input' => array(
                    KfaDeliveryTimeUIKit::createSwitchConf($this->l('لیست سفارش‌های بخش مدیریت')
                            , 'HOOK_ADMIN_ORDERS_LIST'
                            , null
                            , 1),
                    KfaDeliveryTimeUIKit::createTextLangConf($this->l('قالب')
                            , 'HOOK_ADMIN_ORDERS_LIST_FORMAT'
                            , $this->l('پیش‌فرض: {y}-{m}-{d} {from}-{to}')
                            . '<br>'
                            . $this->l('توجه: این قالب فقط برای حامل‌های عادی است و برای حامل‌های زمان تقریبی کاربردی ندارد.')
                            . '<br>'
                            . $this->l('قالب حامل‌های زمان تقریبی را از سربرگ «بازه‌های زمانی» تنظیم کنید.')
                            , null
                            , null
                            , false
                            , '{y}-{m}-{d} {from}-{to}'),
                    KfaDeliveryTimeUIKit::createSwitchConf($this->l('جزئیات سفارش بخش مدیریت')
                            , 'HOOK_ADMIN_ORDER_DETAIL'
                            , null
                            , 1),
                    KfaDeliveryTimeUIKit::createTextLangConf($this->l('قالب')
                            , 'HOOK_ADMIN_ORDER_DETAIL_FORMAT'
                            , $this->l('پیش‌فرض: {w} {d}-{m}-{y} ساعت {from} تا {to}')
                            . '<br>'
                            . $this->l('توجه: این قالب برای حامل‌های زمانی تقریبی کاربردی ندارد؛ زیرا زمان تحویل تقریبی یک پیام متنی است که قابل تجزیه به تاریخ و ساعت شروع و پایان نیست.')
                            , null
                            , null
                            , false
                            , '{w} {d}-{m}-{y} ساعت {from} تا {to}'),
                    KfaDeliveryTimeUIKit::createNumberInputConf($this->l('تعداد سطرهای تاریخچه')
                            , 'HISTORY_LIMIT'
                            , 3
                            , ''
                            , false
                            , $this->l('ردیف')),
                    KfaDeliveryTimeUIKit::createSeparator(),
                    KfaDeliveryTimeUIKit::createSwitchConf($this->l('جزئیات سفارش بخش کاربری')
                            , 'HOOK_ORDER_DETAIL'
                            , null
                            , 1),
                    KfaDeliveryTimeUIKit::createTextLangConf($this->l('قالب عادی')
                            , 'HOOK_ORDER_DETAIL_FORMAT'
                            , $this->l('پیش‌فرض:  زمان تحویل سفارش: {w} {d}-{m}-{y} ساعت {from} تا {to}')
                            , null
                            , null
                            , false
                            , '{w} {d}-{m}-{y} ساعت {from} تا {to}'),
                    KfaDeliveryTimeUIKit::createTextLangConf($this->l('قالب 23:59')
                            , 'HOOK_ORDER_DETAIL_FORMAT_2359'
                            , $this->l('برای بازه‌هایی که ساعت آن‌ها از 00:00 تا 23:59 است از این قالب استفاده می‌شود. اگر مقداری وارد نکنید از همان قالب عادی استفاده خواهد شد.')),
                    KfaDeliveryTimeUIKit::createSwitchConf($this->l('صورت‌حساب PDF')
                            , 'HOOK_PDF'
                            , null
                            , 1),
                    KfaDeliveryTimeUIKit::createTextLangConf($this->l('قالب عادی')
                            , 'HOOK_PDF_FORMAT'
                            , $this->l('پیش‌فرض:  زمان تحویل سفارش: {w} {d}-{m}-{y} ساعت {from} تا {to}')
                            , null
                            , null
                            , false
                            , '{w} {d}-{m}-{y} ساعت {from} تا {to}'),
                    KfaDeliveryTimeUIKit::createTextLangConf($this->l('قالب 23:59')
                            , 'HOOK_PDF_FORMAT_2359'
                            , $this->l('برای بازه‌هایی که ساعت آن‌ها از 00:00 تا 23:59 است از این قالب استفاده می‌شود. اگر مقداری وارد نکنید از همان قالب عادی استفاده خواهد شد.')),
                    KfaDeliveryTimeUIKit::createSwitchConf($this->l('ایمیل')
                            , 'HOOK_EMAIL'
                            , $this->l('علاوه بر فعال کردن این سوئیچ، باید کد {delivery_time} را نیز به قالب ایمیل‌های مورد نظرتان اضافه کنید.')
                            , 1),
                    KfaDeliveryTimeUIKit::createTextLangConf($this->l('قالب عادی')
                            , 'HOOK_EMAIL_FORMAT'
                            , $this->l('پیش‌فرض:  زمان تحویل سفارش: {w} {d}-{m}-{y} ساعت {from} تا {to}')
                            , null
                            , null
                            , false
                            , ' زمان تحویل سفارش: {w} {d}-{m}-{y} ساعت {from} تا {to}'),
                    KfaDeliveryTimeUIKit::createTextLangConf($this->l('قالب 23:59')
                            , 'HOOK_EMAIL_FORMAT_2359'
                            , $this->l('برای بازه‌هایی که ساعت آن‌ها از 00:00 تا 23:59 است از این قالب استفاده می‌شود. اگر مقداری وارد نکنید از همان قالب عادی استفاده خواهد شد.')),
                ),
                'submit' => array(
                    'title' => $this->l('ذخیره'),
                    'class' => 'btn btn-default pull-right'
                ),
            ),
        );
        
        $dbsinvoice = Module::isInstalled('dbsinvoice') && Module::isEnabled('dbsinvoice');
        $ranginesmspresta = Module::isInstalled('ranginesmspresta') && Module::isEnabled('ranginesmspresta');
        if ($dbsinvoice || $ranginesmspresta || $this->context->shop->domain == 'localhost') {
            $fields_forms['additional_hook'] = array(
                'form' => array(
                    'legend' => array(
                        'title' => $this->l('هوک مخصوص ماژول‌های پرستاپرینت و پیامک رنگینه'),
                        'icon' => '',
                    ),
                    'input' => array(
                        array(
                            'type' => 'html',
                            'name' => "<textarea class=\"kfa-ltr\" readonly>{hook h='displayKfaDeliveryTimeOption' order=\$order format='{w} {d} {mm} {y} بین {from} تا {to}' format2359='{w} {d} {mm} {y}' fa=true}</textarea>",
                            'label' => $this->l('کد قالب برای استفاده در فاکتور'),
                            'desc' => $this->l('پارامتر format را به دلخواه خود تغییر دهید.') .
                                    '<br>' .
                                    $this->l('کاربرد پارامتر format2359، مشابه format اما اختیاری و برای بازه‌های زمانی 00:00 تا 23:59 است.') .
                                    '<br>' .
                                    $this->l('پارامتر fa=true اختیاری بوده و برای تبدیل ارقام انگلیسی به فارسی است؛ می‌توانید حذفش کنید.'),
                        ),
                        KfaDeliveryTimeUIKit::createSwitchConf($this->l('چاپ زمان تقریبی')
                                , 'PRINT_ABNORMAL_OPTION'
                                , $this->l('برای جلوگیری از نمایش زمان تقریبی در فاکتور و پیامک (به دلیل طولانی بودن متن زمان تقریبی)، این گزینه را خاموش کنید.')
                                , 1),
                    ),
                    'submit' => array(
                        'title' => $this->l('ذخیره'),
                        'class' => 'btn btn-default pull-right'
                    ),
                ),
            );
        }
        
        return $fields_forms;
    }
    
    /* FORM SETTINGS */
    
    private function getOrderStates() {
        $order_states = OrderState::getOrderStates($this->context->language->id);
        $result = array();
        if (is_array($order_states)) {
            foreach ($order_states as $order_state) {
                $result[] = array(
                    'id' => $order_state['id_order_state'],
                    'name' => $order_state['name'],
                    'val' => '1',
                );
            }
        }
        return $result;
    }
    
    private function getSettingsForm() {
        $fields_forms = array();
        
        $descriptions = array(
            array(
                'id' => 'SHOW_DESCRIPTION_' . KfaDeliveryTime::SHOW_DESCRIPTION_DEFAULT,
                'value' => KfaDeliveryTime::SHOW_DESCRIPTION_DEFAULT,
                'label' => $this->l('پیش‌فرض (در صورت وجود بازه‌ی غیرفعال)'),
            ),
            array(
                'id' => 'SHOW_DESCRIPTION_' . KfaDeliveryTime::SHOW_DESCRIPTION_ALWAYS,
                'value' => KfaDeliveryTime::SHOW_DESCRIPTION_ALWAYS,
                'label' => $this->l('همیشه'),
            ),
            array(
                'id' => 'SHOW_DESCRIPTION_' . KfaDeliveryTime::SHOW_DESCRIPTION_NEVER,
                'value' => KfaDeliveryTime::SHOW_DESCRIPTION_NEVER,
                'label' => $this->l('هرگز'),
            ),
        );
        
        $disabled_days_options = array(
            array(
                'value' => KfaDeliveryTime::DISABLED_DAYS_IGNORE,
                'label' => $this->l('حذف'),
            ),
            array(
                'value' => KfaDeliveryTime::DISABLED_DAYS_DISPLAY,
                'label' => $this->l('نمایش'),
            ),
            array(
                'value' => KfaDeliveryTime::DISABLED_DAYS_DISPLAY_AND_COUNT,
                'label' => $this->l('نمایش و شمارش به عنوان روز دارای ارسال'),
            ),
        );
        
        $fields_forms['general'] = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('عمومی'),
                    'icon' => '',
                ),
                'input' => array(
                    KfaDeliveryTimeUIKit::createSwitchConf($this->l('نگهداری اطلاعات در هنگام عزل نصب')
                            , 'KEEP_DATA'
                            , $this->l('برای حفظ تنظیمات ماژول، در هنگام «تنظیم مجدد» یا «حذف ماژول»، این گزینه را فعال کنید.')
                            , 1),
                    KfaDeliveryTimeUIKit::createSwitchConf($this->l('فعال سازی لاگ')
                            , 'LOGGING'
                            , $this->l('فقط با هماهنگی برنامه نویس فعال شود.')),
                    KfaDeliveryTimeUIKit::createSwitchConf($this->l('نمایش ستون حامل در لیست سفارش‌ها'), 'ADD_CARRIER_NAME_TO_ORDERS_LISTING'),
                    KfaDeliveryTimeUIKit::createSwitchConf($this->l('مخفی کردن ساعت 00:00 تا 23:59')
                            , 'HIDE_LONG_TIMES'
                            , $this->l('با فعال کردن این گزینه، اگر بازه‌ای از ساعت 00:00 تا 23:59 باشد، در سبد خرید فقط تاریخش نمایش داده می‌شود و ساعتش مخفی می‌شود.')
                            , 0
                            , false
                            , null
                            , $this->l('فقط برای سبد خرید')),
                    KfaDeliveryTimeUIKit::createSwitchConf($this->l('اسکرول فقط هنگام تعویض حامل')
                            , 'SCROLL_ON_CHANGE_ONLY'
                            , $this->l('با فعال کردن این سوئیچ، حتی اگر «اسکرول به جدول زمان تحویل» در تنظیمات یک حامل فعال شده باشد، در لود اولیه‌ی سبد خرید، اسکرول به جدول زمان تحویل انجام نخواهد شد.')
                            , 1),
                    KfaDeliveryTimeUIKit::createRadioConf('DISABLED_DAYS'
                            , $this->l('روزهای فاقد بازه‌ی فعال')
                            , $disabled_days_options
                            , 0
                            , $this->l('روزهایی که همه‌ی بازه‌های آن‌ها به دلیل تکمیل ظرفیت یا تنظیمات شما غیرفعال شده‌اند.')),
                ),
                'submit' => array(
                    'title' => $this->l('ذخیره'),
                    'class' => 'btn btn-default pull-right'
                ),
            ),
        );
        
        $fields_forms['colors'] = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('رنگ بازه‌ها در بخش افزودن/ویرایش سفارش'),
                    'icon' => '',
                ),
                'input' => array(
                    KfaDeliveryTimeUIKit::createColorInputConf($this->l('خارج از دسترس'), 'RANGE_COLOR_UNAVAILABLE', '#ffa500', true),
                    KfaDeliveryTimeUIKit::createColorInputConf($this->l('تکمیل ظرفیت'), 'RANGE_COLOR_OUT_OF_CAPACITY', '#dc143c', true),
                    KfaDeliveryTimeUIKit::createColorInputConf($this->l('غیرفعال'), 'RANGE_COLOR_DISABLED', '#a9a9a9', true),
                    KfaDeliveryTimeUIKit::createColorInputConf($this->l('فعال'), 'RANGE_COLOR_ENABLED', '#000000', true),
                ),
                'submit' => array(
                    'title' => $this->l('ذخیره'),
                    'class' => 'btn btn-default pull-right'
                ),
            ),
        );
        
        $fields_forms['texts'] = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('متن‌های نمایشی'),
                    'icon' => '',
                ),
                'description' => $this->context->smarty->fetch($this->getLocalPath() . 'views/templates/admin/_configure/texts_description.tpl'),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('قالب تاریخ امروز'),
                        'name' => $this->conf . 'TODAY_TEXT',
                        'desc' => $this->l('پیش‌فرض: امروز'),
                        'default' => array('all' => 'امروز'),
                        'required' => true,
                        'lang' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('قالب تاریخ فردا'),
                        'name' => $this->conf . 'TOMORROW_TEXT',
                        'desc' => $this->l('پیش‌فرض: فردا'),
                        'default' => array('all' => 'فردا'),
                        'required' => true,
                        'lang' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('قالب تاریخ‌های دیگر'),
                        'name' => $this->conf . 'NEXT_DAYS_TEXT',
                        'desc' => $this->l('پیش‌فرض: {w} {d} {mm} {y}'),
                        'default' => array('all' => '{w} {d} {mm} {y}'),
                        'required' => true,
                        'lang' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('قالب روزهای باقیمانده'),
                        'name' => $this->conf . 'REMAINING_DAYS_TEXT',
                        'desc' => $this->l('پیش‌فرض: %d روز دیگر'),
                        'default' => array('all' => '%d روز دیگر'),
                        'required' => true,
                        'lang' => true,
                        'hint' => $this->l('هشدار: حتماً از %d در متن خود استفاده کنید.'),
                    ),
                    KfaDeliveryTimeUIKit::createSeparator(),
                    array(
                        'type' => 'text',
                        'label' => $this->l('متن بین ساعت شروع و پایان'),
                        'name' => $this->conf . 'TIME_SEPARATOR',
                        'desc' => $this->l('اگر خالی بگذارید از خط تیره استفاده می‌شود.'),
                        'default' => array('all' => ' تا '),
                        'lang' => true,
                    ),
                    KfaDeliveryTimeUIKit::createSeparator(),
                    array(
                        'type' => 'text',
                        'label' => $this->l('توضیح خارج از دسترس'),
                        'name' => $this->conf . 'UNAVAILABLE_DESCRIPTION',
                        'desc' => $this->l('توضیح اختیاری کوتاه برای بازه‌ای که به دلیل اعمال محدودیت حداکثر زمان دسترسی، غیرفعال شده است.'),
                        'default' => array('all' => 'خارج از دسترس'),
                        'lang' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('توضیح تکمیل ظرفیت'),
                        'name' => $this->conf . 'OUT_OF_CAPACITY_DESCRIPTION',
                        'desc' => $this->l('توضیح اختیاری کوتاه برای بازه‌ای که به اندازه‌ی ظرفیتش در آن سفارش معتبر ثبت شده است و قابل انتخاب نیست.'),
                        'default' => array('all' => 'تکمیل ظرفیت'),
                        'lang' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('توضیح گزینه‌های غیرفعال'),
                        'name' => $this->conf . 'DISABLED_OPTION_DESCRIPTION',
                        'desc' => $this->l('توضیح اختیاری کوتاه برای بازه‌ای که زمان باقی مانده تا پایان آن، کمتر از «زمان آماده سازی» است و قابل انتخاب نیست.'),
                        'default' => array('all' => 'منقضی شده'),
                        'lang' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('توضیح گزینه‌های فعال'),
                        'name' => $this->conf . 'NORMAL_OPTION_DESCRIPTION',
                        'desc' => $this->l('توضیح اختیاری کوتاه برای بازه‌هایی که قابل انتخاب هستند.'),
                        'default' => array('all' => 'معتبر'),
                        'lang' => true,
                    ),
                    array(
                        'type' => 'radio',
                        'name' => $this->conf . 'SHOW_DESCRIPTION',
                        'label' => $this->l('نمایش توضیحات'),
                        'values' => $descriptions,
                        'default' => KfaDeliveryTime::SHOW_DESCRIPTION_DEFAULT,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('ذخیره'),
                    'class' => 'btn btn-default pull-right'
                ),
            ),
        );
        
        $fields_forms['errors'] = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('پیام‌های خطا در سبد خرید'),
                    'icon' => '',
                ),
                'input' => array(
                    KfaDeliveryTimeUIKit::createTextLangConf($this->l('انتخاب منقضی شده')
                            , 'SELECTION_EXPIRED'
                            , $this->l('برای وقتی که مشتری زمان تحویل سفارش را انتخاب کرده است اما به دلیل گذشت زمان، بازه‌ی انتخابی منقضی شده است.')
                            , null
                            , null
                            , false
                            , 'زمانی را که برای ارسال سفارش انتخاب کرده‌اید، منقضی شده است.'),
                    KfaDeliveryTimeUIKit::createTextLangConf($this->l('انتخاب در دسترس نیست')
                            , 'SELECTION_NOT_AVAILABLE'
                            , $this->l('برای وقتی که مشتری زمان تحویل سفارش را انتخاب کرده است اما به دلیل عوض شدن تنظیمات بازه‌ها در بخش مدیریت، بازه‌ی انتخابی دیگر وجود ندارد.')
                            , null
                            , null
                            , false
                            , 'زمانی را که برای تحویل سفارش خود انتخاب کرده‌اید، دیگر در دسترس نیست.'),
                    KfaDeliveryTimeUIKit::createTextLangConf($this->l('انتخاب انجام نشده')
                            , 'SELECTION_NOT_DONE'
                            , $this->l('برای وقتی که انتخاب زمان تحویل الزامی است و مشتری زمان تحویل سفارش را انتخاب نکرده است.')
                            , null
                            , null
                            , false
                            , 'زمان ارسال سفارش، انتخاب نشده است.'),
                ),
                'submit' => array(
                    'title' => $this->l('ذخیره'),
                    'class' => 'btn btn-default pull-right'
                ),
            ),
        );
        
        $grouping_options = array(
            array(
                'id' => 'DESKTOP',
                'name' => $this->l('کامپیوتر'),
                'val' => 1,
            ),
            array(
                'id' => 'MOBILE',
                'name' => $this->l('موبایل و تبلت'),
                'val' => 1,
            ),
        );
        $grouping_description = $this->context->smarty->fetch($this->getLocalPath() . 'views/templates/admin/_configure/grouping_description.tpl');
        $fields_forms['grouping'] = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('نمایش بازه‌ها به صورت گروه بندی شده'),
                    'icon' => '',
                ),
                'description' => $grouping_description,
                'input' => array(
                    KfaDeliveryTimeUIKit::createCheckBoxConf($this->l('فعال در'), 'GROUPING', $grouping_options),
                    KfaDeliveryTimeUIKit::createSeparator(),
                    KfaDeliveryTimeUIKit::createTextLangConf($this->l('قالب سربرگ - خط اول')
                            , 'GROUPING_FORMAT_TAB_LINE1'
                            , $this->l('پیش‌فرض: {w}')
                            , null
                            , null
                            , false
                            , '{w}'),
                    KfaDeliveryTimeUIKit::createTextLangConf($this->l('قالب سربرگ - خط دوم')
                            , 'GROUPING_FORMAT_TAB_LINE2'
                            , $this->l('پیش‌فرض: {d} {mm}')
                            , null
                            , null
                            , false
                            , '{d} {mm}'),
                    KfaDeliveryTimeUIKit::createTextLangConf($this->l('قالب بازه‌های زمانی')
                            , 'GROUPING_FORMAT_RANGE'
                            , $this->l('پیش‌فرض: ساعت {from} تا {to}')
                            , null
                            , null
                            , false
                            , 'ساعت {from} تا {to}'),
                ),
                'submit' => array(
                    'title' => $this->l('ذخیره'),
                    'class' => 'btn btn-default pull-right'
                ),
            ),
        );
        
        return $fields_forms;
    }
    
    /* FORM DELAY MANAGEMENT */
    
    protected function getDelayManagementForm() {
        $fields_forms = array();
        
        $fields_forms['no_working_days_preparation'] = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('آماده سازی در روزهای غیرکاری'),
                    'icon' => '',
                ),
                'description' => $this->l('با تنظیمات زیر می‌توانید حداکثر زمان در دسترس بودن بازه‌ها (آیکن قفل سبز رنگ) را در روزهای غیرکاری مدیریت کنید. برای هر کدام از روزهای غیرکاری که می‌توانید سفارش‌ها را آماده کنید، سوئیچ مربوط به آن را فعال کنید.'),
                'warning' => $this->l('توجه: این تنظیمات تأثیری در حامل‌های زمان تقریبی ندارند.'),
                'input' => array(
                    KfaDeliveryTimeUIKit::createSwitchConf($this->l('آماده سازی در روزهای تعطیل'), 'AVAILABILITY_DO_NOT_SKIP_OFF_DAYS'),
                    KfaDeliveryTimeUIKit::createSwitchConf($this->l('آماده سازی در روزهای بدون ارسال'), 'AVAILABILITY_DO_NOT_SKIP_NO_DELIVERY_DAYS'),
                    KfaDeliveryTimeUIKit::createSwitchConf($this->l('آماده سازی در روزهای هفته با برنامه‌ی «ارسال نداریم»'), 'AVAILABILITY_DO_NOT_SKIP_NO_DELIVERY_WEEKDAYS'),
                ),
            ),
        );
        
        $preparation_time_types = array(
            array(
                'value' => KfaDeliveryTime::PREPARATION_TIME_DEFAULT,
                'label' => $this->l('بدون افزایش'),
            ),
            array(
                'value' => KfaDeliveryTime::PREPARATION_TIME_BY_QTY,
                'label' => $this->l('بر اساس تعداد اقلام سبد خرید'),
            ),
            array(
                'value' => KfaDeliveryTime::PREPARATION_TIME_BY_ROWS,
                'label' => $this->l('بر اساس تنوع محصولات (تعداد ردیف‌های سبد خرید)'),
            ),
        );
        $fields_forms['preparation_time'] = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('زمان آماده سازی'),
                    'icon' => '',
                ),
                'warning' => $this->l('تنظیمات این بخش فقط برای اعمال محدودیت روی بازه‌های زمانی امروز است.')
                    . '<br>'
                    . $this->l('جهت اعمال محدودیت روی بازه‌های زمانی روزهای آینده، در سربرگ «بازه‌های زمانی» از آیکن قفل مقابل هر بازه استفاده کنید.')
                    . '<br>'
                    . '<a href="http://systemiha.ir/dl/video/kfadeliverytime/kfadeliverytime-availability.mp4" target="_blank">' . $this->l('فیلم راهنما') . '</a>',
                'input' => array(
                    KfaDeliveryTimeUIKit::createNumberInputConf($this->l('زمان آماده سازی پایه')
                            , 'PREPARATION_TIME'
                            , 60
                            , $this->l('مدت زمانی که برای آماده سازی سفارش نیاز دارید. برای مثال، اگر 60 دقیقه زمان نیاز دارید، مشتری نمی‌تواند بازه‌ای را که تا 60 دقیقه دیگر به پایان می‌رسد انتخاب کند.')
                            , false
                            , $this->l('دقیقه')),
                    KfaDeliveryTimeUIKit::createRadioConf('PREPARATION_TYPE'
                            , $this->l('نحوه‌ی افزایش')
                            , $preparation_time_types),
                    KfaDeliveryTimeUIKit::createNumberInputConf($this->l('شروع افزایش زمان از')
                            , 'PREPARATION_MINIMUM_VOLUME'
                            , 0
                            , null
                            , true
                            , $this->l('قلم/محصول')
                            , 'PREPARATION_ADVANCED'),
                    KfaDeliveryTimeUIKit::createNumberInputConf($this->l('میزان افزایش زمان')
                            , 'PREPARATION_EXTRA_TIME'
                            , 0
                            , null
                            , true
                            , $this->l('دقیقه')
                            , 'PREPARATION_ADVANCED'),
                    KfaDeliveryTimeUIKit::createNumberInputConf($this->l('به ازای هر')
                            , 'PREPARATION_EXTRA_STEP'
                            , 0
                            , null
                            , true
                            , $this->l('قلم/محصول')
                            , 'PREPARATION_ADVANCED'),
                ),
                'submit' => array(
                    'title' => $this->l('ذخیره'),
                    'class' => 'btn btn-default pull-right'
                ),
            ),
        );
        
        $warning = $this->l('عملکرد این بخش ممکن است بر خلاف انتظار شما باشد.')
                . '<br>'
                . $this->l('برای آشنایی با طرز کار آن، فیلم راهنما را مشاهده کنید.')
                . '<br>'
                . '<a href="http://systemiha.ir/dl/video/kfadeliverytime/kfadeliverytime-additional-delivery-times.mp4" target="_blank">' . $this->l('فیلم راهنمای عمومی (برای پرستاشاپ 1.6 و 1.7)') . '</a>';
        if (!$this->is17) {
            $warning .= '<br><a href="http://systemiha.ir/dl/video/kfadeliverytime/kfadeliverytime-additional-delivery-times-2.mp4" target="_blank">' . $this->l('فیلم راهنمای مخصوص پرستاشاپ 1.6') . '</a>';
        }
        $fields_forms['additional_delivery_times'] = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('در نظر گرفتن زمان تحویل محصول'),
                    'icon' => '',
                ),
                'warning' => $warning,
                'input' => array(
                    KfaDeliveryTimeUIKit::createSwitchConf($this->l('فعال'), 'ADDITIONAL_DELIVERY_TIMES'),
                ),
                'submit' => array(
                    'title' => $this->l('ذخیره'),
                    'class' => 'btn btn-default pull-right'
                ),
            ),
        );
        if (!$this->is17) {
            $controller = $this->context->link->getAdminLink('AdminKfaDeliveryTimeProducts');
            $fields_forms['additional_delivery_times']['form']['input'][] = KfaDeliveryTimeUIKit::createSwitchConf($this->l('نمایش در صفحه‌ی محصول'), 'DISPLAY_PRODUCT_DELIVERY_TIME');
            $fields_forms['additional_delivery_times']['form']['input'][] = array(
                'type' => 'html',
                'name' => "<a class='btn btn-default' href='$controller'>" . $this->l('تنظیم زمان تحویل محصولات') . '</a>',
                'label' => $this->l('برو به'),
            );
            $fields_forms['additional_delivery_times']['form']['input'][] = KfaDeliveryTimeUIKit::createSeparator();
            $fields_forms['additional_delivery_times']['form']['input'][] = KfaDeliveryTimeUIKit::createTextLangConf($this->l('زمان تحویل پیش‌فرض محصولات موجود'), 'LABEL_DELIVERY_TIME_AVAILABLE');
            $fields_forms['additional_delivery_times']['form']['input'][] = KfaDeliveryTimeUIKit::createTextLangConf($this->l('زمان تحویل پیش‌فرض محصولات ناموجود'), 'LABEL_DELIVERY_TIME_OOSBOA');
            $fields_forms['additional_delivery_times']['form']['input'][] = KfaDeliveryTimeUIKit::createSeparator();
            $fields_forms['additional_delivery_times']['form']['input'][] = KfaDeliveryTimeUIKit::createTextLangConf($this->l('قالب زمان تحویل محصولات موجود')
                    , 'LABEL_DELIVERY_TIME_AVAILABLE_FORMAT'
                    , $this->l('از کد {time} در عبارت مورد نظرتان استفاده کنید.'));
            $fields_forms['additional_delivery_times']['form']['input'][] = KfaDeliveryTimeUIKit::createTextLangConf($this->l('قالب زمان تحویل محصولات ناموجود')
                    , 'LABEL_DELIVERY_TIME_OOSBOA_FORMAT'
                    , $this->l('از کد {time} در عبارت مورد نظرتان استفاده کنید.'));
        }
        
        return $fields_forms;
    }
    
    /* FORM DAILY CAPACITY */
    
    protected function getDailyCapacityForm() {
        $fields_forms = array();
        
        $order_checkings = array(
            array(
                'value' => KfaDeliveryTime::ORDER_CHECKING_BY_VALIDITY,
                'label' => $this->l('بر اساس معتبر بودن سفارش'),
            ),
            array(
                'value' => KfaDeliveryTime::ORDER_CHECKING_BY_STATE,
                'label' => $this->l('بر اساس وضعیت سفارش'),
            ),
        );
        
        $fields_forms[] = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('تنظیمات'),
                    'icon' => '',
                ),
                'input' => array(
                    KfaDeliveryTimeUIKit::createSwitchConf($this->l('ظرفیت سنجی مستقل برای هر حامل'), 'ORDER_CHECKING_BY_CARRIER', null, 1),
                    KfaDeliveryTimeUIKit::createRadioConf('ORDER_CHECKING', $this->l('ظرفیت سنجی سفارش‌ها'), $order_checkings),
                    KfaDeliveryTimeUIKit::createCheckBoxConf($this->l('وضعیت‌های سفارش')
                            , 'ORDER_CHECKING_ORDER_STATE'
                            , $this->getOrderStates()
                            , 'ORDER_CHECKING_ORDER_STATE'),
                ),
                'submit' => array(
                    'title' => $this->l('ذخیره'),
                    'class' => 'btn btn-default pull-right'
                ),
            ),
        );
        
        $fields_forms[] = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('ظرفیت تحویل روزانه'),
                    'icon' => '',
                ),
                'warning' => $this->l('ابتدا ظرفیت بازه‌ها سنجیده می‌شود. اگر ظرفیت بازه‌ای هنوز کامل نشده بود، سپس ظرفیت روزانه سنجیده می‌شود.'),
                'description' => $this->l('اگر می‌خواهید یک روز را موقتاً برای تمام حامل‌ها از دسترس خارج کنید، می‌توانید یک عدد منفی (مثلاً -1) برای آن روز وارد کنید.'),
                'input' => array(
                    array(
                        'type' => 'daily_capacity',
                        'name' => $this->conf . 'DAILY_CAPACITY_SAT',
                        'label' => $this->l('شنبه'),
                    ),
                    array(
                        'type' => 'daily_capacity',
                        'name' => $this->conf . 'DAILY_CAPACITY_SUN',
                        'label' => $this->l('یکشنبه'),
                    ),
                    array(
                        'type' => 'daily_capacity',
                        'name' => $this->conf . 'DAILY_CAPACITY_MON',
                        'label' => $this->l('دوشنبه'),
                    ),
                    array(
                        'type' => 'daily_capacity',
                        'name' => $this->conf . 'DAILY_CAPACITY_TUE',
                        'label' => $this->l('سه‌شنبه'),
                    ),
                    array(
                        'type' => 'daily_capacity',
                        'name' => $this->conf . 'DAILY_CAPACITY_WED',
                        'label' => $this->l('چهارشنبه'),
                    ),
                    array(
                        'type' => 'daily_capacity',
                        'name' => $this->conf . 'DAILY_CAPACITY_THU',
                        'label' => $this->l('پنجشنبه'),
                    ),
                    array(
                        'type' => 'daily_capacity',
                        'name' => $this->conf . 'DAILY_CAPACITY_FRI',
                        'label' => $this->l('جمعه'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('ذخیره'),
                    'class' => 'btn btn-default pull-right'
                ),
            ),
        );
        
        $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
        $this->context->smarty->assign(array(
            'currency' => $currency->sign,
        ));
        
        return $fields_forms;
    }
    
    /* WEBSERVICE */
    
    protected function renderWebserviceForm() {
        $password = Tools::getValue($this->conf . 'WEBSERVICE_PASSWORD', Configuration::get($this->conf . 'WEBSERVICE_PASSWORD'));
        $params = array(
            'password' => $password,
            'action' => '',
        );
        $url = $this->context->link->getModuleLink($this->name, 'webservice', $params);
        $fields_values = array(
            $this->conf . 'WEBSERVICE_PASSWORD' => $password,
            $this->conf . 'WEBSERVICE_URL' => $url,
        );
        return $this->renderConfigurationForm($this->getWebserviceForm(), 'submit_webservice', $fields_values);
    }
    
    private function getWebserviceForm() {
        $fields_forms = array();
        
        $fields_forms[] = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('تنظیمات'),
                    'icon' => '',
                ),
                'description' => $this->l('شما می‌توانید از action با مقدار get_by_id_cart همراه با پارامتر id_cart یا از action با مقدار get_by_id_order همراه با پارامتر id_order استفاده کنید.'),
                'input' => array(
                    array(
                        'name' => $this->conf . 'WEBSERVICE_PASSWORD',
                        'type' => 'text',
                        'class' => 'kfa-ltr',
                        'label' => $this->l('رمز وب سرویس'),
                        'desc' => $this->l('اگر رمز وب سرویس را خالی بگذارید، وب سرویس کار نخواهد کرد.'),
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'لینک وب سرویس',
                        'name' => $this->conf . 'WEBSERVICE_URL',
                        'class' => 'kfa-ltr',
                        'readonly' => true,
                        'desc' => $this->l('پارامتر action را با مقدار مورد نظر خود مقداردهی کنید و سایر پارامترهای لازم را به انتهای لینک اضافه کنید.'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('ذخیره'),
                    'class' => 'btn btn-default pull-right'
                ),
            ),
        );
        
        return $fields_forms;
    }
    
    /* FORM VACATIONS & NO DELIVERIES */
    
    protected function renderEventsForm($type) {
        $carrier_programs = array(
            array(
                'value' => KfaDeliveryTime::CARRIER_EVENTS_DEFAULT,
                'label' => $this->l('استفاده از رویدادهای پیش‌فرض'),
            ),
            array(
                'value' => KfaDeliveryTime::CARRIER_EVENTS_EMPTY,
                'label' => $type == KfaDeliveryTimeEvent::ENTITY_OFF ? $this->l('تعطیلی ندارد') : $this->l('روز بدون ارسال ندارد'),
            ),
            array(
                'value' => KfaDeliveryTime::CARRIER_EVENTS_CUSTOM,
                'label' => $this->l('تعریف رویدادهای دلخواه'),
            ),
        );
        
        $carriers = $this->getCarriers();
        $this->context->smarty->assign(array(
            'carriers' => $carriers,
            'carrier_programs' => $carrier_programs,
            'entity' => $type == KfaDeliveryTimeEvent::ENTITY_OFF ? 'off' : 'no_delivery',
            'submit' => $type == KfaDeliveryTimeEvent::ENTITY_OFF ? 'submit_vacations' : 'submit_no_deliveries',
            'events' => KfaDeliveryTimeEvent::getAllEvents($type, $carriers),
        ));
        
        return $this->display(dirname(__FILE__), 'views/templates/admin/events.tpl');
    }
    
    protected function processEvents($type) {
        $entity = $type == KfaDeliveryTimeEvent::ENTITY_OFF ? 'off' : 'no_delivery';
        $carriers = $this->getCarriers();
        foreach ($carriers as $carrier) {
            $id_reference = (int) $carrier['id_reference'];
            KfaDeliveryTimeCarrier::update($id_reference, "program_{$entity}", (int) Tools::getValue("program_{$entity}_{$id_reference}"));
            
            $deleted = Tools::getValue("{$entity}_deleted_{$id_reference}");
            $events = array();
            if (!empty($deleted)) {
                $count = count($deleted);
                for ($i = 0; $i < $count; $i++) {
                    $events[] = array(
                        'y'             => (int) $this->getValueFromArray("{$entity}_y_{$id_reference}", $i),
                        'm'             => (int) $this->getValueFromArray("{$entity}_m_{$id_reference}", $i),
                        'd'             => (int) $this->getValueFromArray("{$entity}_d_{$id_reference}", $i),
                        'deleted'       => $this->getValueFromArray("{$entity}_deleted_{$id_reference}", $i) ? 1 : 0,
                        'description'   => $this->getValueFromArray("{$entity}_description_{$id_reference}", $i),
                    );
                }
            }
            KfaDeliveryTimeEvent::update($id_reference, $type, $events);
        }
        return array();
    }
    
    protected function renderVacationsForm() {
        return $this->renderEventsForm(KfaDeliveryTimeEvent::ENTITY_OFF);
    }
    
    protected function processVacations() {
        return $this->processEvents(KfaDeliveryTimeEvent::ENTITY_OFF);
    }
    
    protected function renderNoDeliveriesForm() {
        return $this->renderEventsForm(KfaDeliveryTimeEvent::ENTITY_NO_DELIVERY);
    }
    
    protected function processNoDeliveries() {
        return $this->processEvents(KfaDeliveryTimeEvent::ENTITY_NO_DELIVERY);
    }
    
    /* FORM RANGES */
    
    private function getCarriers() {
        $carriers = KfaDeliveryTimeCarrier::getCarriers($this->context->language->id, $this->l('پیش‌فرض'));
        foreach ($carriers as $index => $carrier) {
            $carriers[$index]['ids'] = KfaDeliveryTimeCarrier::getListOfIds((int) $carrier['id_reference']);
            if (strpos($carrier['approximate_capacity'], '#') === 0) {
                $carriers[$index]['approximate_capacity'] = substr($carrier['approximate_capacity'], 1);
                $carriers[$index]['approximate_capacity_type'] = '#';
            } else {
                $carriers[$index]['approximate_capacity_type'] = '';
            }
        }
        return $carriers;
    }
    
    protected function renderRangesForm() {
        if (($warning = $this->getShopContextWarning())) {
            return $warning;
        }
        
        $weekday_options = array(
            array(
                'value' => KfaDeliveryTime::PROGRAM_DEFAULT,
                'label' => $this->l('استفاده از بازه‌های زمانی پیش‌فرض'),
            ),
            array(
                'value' => KfaDeliveryTime::PROGRAM_CUSTOM,
                'label' => $this->l('تعریف بازه‌های دلخواه'),
            ),
            array(
                'value' => KfaDeliveryTime::PROGRAM_DEFAULT_OFF,
                'label' => $this->l('استفاده از بازه‌های زمانی روزهای تعطیل'),
            ),
            array(
                'value' => KfaDeliveryTime::PROGRAM_NO_DELIVERY,
                'label' => $this->l('ارسال نداریم'),
            ),
        );
        $off_options = array(
            array(
                'value' => 0,
                'label' => $this->l('ارسال نداریم'),
            ),
            array(
                'value' => 1,
                'label' => $this->l('تعریف بازه‌های دلخواه'),
            ),
        );
        $carrier_programs = array(
            array(
                'value' => KfaDeliveryTime::CARRIER_PROGRAM_DEFAULT,
                'label' => $this->l('پیش‌فرض'),
            ),
            array(
                'value' => KfaDeliveryTime::CARRIER_PROGRAM_UNAVAILABLE,
                'label' => $this->l('بدون نیاز به زمان تحویل'),
            ),
            array(
                'value' => KfaDeliveryTime::CARRIER_PROGRAM_CUSTOM,
                'label' => $this->l('دلخواه'),
            ),
            array(
                'value' => KfaDeliveryTime::CARRIER_PROGRAM_APPROXIMATE,
                'label' => $this->l('زمان تقریبی'),
            ),
        );
        $approximate_add_fields = array(
            'approximate_add_off' => $this->l('روزهای تعطیل'),
            'approximate_add_no_delivery' => $this->l('روزهای بدون ارسال'),
            'approximate_add_sat' => $this->l('شنبه'),
            'approximate_add_sun' => $this->l('یکشنبه'),
            'approximate_add_mon' => $this->l('دوشنبه'),
            'approximate_add_tue' => $this->l('سه‌شنبه'),
            'approximate_add_wed' => $this->l('چهارشنبه'),
            'approximate_add_thu' => $this->l('پنجشنبه'),
            'approximate_add_fri' => $this->l('جمعه'),
        );
        $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
        $this->context->smarty->assign(array(
            'ranges_form_selected_carrier'  => Configuration::get($this->getSelectedCarrierConfName()),
            'approximate_add_fields'        => $approximate_add_fields,
            'carriers'                      => $this->getCarriers(),
            'carrier_programs'              => $carrier_programs,
            'days'                          => $this->getAllDays(),
            'weekday_options'               => $weekday_options,
            'off_options'                   => $off_options,
            'currency'                      => $currency->sign,
            'languages'                     => $this->languages,
            'defaultFormLanguage'           => $this->context->language->id,
        ));
        return $this->display(dirname(__FILE__), 'views/templates/admin/_configure/ranges.tpl');
    }
    
    private function getAllDays() {
        $all_days = KfaDeliveryTimeRangeHelper::getAllDays();
        foreach ($all_days as $id_carrier => $days) {
            foreach ($days as $day => $settings) {
                if (strpos($settings['capacity'], '#') === 0) {
                    $all_days[$id_carrier][$day]['capacity'] = substr($settings['capacity'], 1);
                    $all_days[$id_carrier][$day]['capacity_type'] = '#';
                } else {
                    $all_days[$id_carrier][$day]['capacity_type'] = '';
                }
                
                if (empty($settings['ranges'])) {
                    continue;
                }
                
                foreach ($settings['ranges'] as $index => $range) {
                    if (strpos($range['capacity'], '#') === 0) {
                        $all_days[$id_carrier][$day]['ranges'][$index]['capacity'] = substr($range['capacity'], 1);
                        $all_days[$id_carrier][$day]['ranges'][$index]['capacity_type'] = '#';
                    } else {
                        $all_days[$id_carrier][$day]['ranges'][$index]['capacity_type'] = '';
                    }
                }
            }
        }
        return $all_days;
    }
    
    private function getValueFromArray($key, $index, $default = 0) {
        $value = Tools::getValue($key);
        return is_array($value) && isset($value[$index]) ? $value[$index] : $default;
    }
    
    private function getSelectedCarrierConfName() {
        return $this->conf . 'RANGES_FORM_SELECTED_CARRIER_' . $this->context->employee->id;
    }
    
    protected function processRanges() {
        Configuration::updateValue($this->getSelectedCarrierConfName(), Tools::getValue('ranges_form_selected_carrier'));
        $errors = array();
        $carriers = $this->getCarriers();
        $names = array('default', 'saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'off');
        $fields = array(
            'program'                       => ObjectModel::TYPE_INT,
            'same_day_delivery'             => ObjectModel::TYPE_BOOL,
            'required'                      => ObjectModel::TYPE_BOOL,
            'auto_select'                   => ObjectModel::TYPE_BOOL,
            'scroll_active'                 => ObjectModel::TYPE_BOOL,
            'scroll_selector'               => ObjectModel::TYPE_STRING,
            'scroll_offset'                 => ObjectModel::TYPE_INT,
            'scroll_speed'                  => ObjectModel::TYPE_INT,
            'future_days'                   => ObjectModel::TYPE_INT,
            'future_days_when_no_delivery'  => ObjectModel::TYPE_BOOL,
            'approximate_capacity'          => ObjectModel::TYPE_STRING,
            'approximate_data'              => ObjectModel::TYPE_STRING,
            'approximate_min_day'           => ObjectModel::TYPE_INT,
            'approximate_max_day'           => ObjectModel::TYPE_INT,
            'approximate_format_bo'         => ObjectModel::TYPE_STRING,
            'approximate_format_fo'         => ObjectModel::TYPE_STRING,
            'approximate_add_off'           => ObjectModel::TYPE_BOOL,
            'approximate_add_no_delivery'   => ObjectModel::TYPE_BOOL,
            'approximate_add_sat'           => ObjectModel::TYPE_BOOL,
            'approximate_add_sun'           => ObjectModel::TYPE_BOOL,
            'approximate_add_mon'           => ObjectModel::TYPE_BOOL,
            'approximate_add_tue'           => ObjectModel::TYPE_BOOL,
            'approximate_add_wed'           => ObjectModel::TYPE_BOOL,
            'approximate_add_thu'           => ObjectModel::TYPE_BOOL,
            'approximate_add_fri'           => ObjectModel::TYPE_BOOL,
            'fixed_option_active'           => ObjectModel::TYPE_INT,
            'fixed_option_value'            => ObjectModel::TYPE_STRING,
            'fixed_option_date'             => ObjectModel::TYPE_STRING,
        );
        $lang_fields = array(
            'heading',
        );
        $id_shop = $this->context->shop->id;
        
        foreach ($carriers as $carrier) {
            $id_reference = (int) $carrier['id_reference'];
            
            $values = array();
            foreach ($fields as $field => $type) {
                if ($field == 'approximate_capacity') {
                    $capacity = Tools::getValue("{$field}_{$id_reference}");
                    $capacity_type = $capacity ? Tools::getValue("{$field}_type_{$id_reference}") : '';
                    $values[] = $capacity_type . $capacity;
                    continue;
                }
                
                if ($field == 'approximate_data') {
                    $data = array(
                        'availability_active'   => Tools::getValue("approximate_availability_active_{$id_reference}") ? 1 : 0,
                        'availability_h'        => Tools::getValue("approximate_availability_h_{$id_reference}"),
                        'availability_m'        => Tools::getValue("approximate_availability_m_{$id_reference}"),
                        'availability_interval' => Tools::getValue("approximate_availability_interval_{$id_reference}"),
                        'availability_unit'     => Tools::getValue("approximate_availability_unit_{$id_reference}"),
                    );
                    $values[] = json_encode($data);
                    continue;
                }
                
                switch ($type) {
                    case ObjectModel::TYPE_INT:
                        $values[] = (int) Tools::getValue("{$field}_{$id_reference}");
                        break;
                    case ObjectModel::TYPE_BOOL:
                        $values[] = Tools::getValue("{$field}_{$id_reference}") ? 1 : 0;
                        break;
                    default:
                        $values[] = Tools::getValue("{$field}_{$id_reference}");
                        break;
                }
            }
            KfaDeliveryTimeCarrier::update($id_reference, array_keys($fields), $values);
            if (($db_error = Db::getInstance()->getMsgError())) {
                $errors[] = $db_error;
            }
            
            foreach ($lang_fields as $field) {
                foreach ($this->languages as $language) {
                    $key = "{$field}_{$id_reference}_$language[id_lang]";
                    if (Tools::isSubmit($key)) {
                        KfaDeliveryTimeCarrier::updateLang($id_reference
                                , $id_shop
                                , $language['id_lang']
                                , $field
                                , Tools::getValue($key));
                        if (($db_error = Db::getInstance()->getMsgError())) {
                            $errors[] = $db_error;
                        }
                    }
                }
            }
            
            foreach ($names as $name) {
                $deleted = Tools::getValue("{$name}_deleted_{$id_reference}");
                $ranges = array();
                if (!empty($deleted)) {
                    $count = count($deleted);
                    for ($i = 0; $i < $count; $i++) {
                        $data = array(
                            'availability_active'   => $this->getValueFromArray("{$name}_availability_active_{$id_reference}", $i) ? 1 : 0,
                            'availability_h'        => $this->getValueFromArray("{$name}_availability_h_{$id_reference}", $i),
                            'availability_m'        => $this->getValueFromArray("{$name}_availability_m_{$id_reference}", $i),
                            'availability_interval' => $this->getValueFromArray("{$name}_availability_interval_{$id_reference}", $i),
                            'availability_unit'     => $this->getValueFromArray("{$name}_availability_unit_{$id_reference}", $i),
                        );
                        
                        $capacity = $this->getValueFromArray("{$name}_capacity_{$id_reference}", $i, '');
                        $capacity_type = $capacity ? $this->getValueFromArray("{$name}_capacity_type_{$id_reference}", $i, '') : '';
                        $ranges[] = array(
                            'id_reference'  => $id_reference,
                            'id_shop'       => $id_shop,
                            'name'          => $name,
                            'from_h'        => (int) $this->getValueFromArray("{$name}_from_h_{$id_reference}", $i),
                            'from_m'        => (int) $this->getValueFromArray("{$name}_from_m_{$id_reference}", $i),
                            'to_h'          => (int) $this->getValueFromArray("{$name}_to_h_{$id_reference}", $i),
                            'to_m'          => (int) $this->getValueFromArray("{$name}_to_m_{$id_reference}", $i),
                            'capacity'      => $capacity_type . $capacity,
                            'deleted'       => $this->getValueFromArray("{$name}_deleted_{$id_reference}", $i) ? 1 : 0,
                            'disabled'      => $this->getValueFromArray("{$name}_disabled_{$id_reference}", $i) ? 1 : 0,
                            'description'   => $this->getValueFromArray("{$name}_description_{$id_reference}", $i, ''),
                            'data'          => json_encode($data),
                        );
                    }
                }
                
                $capacity = Tools::getValue("{$name}_default_capacity_{$id_reference}");
                $capacity_type = $capacity ? Tools::getValue("{$name}_default_capacity_type_{$id_reference}") : '';
                $day = array(
                    'id_reference'  => $id_reference,
                    'id_shop'       => $id_shop,
                    'name'          => $name,
                    'program'       => (int) Tools::getValue("{$name}_program_{$id_reference}"),
                    'same'          => Tools::getValue("{$name}_same_{$id_reference}") ? 1 : 0,
                    'capacity'      => $capacity_type . $capacity,
                );
                KfaDeliveryTimeRangeHelper::update($day, $ranges, $id_reference, $id_shop);
                if (($db_error = Db::getInstance()->getMsgError())) {
                    $errors[] = $db_error;
                }
            }
        }
        
        return $errors;
    }
    
    /* CUSTOM CODES */
    
    protected function renderCustomCodesForm() {
         $fields_value = array(
            $this->conf . 'CSS' => Tools::isSubmit($this->conf . 'CSS') ? Tools::getValue($this->conf . 'CSS') : $this->readCustomCode('css'),
            $this->conf . 'JS' => Tools::isSubmit($this->conf . 'JS') ? Tools::getValue($this->conf . 'JS') : $this->readCustomCode('js'),
        );
        return $this->renderConfigurationForm($this->getCustomCodesForm(), 'submit_custom_codes', $fields_value);
    }
    
    private function getCustomCodesForm() {
        $fields_form_1 = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('کدهای سفارشی'),
                    'icon' => ''
                ),
                'warning' => $this->l('در وارد کردن کدهای JS دقت کنید. اگر کدهای JS دارای خطا باشند، صفحه‌ی محصول دچار مشکل می‌شود.'),
                'input' => array(
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('کدهای CSS'),
                        'name' => $this->conf . 'CSS',
                        'class' => 'kfa-ltr kfa-custom-code',
                        'rows' => 20,
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('کدهای JS'),
                        'name' => $this->conf . 'JS',
                        'class' => 'kfa-ltr kfa-custom-code',
                        'rows' => 20,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('ذخیره'),
                )
            ),
        );

        return array($fields_form_1);
    }
    
    protected function processCustomCodes() {
        $errors = array();
        if (!$this->writeCustomCode('css', $_POST[$this->conf . 'CSS']) || !$this->writeCustomCode('js', $_POST[$this->conf . 'JS'])) {
            $errors[] = $this->l('خطایی در ذخیره کردن کدهای سفارشی پیش آمد.');
        }
        return $errors;
    }

    private function readCustomCode($type) {
        $filename = $this->getCustomCodePath($type);
        if (file_exists($filename) && filesize($filename) > 0) {
            $handle = fopen($filename, 'r');
            $code = fread($handle, filesize($filename));
            fclose($handle);
            return $code;
        }
        return '';
    }
    
    private function getCustomCodePath($type) {
        if ($type == 'css') {
            return $this->getLocalPath() . 'views/css/custom.css';
        } elseif ($type == 'js') {
            return $this->getLocalPath() . 'views/js/custom.js';
        }
    }

    private function writeCustomCode($type, $code) {
        $clean_code = trim($code);
        $filename = $this->getCustomCodePath($type);
        if (empty($clean_code)) {
            if (file_exists($filename) && is_file($filename)) {
                $result = unlink($filename);
            } else {
                $result = true;
            }
        } else {
            $handle = fopen($filename, 'w');
            $result = fwrite($handle, $clean_code) !== false;
            fclose($handle);
        }
        return $result;
    }
    
    private function addCustomCodes() {
        $css_filename = $this->getCustomCodePath('css');
        if (file_exists($css_filename) && is_file($css_filename) && filesize($css_filename) > 0) {
            $this->context->controller->addCSS($css_filename, 'all');
        }
        
        $js_filename = $this->getCustomCodePath('js');
        if (file_exists($js_filename) && is_file($js_filename) && filesize($js_filename) > 0) {
            $this->context->controller->addJS($js_filename);
        }
    }
    
    /* HOOKS */
    
    public static function printAdminOrderDeliveryTime($echo, $row) {
        if ($echo) {
            // Suppress netbeans warning
        }
        
        $approximate = false;
        $selected_option = KfaDeliveryTimeCart::getOptionForAdminOrdersListing($row['id_cart'], $approximate);
        if (!$selected_option) {
            return '-';
        }
        
        if ($approximate) {
            if (isset($row['id_carrier'])) {
                $id_carrier = $row['id_carrier'];
            } else {
                $id_carrier = KfaDeliveryTimeCarrier::getIdCarrierByIdOrder($row['id_order']);
            }
            
            if (isset($row['id_reference'])) {
                $id_reference = $row['id_reference'];
            } else {
                $id_reference = KfaDeliveryTimeCarrier::getCarrierReference($id_carrier);
            }
            
            if (isset($row['date_process']) && isset($row['date_start']) && isset($row['date_end'])) {
                $date = array(
                    'process' => $row['date_process'],
                    'from' => $row['date_start'],
                    'to' => $row['date_end'],
                );
            } else {
                $delivery_time = KfaDeliveryTimeCart::getByIdCart($row['id_cart']);
                $date = array(
                    'process' => $delivery_time['date_process'],
                    'from' => $delivery_time['date_start'],
                    'to' => $delivery_time['date_end'],
                );
            }
            
            $data = KfaDeliveryTimeCarrier::getCarrierApproximateData($id_reference);
            $format = $data['approximate_format_bo'];
            
            $string = KfaDeliveryTimeFormatHelper::formatDualDate($date, $format);
        } else {
            $string = KfaDeliveryTimeFormatHelper::formatOption($selected_option, 'KFADELIVERYTIME_' . 'HOOK_ADMIN_ORDERS_LIST_FORMAT');
        }
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
    
    private function getAdminOrdersDateFilters() {
        if (empty($this->context->cookie->ordersorderFilter_kfadeliverytime_date_start)) {
            return false;
        }
        
        $filter = $this->context->cookie->ordersorderFilter_kfadeliverytime_date_start;
        if (strpos($filter, '[') === 0) {
            $filters = json_decode($filter, true);
        } else {
            $filters = unserialize($filter);
        }
        return $filters;
    }
    
    private function addDeliveryTimeToOrdersListing($param) {
        if (isset($param['fields']['kfadeliverytime_date_start'])) {
            return;
        }
        
        $prefix = _DB_PREFIX_;
        $param['select'] .= ', a.`id_cart`, IFNULL(kfadeliverytime_cart.`date_process`, kfadeliverytime_cart.`date_start`) AS `kfadeliverytime_date_start`';
        $param['join'] .= "\n\t\tLEFT JOIN `{$prefix}kfadeliverytime_cart` kfadeliverytime_cart ON (kfadeliverytime_cart.`id_cart` = a.`id_cart`)";
        $param['fields']['kfadeliverytime_date_start'] = array(
            'callback' => 'printAdminOrderDeliveryTime',
            'callback_object' => 'KfaDeliveryTime',
            'title' => $this->l('زمان تحویل'),
            'align' => 'text-right',
            'type' => 'datetime',
        );
        
        $filters = $this->getAdminOrdersDateFilters();
        if ($filters !== false) {
            if ($this->context->language->iso_code == 'fa') {
                if ($filters[0]) {
                    $start = KfaPersianDate::isSolarDate($filters[0]) ? KfaPersianDate::stoG($filters[0])->format('Y-m-d 00:00:00') : date('Y-m-d 00:00:00', strtotime($filters[0]));
                } else {
                    $start = false;
                }
                if ($filters[1]) {
                    $end = KfaPersianDate::isSolarDate($filters[1]) ? KfaPersianDate::stoG($filters[1])->format('Y-m-d 23:59:59') : date('Y-m-d 23:59:59', strtotime($filters[1]));
                } else {
                    $end = false;
                }
            } else {
                $start = $filters[0];
                $end = $filters[1];
            }
            
            if (!empty($end) && !empty($start)) {
                $param['where'] .= " AND kfadeliverytime_cart.`date_start` BETWEEN '$start' AND '$end' ";
            } elseif (!empty($start)) {
                $param['where'] .= " AND kfadeliverytime_cart.`date_start` >= '$start' ";
            } elseif (!empty($end)) {
                $param['where'] .= " AND kfadeliverytime_cart.`date_start` <= '$end' ";
            }
        }
    }
    
    private function addCarrierNameToOrdersListing($param) {
        if (isset($param['fields']['carrier_name'])) {
            return;
        }
        
        $prefix = _DB_PREFIX_;
        $param['select'] .= ', a.`id_carrier`, carrier.`name` AS carrier_name';
        $param['join'] .= "\n\t\tLEFT JOIN `{$prefix}carrier` carrier USING(`id_carrier`)";
        $param['fields']['carrier_name'] = array(
            'title' => $this->l('حامل'),
            'align' => 'text-right',
            'type' => 'text',
        );
        
        if (!empty($this->context->cookie->ordersorderFilter_carrier_name)) {
            $carrier_name = $this->context->cookie->ordersorderFilter_carrier_name;
            $param['where'] .= " AND carrier.`name` = '$carrier_name' ";
        }
    }
    
    public function hookActionAdminOrdersListingFieldsModifier($param) {
        // Before PS 1.7.6
        if (!$this->isActive() || !isset($param['select'])) {
            return;
        }
        
        if ($this->getConf('HOOK_ADMIN_ORDERS_LIST')) {
            $this->addDeliveryTimeToOrdersListing($param);
        }
        
        if ($this->getConf('ADD_CARRIER_NAME_TO_ORDERS_LISTING')) {
            $this->addCarrierNameToOrdersListing($param);
        }
    }
    
    public function hookActionOrderGridDefinitionModifier($params) {
       
        // Since PS 1.7.6
        if (!$this->isActive()) {
            return;
        }
        
        if (is_null($this->orderListingInstance)) {
            require_once $this->getLocalPath() . 'classes/KfaDeliveryTimeOrderListing.php';
            $this->orderListingInstance = new KfaDeliveryTimeOrderListing();
        }
        
        $this->orderListingInstance->hookActionOrderGridDefinitionModifier($params
                , $this->getConf('HOOK_ADMIN_ORDERS_LIST')
                , $this->getConf('ADD_CARRIER_NAME_TO_ORDERS_LISTING'));
    }

    public function hookActionOrderGridQueryBuilderModifier($params) {
        // Since PS 1.7.6
        if (!$this->isActive()) {
            return;
        }
        
        if (is_null($this->orderListingInstance)) {
            require_once $this->getLocalPath() . 'classes/KfaDeliveryTimeOrderListing.php';
            $this->orderListingInstance = new KfaDeliveryTimeOrderListing();
        }
        
        $this->orderListingInstance->hookActionOrderGridQueryBuilderModifier($params
                , $this->getConf('HOOK_ADMIN_ORDERS_LIST')
                , $this->getConf('ADD_CARRIER_NAME_TO_ORDERS_LISTING'));
    }
    
    public function hookActionOrderStatusPostUpdate($params) {
        // Since version 1.27
        $id_order   = (int) $params['id_order'];
        $order      = new Order($id_order);
        $cart       = new Cart($order->id_cart);
        $id_carrier = $this->detectIdCarrier($cart);
        $program    = KfaDeliveryTimeCarrier::getCarrierProgram($id_carrier);
        KfaDeliveryTime::logCalculations(__CLASS__ . ' > ' . __FUNCTION__ . " > called for order $id_order, cart $cart->id, carrier $id_carrier, program $program");
        if ($program === KfaDeliveryTime::CARRIER_PROGRAM_UNAVAILABLE) {
            KfaDeliveryTime::logCalculations(__CLASS__ . ' > ' . __FUNCTION__ . " > Begin deleting option for order $id_order with cart $cart->id, because carrier $id_carrier program is `$program`.");
            KfaDeliveryTimeCart::deleteOption($cart->id);
            KfaDeliveryTime::logCalculations(__CLASS__ . ' > ' . __FUNCTION__ . " > End deleting option for order $id_order with cart $cart->id.");
        }
    }
    
    public function hookActionEmailAddAfterContent($param) {
        if (!$this->isActive() || empty($param['cart']) || !Validate::isLoadedObject($param['cart']) || !$this->getConf('HOOK_EMAIL')) {
            return;
        }
        
        $selected_option = KfaDeliveryTimeCart::getOption($param['cart']->id);
        $key = $this->findAlternativeKey($selected_option, 'HOOK_EMAIL_FORMAT');
        
        if (!empty($param['template_html'])) {
            $replace = $selected_option ? $this->formatOption($selected_option, $key) : '';
            $param['template_html'] = str_replace('{delivery_time}', $replace, $param['template_html']);
        }
        
        if (!empty($param['template_text'])) {
            $replace = $selected_option ? $this->formatOption($selected_option, $key) : '';
            $param['template_text'] = str_replace('{delivery_time}', $replace, $param['template_text']);
        }
    }
    
    private function formatDate($date, $key, $format = false) {
        if ($key) {
            $key = $this->conf . $key;
        }
        return KfaDeliveryTimeFormatHelper::formatDate($date, $key, $format);
    }
    
    // Used in appsys3
    public function formatOption($option, $key, $format = false) {
        if ($key) {
            $key = $this->conf . $key;
        }
        return KfaDeliveryTimeFormatHelper::formatOption($option, $key, $format);
    }
    
    private function isApproximateCarrier($id_carrier, $id_shop) {
        $program = KfaDeliveryTimeCarrier::getCarrierProgram($id_carrier, $id_shop);
        return $program == KfaDeliveryTime::CARRIER_PROGRAM_APPROXIMATE;
    }
    
    private function getAdminOrderTabHTML($id_cart, $template) {
        if (!$this->isActive() || !$this->getConf('HOOK_ADMIN_ORDER_DETAIL')) {
            return null;
        }
        
        $selected_option = KfaDeliveryTimeCart::getOption($id_cart);
        if (!$selected_option && !$this->showDeliveryTimeForAllOrders) {
            return null;
        }
        
        return $this->display(dirname(__FILE__), "views/templates/hook/$template");
    }
    
    private function getAdminOrderTabContentHTML($order, $template, $return = false) {
        if (!$this->isActive() || !$this->getConf('HOOK_ADMIN_ORDER_DETAIL')) {
            return null;
        }
        
        $id_cart = $order->id_cart;
        $selected_option = KfaDeliveryTimeCart::getOption($id_cart);
        if (!$selected_option && !$this->showDeliveryTimeForAllOrders) {
            return null;
        }
        
        $order_date = date('Y-m-d', strtotime($order->date_add));
        if ($this->isApproximateCarrier($order->id_carrier, $order->id_shop)) {
            $i = 0;
            $now = date('Y-m-d');
            $start_date = array();
            $max_i = date_diff(date_create($order_date), date_create($now), true)->days;
            do {
                $start_date[] = date('Y-m-d', strtotime("$order_date +$i day"));
                $i++;
            } while ($i <= $max_i);
            
            $row = KfaDeliveryTimeCart::getByIdCart($id_cart);
            if (!empty($row['date_process'])) {
                $date_process = str_replace(' 00:00:00', '', $row['date_process']);
            }
        } else {
            $start_date = $order_date;
        }
        $data = $this->getAdminDeliveryTimes($id_cart, $start_date, false, false, 10);
        $array = array(
            'kfadeliverytime_data' => $data,
            'kfadeliverytime_date_process' => isset($date_process) ? $date_process : null,
            'kfadeliverytime_id_cart' => $id_cart,
            'kfadeliverytime_history' => $this->createHistory($id_cart),
            'kfadeliverytime_selected_option_value' => $selected_option,
            'kfadeliverytime_selected_option_display' => $selected_option ? $this->formatOption($selected_option, 'HOOK_ADMIN_ORDER_DETAIL_FORMAT') : 'انتخاب نشده است.',
        );
        
        if ($return) {
            return $array;
        }
        
        $this->context->smarty->assign($array);
        return $this->display(dirname(__FILE__), "views/templates/hook/$template");
    }
    
    public function hookDisplayAdminOrderTabShip($param) {
        // Before PS 1.7.7
        return $this->getAdminOrderTabHTML($param['order']->id_cart, 'admin_order_tab_ship.tpl');
    }
    
    public function hookDisplayAdminOrderContentShip($param) {
        // Before PS 1.7.7
        return $this->getAdminOrderTabContentHTML($param['order'], 'admin_order_content_ship.tpl', !empty($param['return']));
    }
    
    public function hookDisplayAdminOrderTabLink($params) {
        // Since PS 1.7.7
        $order = new Order($params['id_order']);
        return $this->getAdminOrderTabHTML($order->id_cart, 'admin_order_tab_link.tpl');
    }
    
    public function hookDisplayAdminOrderTabContent($param) {
        // Since PS 1.7.7
        $order = new Order($param['id_order']);
        return $this->getAdminOrderTabContentHTML($order, 'admin_order_tab_content.tpl');
    }
    
    private function isInProductPage() {
        return Tools::getValue('controller') == 'product' && Tools::getValue('id_product') && $this->getConf('DISPLAY_PRODUCT_DELIVERY_TIME');
    }
    
    private function isInCart() {
        $page = isset($this->context->controller->php_self) ? $this->context->controller->php_self : '';
        return in_array($page, array('order', 'order-opc', 'history'));
    }
    
    private function isInModule($module_name) {
        if (Tools::getValue('fc') != 'module') {
            return false;
        }
        
        $a = Module::isInstalled($module_name) && Module::isEnabled($module_name);
        $b = Tools::getValue('module') == $module_name;
        $c = Tools::getValue('module') == $this->name && Tools::getValue('ajax');
        return $a && ($b || $c);
    }
    
    private function isInPrestacart() {
        return $this->isInModule('psf_prestacart');
    }
    
    private function isInSteasycheckout() {
        return $this->isInModule('steasycheckout');
    }
    
    private function isInOrderRelatedPages() {
        if (empty($this->context->controller->php_self)) {
            return false;
        }
        
        $pages = array(
            'order-confirmation',
            'order-detail',
        );
        return in_array($this->context->controller->php_self, $pages);
    }
    
    /**
     * پابلیک به دلیل استفاده در ماژول اپلیکیشن
     * @return bool
     */
    public function isGroupingEnabled() {
        if (Tools::getValue('fc') == 'module' && Tools::getValue('module') == 'appsys3') {
            return $this->getConf('GROUPING_MOBILE');
        }
        
        $mobile_detect = Context::getContext()->getMobileDetect();
        if ($mobile_detect->isMobile() || $mobile_detect->isTablet()) {
            return $this->getConf('GROUPING_MOBILE');
        } else {
            return $this->getConf('GROUPING_DESKTOP');
        }
    }
    
    private static function getAttributesResume($id_shop, $id_product) {
        $prefix = _DB_PREFIX_;
        $sql = "
            SELECT id_product_attribute, default_on 
            FROM `{$prefix}product_attribute_shop` 
            WHERE `id_shop` = $id_shop AND `id_product` = $id_product
        ";
        return Db::getInstance()->executeS($sql);
    }
    
    private function processProductHeader() {
        $id_shop = $this->context->shop->id;
        $id_lang = $this->context->language->id;
        $id_product = Tools::getValue('id_product');
        if (empty($product = KfaDeliveryTimeAdditionalDeliveryTimes::getProduct($this->is17, $id_shop, $id_lang, $id_product))) {
            return;
        }
        
        $option = $product['additional_delivery_times'];
        $use_default_information = 1;
        $use_product_information = 2;
        if ($option == $use_default_information) {
            $labels = KfaDeliveryTimeAdditionalDeliveryTimes::getDefaultLabels($this, $id_lang);
        } elseif ($option == $use_product_information) {
            $labels = array(
                'in' => $product['delivery_in_stock'],
                'out' => $product['delivery_out_stock'],
            );
        } else {
            return;
        }
        
        if (!empty($format = $this->getConf('LABEL_DELIVERY_TIME_AVAILABLE_FORMAT', $id_lang)) && !empty($labels['in'])) {
            $labels['in'] = str_replace('{time}', $labels['in'], $format);
        }
        if (!empty($format = $this->getConf('LABEL_DELIVERY_TIME_OOSBOA_FORMAT', $id_lang)) && !empty($labels['out'])) {
            $labels['out'] = str_replace('{time}', $labels['out'], $format);
        }
        
        $data = array();
        $attributes_resume = self::getAttributesResume($id_shop, $id_product);
        if (empty($attributes_resume)) {
            $attributes_resume = array(
                array('id_product_attribute' => 0),
            );
        }
        foreach ($attributes_resume as $attribute_resume) {
            $id_product_attribute = $attribute_resume['id_product_attribute'];
            $quantity = StockAvailable::getQuantityAvailableByProduct($id_product, $id_product_attribute, $id_shop);
            $allow_oosp = Product::isAvailableWhenOutOfStock(StockAvailable::outOfStock($id_product));
            if ($quantity > 0 || $allow_oosp) {
                if (!empty($labels['in'])) {
                    $data[$id_product_attribute] = array(
                        'stock_class' => 'in',
                        'label' => $labels['in'],
                    );
                }
            } else {
                if (!empty($labels['out'])) {
                    $data[$id_product_attribute] = array(
                        'stock_class' => 'out',
                        'label' => $labels['out'],
                    );
                }
            }
        }
        Media::addJsDef(array(
            'KfaDeliveryTimeData' => $data,
        ));
        
        $this->addMediaWithVersion($this->getPathUri() . 'views/js/product.js');
    }
    
    private function processCartHeader() {
        $this->addMediaWithVersion($this->getPathUri() . 'views/css/kfadeliverytime_fontello-embedded.css');
        $this->addMediaWithVersion($this->getPathUri() . 'views/css/content_reload.css');
        $this->addMediaWithVersion($this->getPathUri() . 'views/css/front.css');
        $this->addMediaWithVersion($this->getPathUri() . 'views/js/front.js');
        
        if ($this->isGroupingEnabled()) {
            $this->addMediaWithVersion($this->getPathUri() . 'views/css/content_grouped.css');
        } else {
            $this->addMediaWithVersion($this->getPathUri() . 'views/css/content.css');
        }
    }
    
    public function hookDisplayHeader() {
        $in_product = $this->isInProductPage();
        $in_cart = $this->isInCart() || $this->isInPrestacart() || $this->isInSteasycheckout();
        $in_order_related_pages = $this->isInOrderRelatedPages();
        if (!($in_cart || $in_product || $in_order_related_pages)) {
            return;
        }
        
        $this->addMediaWithVersion($this->getPathUri() . 'views/css/kfadeliverytime_fontello-embedded.css');
        $this->addCustomCodes();
        
        if ($in_product) {
            $this->processProductHeader();
        } elseif ($in_cart) {
            $this->processCartHeader();
        }
    }
    
    public function hookDisplayBackOfficeHeader() {
        if (!$this->isActive()) {
            return;
        }
        
        if ($this->context->controller->controller_name != 'AdminOrders') {
            return;
        }
        
        $this->context->controller->addJquery();
        $this->addMediaWithVersion($this->getPathUri() . 'views/js/admin-orders.js');
        $this->addMediaWithVersion($this->getPathUri() . 'views/css/admin-orders.css');
        Media::addJsDefL('kfadeliverytimeUrl', $this->context->link->getAdminLink('AdminModules') . "&configure=$this->name");
        
        if (Module::isInstalled('psf_prestaplus') && Module::isEnabled('psf_prestaplus') && Configuration::get('PSFPLUS_JALALI_DATE')) {
            $filters = $this->getAdminOrdersDateFilters();
            if (is_array($filters) && count($filters) === 2) {
                $date0_parts = explode('-', KfaPersianDate::gToS($filters[0], false, false));
                $date1_parts = explode('-', KfaPersianDate::gToS($filters[1], false, false));
                if (is_array($date0_parts) && count($date0_parts) === 3 && is_array($date1_parts) && count($date1_parts) === 3) {
                    $date0 = "$date0_parts[2]/$date0_parts[1]/$date0_parts[0]";
                    $date1 = "$date1_parts[2]/$date1_parts[1]/$date1_parts[0]";
                    Media::addJsDefL('kfadeliverytimeDateStart0', $date0);
                    Media::addJsDefL('kfadeliverytimeDateStart1', $date1);
                }
            }
        }
    }
    
    // Used in appsys3
    public function getDeliveryOptions(Cart $cart) {
        if (!isset($this->deliveryOptionsCache[$cart->id])) {
            $this->deliveryOptionsCache[$cart->id] = $cart->getDeliveryOptionList();
        }
        $delivery_option_list = $this->deliveryOptionsCache[$cart->id];
        
        $carriers_available = array();
        if (isset($delivery_option_list[$cart->id_address_delivery])) {
            foreach ($delivery_option_list[$cart->id_address_delivery] as $carriers_list) {
                foreach ($carriers_list as $carriers) {
                    if (is_array($carriers)) {
                        foreach ($carriers as $carrier) {
                            $carriers_available[] = $carrier['instance']->id;
                        }
                    }
                }
            }
        }
        return $carriers_available;
    }
    
    public function detectIdCarrier($cart) {
        if (($id_carrier_from_order = KfaDeliveryTimeCart::getCarrierFromOrder($cart->id))) {
            return $id_carrier_from_order;
        }
        
        $available_carriers = $this->getDeliveryOptions($cart);
        if ($cart->id_carrier && in_array($cart->id_carrier, $available_carriers)) {
            return (int) $cart->id_carrier;
        } else {
            return count($available_carriers) ? $available_carriers[0] : 0;
        }
    }
    
    private function getDeliveryMessage($delivery_time) {
        $date = array(
            'process' => $delivery_time['process'],
            'from' => $delivery_time['from'],
            'to' => $delivery_time['to'],
        );
        $format = $delivery_time['format'];
        return KfaDeliveryTimeFormatHelper::formatDualDate($date, $format);
    }
    
    // Used in appsys3
    public function getDeliveryTimes($cart = null, $id_carrier_force = false, $start_date = false, $disabled_days = null, $is_admin = false, $future_days_count = false) {
        if (is_null($cart)) {
            $cart = $this->context->cart;
        }
        
        if (Validate::isLoadedObject($cart) && $cart->isVirtualCart()) {
            return null;
        }
        
        if (!$start_date) {
            $start_date = date('Y-m-d');
        }
        
        $id_carrier = $id_carrier_force === false ? $this->detectIdCarrier($cart) : $id_carrier_force;
        $range_helper = new KfaDeliveryTimeRangeHelper($this);
        $delivery_times = $range_helper->getDeliveryTimes($cart, $id_carrier, $start_date, $disabled_days, $is_admin, $future_days_count);
        if (empty($delivery_times)) {
            return null;
        }
        
        if (!empty($delivery_times['approximate'])) {
            if (isset($delivery_times['multiple_date'])) {
                foreach ($delivery_times['multiple_date'] as $date => $delivery_time) {
                    $delivery_times['multiple_date'][$date]['value'] = $this->getDeliveryMessage($delivery_time);
                    $delivery_times['multiple_date'][$date]['origin_display'] = KfaDeliveryTimeFormatHelper::displayDate($date, true);
                    $delivery_times['multiple_date'][$date]['origin_weekday'] = KfaDeliveryTimeRangeHelper::persianWeekday($date);
                }
            } else {
                $delivery_times['value'] = $this->getDeliveryMessage($delivery_times);
            }
            return $delivery_times;
        }
        
        $separator = $this->getConf('TIME_SEPARATOR', $this->context->language->id);
        if (empty($separator)) {
            $separator = ' - ';
        }
        
        $description_unavailable = $this->getConf('UNAVAILABLE_DESCRIPTION', $this->context->language->id);
        $description_out_of_capacity = $this->getConf('OUT_OF_CAPACITY_DESCRIPTION', $this->context->language->id);
        $description_disabled = $this->getConf('DISABLED_OPTION_DESCRIPTION', $this->context->language->id);
        $description_normal = $this->getConf('NORMAL_OPTION_DESCRIPTION', $this->context->language->id);
        $show_description = $this->getConf('SHOW_DESCRIPTION');
        
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime("$today + 1 days"));
        $today_text = $this->formatDate($today, 'TODAY_TEXT');
        $tomorrow_text = $this->formatDate($tomorrow, 'TOMORROW_TEXT');
        $selected_option = KfaDeliveryTimeCart::getOption($cart->id);
        foreach ($delivery_times as $index => $delivery_time) {
            if (!empty($delivery_time['fixed'])) {
                $delivery_times[$index]['date_display'] = '';
                $delivery_times[$index]['time_display'] = $delivery_time['value'];
                $delivery_times[$index]['selected'] = $delivery_time['value'] == $selected_option ? 1 : 0;
                continue;
            }
            
            $value = $delivery_time['date_display'] . ' ' . $delivery_time['start'] . '-' . $delivery_time['end'];
            
            if ($delivery_time['date'] == $today) {
                $delivery_times[$index]['date_display'] = $today_text;
            } elseif ($delivery_time['date'] == $tomorrow) {
                $delivery_times[$index]['date_display'] = $tomorrow_text;
            } else {
                $delivery_times[$index]['date_display'] = $this->formatDate($delivery_time['date'], 'NEXT_DAYS_TEXT');
            }
            $delivery_times[$index]['time_display'] = $delivery_time['start'] . $separator . $delivery_time['end'];
            $delivery_times[$index]['value'] = $value;
            $delivery_times[$index]['selected'] = (!$delivery_time['disabled'] && $value == $selected_option) ? 1 : 0;
            $delivery_times[$index]['fixed'] = 0;
            
            if ($delivery_time['start'] == '00:00' && $delivery_time['end'] == '23:59' && $this->getConf('HIDE_LONG_TIMES')) {
                $delivery_times[$index]['hide_time'] = 1;
                $delivery_times[$index]['time_display'] = '';
            } else {
                $delivery_times[$index]['hide_time'] = 0;
            }
            
            $description = $delivery_times[$index]['description'];
            switch ($show_description) {
                case KfaDeliveryTime::SHOW_DESCRIPTION_NEVER:
                    $delivery_times[$index]['description'] = null;
                    break;
                case KfaDeliveryTime::SHOW_DESCRIPTION_ALWAYS:
                    if ($description) {
                        $delivery_times[$index]['description'] = $description;
                    } elseif ($delivery_time['out_of_capacity']) {
                        $delivery_times[$index]['description'] = $description_out_of_capacity;
                    } elseif ($delivery_time['unavailable']) {
                        $delivery_times[$index]['description'] = $description_unavailable;
                    } elseif ($delivery_time['disabled']) {
                        $delivery_times[$index]['description'] = $description_disabled;
                    } else {
                        $delivery_times[$index]['description'] = $description_normal;
                    }
                    break;
                default:
                    if ($description) {
                        $delivery_times[$index]['description'] = $description;
                    } elseif ($delivery_time['out_of_capacity']) {
                        $delivery_times[$index]['description'] = $description_out_of_capacity;
                    } elseif ($delivery_time['unavailable']) {
                        $delivery_times[$index]['description'] = $description_unavailable;
                    } elseif ($delivery_time['disabled']) {
                        $delivery_times[$index]['description'] = $description_disabled;
                    } else {
                        $delivery_times[$index]['description'] = null;
                    }
                    break;
            }
        }
        return $delivery_times;
    }
    
    /**
     * Used in appsys3
     * @param null|array $delivery_times
     * @return array
     */
    public function getSelectedOptionErrors($delivery_times = null, $auto_delete_selected_option_on_error = false) {
        if (is_null($delivery_times)) {
            $delivery_times = $this->getDeliveryTimes($this->context->cart);
        }
        
        // Approximate delivery time
        if (!empty($delivery_times['approximate'])) {
            return array();
        }
        
        $id_lang = $this->context->language->id;
        $id_cart = $this->context->cart->id;
        
        $errors = array();
        $exists = false;
        if (KfaDeliveryTimeCart::exists($id_cart)) {
            $exists = true;
            $selected_option = KfaDeliveryTimeCart::getOption($id_cart);
            $found = false;
            if ($delivery_times) {
                foreach ($delivery_times as $delivery_time) {
                    if ($delivery_time['value'] == $selected_option) {
                        if ($delivery_time['disabled']) {
                            if ($auto_delete_selected_option_on_error && KfaDeliveryTimeCart::deleteOption($id_cart)) {
                                // حذف خودکار انتخابی که منقضی شده
                                $exists = false;
                            } else {
                                $errors[] = $this->getConf('SELECTION_EXPIRED', $id_lang);
                            }
                        }
                        $found = true;
                        break;
                    }
                }
            }
            if (!$found && $this->isDeliveryTimeRequired()) {
                if ($auto_delete_selected_option_on_error && KfaDeliveryTimeCart::deleteOption($id_cart)) {
                    // حذف خودکار انتخابی که دیگر وجود ندارد
                    $exists = false;
                } else {
                    $errors[] = $this->getConf('SELECTION_NOT_AVAILABLE', $id_lang);
                }
            }
        }
        
        if (!$exists && $this->isDeliveryTimeRequired()) {
            $errors[] = $this->getConf('SELECTION_NOT_DONE', $id_lang);
        }
        return $errors;
    }
    
    /**
     * Used in appsys3
     * @param array $delivery_times
     * @param int $id_cart
     * @param boolean $called_from_appsys3
     */
    public function autoSelectFirstOption(&$delivery_times, $id_cart, $called_from_appsys3) {
        if (empty($delivery_times)) {
            return;
        }
        
        if (!empty($delivery_times['approximate'])) {
            return;
        }
        
        $cart = new Cart($id_cart);
        $id_carrier = $this->detectIdCarrier($cart);
        $id_reference = KfaDeliveryTimeCarrier::getCarrierReference($id_carrier);
        $auto_select = KfaDeliveryTimeCarrier::getCarrierAutoSelect($id_reference);
        if (!$auto_select) {
            return;
        }
        
        $any_option_selected = false;
        $index_of_first_available_option = -1;
        foreach ($delivery_times as $index => $delivery_time) {
            if (!empty($delivery_time['selected'])) {
                $any_option_selected = true;
                break;
            }
            
            if ($index_of_first_available_option == -1) {
                $is_available = empty($delivery_time['disabled']) && empty($delivery_time['unavailable']) && empty($delivery_time['out_of_capacity']);
                if ($is_available) {
                    $index_of_first_available_option = $index;
                }
            }
        }
        if ($any_option_selected) {
            return;
        }
        
        if ($index_of_first_available_option > -1) {
            $selected = $called_from_appsys3 || KfaDeliveryTimeCart::selectOption($id_cart
                        , $delivery_times[$index_of_first_available_option]['value']
                        , null
                        , $delivery_times[$index_of_first_available_option]['date_start']
                        , $delivery_times[$index_of_first_available_option]['date_end']
                        , false);
            if ($selected) {
                $delivery_times[$index_of_first_available_option]['selected'] = 1;
            }
        }
    }
    
    public function hookDisplayAfterCarrier() {
        // Hook for PS 1.7 only
        if (!$this->isActive() || !$this->is17) {
            return null;
        }
        
        KfaDeliveryTime::logCalculations(__CLASS__ . ' > ' . __FUNCTION__ . ' > STARTED');
        $result = $this->displayHookContent();
        KfaDeliveryTime::logCalculations(__CLASS__ . ' > ' . __FUNCTION__ . ' > ENDED');
        
        return $result;
    }
    
    private function getDeliveryOptionId() {
        $id_carrier = $this->detectIdCarrier($this->context->cart);
        
        if ($this->is17) {
            return "delivery_option_$id_carrier";
        }
        
        $delivery_options = $this->getDeliveryOptions($this->context->cart);
        for ($i = 0; $i < count($delivery_options); $i++) {
            if ($delivery_options[$i] == $id_carrier) {
                $id_address_delivery = $this->context->cart->id_address_delivery;
                return "delivery_option_{$id_address_delivery}_{$i}";
            }
        }
        
        return '';
    }
    
    /**
     * پابلیک به دلیل استفاده در ماژول اپلیکیشن
     * @param array $delivery_times
     * @param string $key
     * @return bool
     */
    public function groupDeliveryTimes($delivery_times, $key) {
        $groups = array();
        $first_group = false;
        $selected_group = false;
        $active_group = false;
        foreach ($delivery_times as $delivery_time) {
            $group = $delivery_time['date'];
            if (!$first_group) {
                $first_group = $group;
            }
            if (!isset($groups[$group])) {
                if (!empty($delivery_time['fixed'])) {
                    $formatted_tab_line1 = $delivery_time['value'];
                    $formatted_tab_line2 = '';
                } else {
                    $formatted_tab_line1 = $this->formatOption($delivery_time['value'], 'GROUPING_FORMAT_TAB_LINE1');
                    $formatted_tab_line2 = $this->formatOption($delivery_time['value'], 'GROUPING_FORMAT_TAB_LINE2');
                }
                $groups[$group] = array(
                    'active' => 0,
                    'label' => $delivery_time['date_display'],
                    'formatted_tab_line1' => $formatted_tab_line1,
                    'formatted_tab_line2' => $formatted_tab_line2,
                    $key => array(),
                );
            }

            $delivery_time['formatted_range'] = $this->formatOption($delivery_time['value'], 'GROUPING_FORMAT_RANGE');
            $groups[$group][$key][] = $delivery_time;
            if (!empty($delivery_time['selected'])) {
                $groups[$group]['active'] = 1;
                $selected_group = true;
            }
            if (empty($delivery_time['disabled'])) {
                if (!$active_group) {
                    $active_group = $group;
                }
            }
        }

        if (!$selected_group) {
            if ($active_group) {
                $groups[$active_group]['active'] = 1;
            } elseif ($first_group) {
                $groups[$first_group]['active'] = 1;
            }
        }
        
        return $groups;
    }
    
    public function displayHookContent() {
        if (!$this->isActive()) {
            return null;
        }
        
        $cart = $this->context->cart;
        
        $delivery_times = $this->getDeliveryTimes($cart);
        $selected_option_errors = $this->getSelectedOptionErrors($delivery_times, true);
        $this->autoSelectFirstOption($delivery_times, $cart->id, false);
        $approximate = !empty($delivery_times['approximate']);
        
        if ($approximate) {
            $delivery_message = $delivery_times['value'];
            KfaDeliveryTimeCart::selectOption($cart->id
                    , $delivery_times['value']
                    , $delivery_times['process']
                    , $delivery_times['from']
                    , $delivery_times['to']
                    , true);
        } else {
            $delivery_message = '';
        }
        
        $show_description = false;
        if (!$approximate && $delivery_times) {
            foreach ($delivery_times as $delivery_time) {
                if (!empty($delivery_time['description'])) {
                    $show_description = true;
                }
            }
        }
        
        if (!$approximate && $delivery_times && ($grouping = $this->isGroupingEnabled())) {
            $groups = $this->groupDeliveryTimes($delivery_times, 'delivery_times');
        } else {
            $grouping = false;
        }
        
        if ($this->is17) {
            if ($this->isInPrestacart()) {
                $submit_selector = '#continue_steps,#psy_continue_steps';
            } elseif ($this->isInSteasycheckout()) {
                $submit_selector = '.steco_confirmation_btn';
            } else {
                $submit_selector = '[name=confirmDeliveryOption],#payment-confirmation button';
            }
        } else {
            if ($this->isInPrestacart()) {
                $submit_selector = '#SubmitPayment,#HOOK_PAYMENT a';
            } else {
                $submit_selector = Configuration::get('PS_ORDER_PROCESS_TYPE') ? '#HOOK_PAYMENT a' : '[name=processCarrier]';
            }
        }
        
        if (empty($this->context->controller->ssl)) {
            $action = $this->context->link->getModuleLink($this->name);
        } else {
            $action = $this->context->link->getModuleLink($this->name, 'default', array(), Configuration::get('PS_SSL_ENABLED'));
        }
        
        $this->context->smarty->assign(array(
            'kfadeliverytime_auto_expand_shipping_tab' => $this->is17 && $selected_option_errors,
            'kfadeliverytime_required' => $this->isDeliveryTimeRequired(),
            'kfadeliverytime_submit_selector' => $submit_selector,
            'kfadeliverytime_steasycheckout' => $this->isInSteasycheckout(),
            'kfadeliverytime_show_description' => $show_description,
            'kfadeliverytime_action' => $action,
            'kfadeliverytime_delivery_message' => $delivery_message,
            'kfadeliverytime_delivery_option_id' => $this->getDeliveryOptionId(),
            'kfadeliverytime_scroll' => $this->getCarrierScrollData(),
            'kfadeliverytime_heading' => $this->getCarrierHeading($cart),
        ));
        if ($grouping) {
            $this->context->smarty->assign(array(
                'kfadeliverytime_groups' => $groups,
                'kfadeliverytime_tab_width' => $groups ? 100 / count($groups) : 0,
            ));
            $tpl = 'content_grouped.tpl';
        } else {
            $this->context->smarty->assign(array(
                'kfadeliverytime_delivery_times' => $delivery_times,
            ));
            $tpl = $this->isInPrestacart() ? 'content_prestacart.tpl' : 'content.tpl';
        }
        
        return $this->display(dirname(__FILE__), "views/templates/hook/$tpl");
    }
    
    public function hookDisplayCarrierList() {
        // Hook for PS 1.6 only
        if (!$this->isActive() || $this->is17) {
            return null;
        }
        
        KfaDeliveryTime::logCalculations(__CLASS__ . ' > ' . __FUNCTION__ . ' > STARTED');
        $result = $this->displayHookContent();
        KfaDeliveryTime::logCalculations(__CLASS__ . ' > ' . __FUNCTION__ . ' > ENDED');
        return $result;
    }
    
    private function findAlternativeKey($selected_option, $key) {
        $explode = explode(' ', $selected_option);
        if (count($explode) === 2 && $explode[1] == '00:00-23:59' && $this->getConf("{$key}_2359", $this->context->language->id)) {
            return "{$key}_2359";
        }
        
        return $key;
    }
    
    private function getOrderRelatedHookContent($param, $tpl) {
        if (!$this->isActive() || !$this->getConf('HOOK_ORDER_DETAIL')) {
            return null;
        }
        
        $id_cart = $param['order']->id_cart;
        $selected_option = KfaDeliveryTimeCart::getOption($id_cart);
        if (!$selected_option) {
            return null;
        }
        
        $key = $this->findAlternativeKey($selected_option, 'HOOK_ORDER_DETAIL_FORMAT');
        $cart = new Cart($id_cart);
        $this->context->smarty->assign(array(
            'kfadeliverytime_selected_option' => $this->formatOption($selected_option, $key),
            'kfadeliverytime_heading' => $this->getCarrierHeading($cart),
        ));
        return $this->display(dirname(__FILE__), "views/templates/hook/$tpl");
    }
    
    public function hookDisplayOrderConfirmation($param) {
        return $this->getOrderRelatedHookContent($param, 'order_confirmation.tpl');
    }
    
    public function hookDisplayOrderDetail($param) {
        return $this->getOrderRelatedHookContent($param, 'order_detail.tpl');
    }
    
    public function hookDisplayKfaDeliveryTimeOption($param) {
        if (!$this->isActive() || empty($param['order']) || empty($param['format'])) {
            return null;
        }
        
        if (is_numeric($param['order'])) {
            $order = new Order($param['order']);
            $id_cart = $order->id_cart;
        } elseif ($param['order'] instanceof Order) {
            $id_cart = $param['order']->id_cart;
        } else {
            return null;
        }
        
        $selected_option = KfaDeliveryTimeCart::getOption($id_cart);
        if (!$selected_option) {
            return null;
        }
        
        if (!KfaDeliveryTimeFormatHelper::isNormalOption($selected_option) && !$this->getConf('PRINT_ABNORMAL_OPTION')) {
            return null;
        }
        
        $explode = explode(' ', $selected_option);
        if (count($explode) == 2 && $explode[1] == '00:00-23:59' && !empty($param['format2359'])) {
            $format = $param['format2359'];
        } else {
            $format = $param['format'];
        }
        
        $result = $this->formatOption($selected_option, null, $format);
        if (!empty($param['fa'])) {
            $result = str_replace(self::LTR_DIGITS, self::RTL_DIGITS_FA, str_replace(self::RTL_DIGITS_AR, self::LTR_DIGITS, $result));
        }
        return $result;
    }
    
    public function hookDisplayPDFInvoice($param) {
        if (!$this->isActive() || !$this->getConf('HOOK_PDF')) {
            return null;
        }
        
        if (empty($param['object']) || !is_object($param['object'])) {
            return null;
        }
        
        if ($param['object'] instanceof Order) {
            $id_cart = $param['object']->id_cart;
        } else {
            $id_cart = Cart::getCartIdByOrderId($param['object']->id_order);
        }
        
        $selected_option = KfaDeliveryTimeCart::getOption($id_cart);
        if (!$selected_option) {
            return null;
        }
        
        $key = $this->findAlternativeKey($selected_option, 'HOOK_PDF_FORMAT');
        $this->context->smarty->assign(array(
            'kfadeliverytime_selected_option' => $this->formatOption($selected_option, $key),
        ));
        return $this->display(dirname(__FILE__), 'views/templates/hook/pdf_invoice.tpl');
    }
    
    public function hookDisplayProductDeliveryTime($param) {
        if ($this->is17 || empty($param['product']) || !$this->getConf('DISPLAY_PRODUCT_DELIVERY_TIME')) {
            return null;
        }
        
        return '<div id="kfadeliverytime-additional_delivery_times" style="display: none;"></div>';
    }
    
    // Used in appsys3
    public function isDeliveryTimeRequired($id_carrier = null, $id_shop = null) {
        if (is_null($id_carrier)) {
            $id_carrier = $this->detectIdCarrier($this->context->cart);
        }
        if (is_null($id_shop)) {
            $id_shop = isset($this->context->cart) && Validate::isLoadedObject($this->context->cart)
                    ? $this->context->cart->id_shop
                    : $this->context->shop->id;
        }
        
        $program = KfaDeliveryTimeCarrier::getCarrierProgram($id_carrier, $id_shop);
        if ($program == KfaDeliveryTime::CARRIER_PROGRAM_UNAVAILABLE || $program == KfaDeliveryTime::CARRIER_PROGRAM_APPROXIMATE) {
            return false;
        }
        
        $id_reference = KfaDeliveryTimeCarrier::getCarrierReference($id_carrier);
        return KfaDeliveryTimeCarrier::getCarrierRequired($id_reference);
    }
    
    private function getCarrierScrollData() {
        $id_carrier = $this->detectIdCarrier($this->context->cart);
        $result = KfaDeliveryTimeCarrier::getCarrierScrollData($id_carrier);
        $result['first_load_no_scroll'] = $this->getConf('SCROLL_ON_CHANGE_ONLY');
        return $result;
    }
    
    private function getCarrierHeading($cart) {
        $id_carrier = $this->detectIdCarrier($cart);
        return KfaDeliveryTimeCarrier::getCarrierHeading($id_carrier
                , $this->context->shop->id
                , $this->context->language->id);
    }
    
    /* FORMS */
    
    private function renderConfigurationForm($fields_forms, $submit_action, $fields_value = null) {
        $helper                             = new HelperForm();
        $helper->identifier                 = $this->identifier;
        $helper->currentIndex               = $this->context->link->getAdminLink('AdminModules', false) . "&configure=$this->name&tab_module=$this->tab&module_name=$this->name";
        $helper->show_toolbar               = false;
        $helper->table                      = $this->table;
        $helper->module                     = $this;
        $helper->token                      = Tools::getAdminTokenLite('AdminModules');
        $helper->languages                  = $this->languages;
        $helper->default_form_language      = $this->default_form_language;
        $helper->allow_employee_form_lang   = $this->allow_employee_form_lang;
        $helper->submit_action              = $submit_action;
        $helper->fields_value               = empty($fields_value) ? $this->autoGetFieldsValue($fields_forms) : $fields_value;
        return $helper->generateForm($fields_forms);
    }
    
    private function autoGetFieldsValue($forms) {
        $result = array();
        foreach ($forms as $form) {
            $inputs = $form['form']['input'];
            foreach ($inputs as $input) {
                $name = $input['name'];
                if ($input['type'] == 'daily_capacity') {
                    $value = Tools::getValue($name, Configuration::get($name));
                    if (strpos($value, '#') === 0) {
                        $result[$name] = substr($value, 1);
                        $result[$name . '_type'] = '#';
                    } else {
                        $result[$name] = $value;
                        $result[$name . '_type'] = '';
                    }
                } elseif ($input['type'] == 'checkbox') {
                    foreach ($input['values']['query'] as $checkbox) {
                        $key = $name . '_' . $checkbox[$input['values']['id']];
                        $result[$key] = Tools::getValue($key, Configuration::get($key));
                    }
                } elseif (!empty($input['lang'])) {
                    foreach ($this->languages as $language) {
                        $id_lang = $language['id_lang'];
                        $result[$name][$id_lang] = Tools::getValue($name . '_' . $id_lang, Configuration::get($name, $id_lang));
                    }
                } else {
                    $result[$name] = Tools::getValue($name, Configuration::get($name));
                }
            }
        }
        return $result;
    }
    
    private function autoSetFieldsValue($forms) {
        $result = array();
        foreach ($forms as $form) {
            $inputs = $form['form']['input'];
            foreach ($inputs as $input) {
                $is_html = !empty($input['autoload_rte']);
                $name = $input['name'];
                if ($input['type'] == 'daily_capacity') {
                    $capacity = Tools::getValue($name);
                    $capacity_type = $capacity ? Tools::getValue($name . '_type') : '';
                    $value = $capacity_type . $capacity;
                    Configuration::updateValue($name, $value);
                } elseif ($input['type'] == 'checkbox') {
                    foreach ($input['values']['query'] as $checkbox) {
                        $key = $name . '_' . $checkbox[$input['values']['id']];
                        $value = Tools::getValue($key) ? $checkbox['val'] : '';
                        Configuration::updateValue($key, $value);
                    }
                } elseif (!empty($input['lang'])) {
                    $data = array();
                    foreach ($this->languages as $language) {
                        $id_lang = $language['id_lang'];
                        $data[$id_lang] = Tools::getValue($name . '_' . $id_lang);
                    }
                    Configuration::updateValue($name, $data, $is_html);
                } elseif ($input['type'] != 'html') {
                    if (Tools::isSubmit($name)) {
                        Configuration::updateValue($name, Tools::getValue($name), $is_html);
                    } else {
                        Configuration::updateValue($name, '');
                    }
                }
            }
        }
        return $result;
    }
    
    /* AJAX */
    
    public function ajaxProcessDisplayOrders() {
        $id_reference = (int) Tools::getValue('id_reference');
        $value = pSQL(Tools::getValue('value'));
        $id_lang = $this->context->language->id;
        $prefix = _DB_PREFIX_;
        
        $carrier = Carrier::getCarrierByReference($id_reference);
        $capacity_helper = new KfaDeliveryTimeCapacityHelper($this);
        $filters = $capacity_helper->getCapacityInfoFilters($carrier->id);
        $filters[] = "kc.`value` = '$value'";
        $where = 'WHERE ' . implode(' AND ', $filters);
        
        $sql = "
            SELECT
                o.`id_order`, o.`id_cart`, o.`reference`, o.`date_add`,
                CONCAT(c.`id_customer`, '- ', c.`firstname`, ' ', c.`lastname`) AS customer,
                os.`color`,
                osl.`name` AS current_state
            FROM `{$prefix}kfadeliverytime_cart` kc
            INNER JOIN `{$prefix}orders` o USING(`id_cart`)
            LEFT JOIN `{$prefix}customer` c USING(`id_customer`)
            LEFT JOIN `{$prefix}order_state` os ON os.`id_order_state` = o.`current_state`
            LEFT JOIN `{$prefix}order_state_lang` osl ON osl.`id_order_state` = o.`current_state` AND osl.`id_lang` = $id_lang
            $where
        ";
        
        if (!($rows = Db::getInstance()->executeS($sql))) {
            $rows = array();
        }
        $fields_list = array(
            'id_cart' => array(
                'title' => $this->l('ش. سبد خرید'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'search' => false,
            ),
            'id_order' => array(
                'title' => $this->l('ش. سفارش'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'search' => false,
            ),
            'reference' => array(
                'title' => $this->l('مرجع سفارش'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'callback' => 'printOrderReference',
                'callback_object' => $this,
            ),
            'customer' => array(
                'title' => $this->l('مشتری'),
                'align' => 'center',
            ),
            'date_add' => array(
                'title' => $this->l('تاریخ سفارش'),
                'align' => 'center',
                'class' => 'kfa-ltr',
                'callback' => 'printOrderDate',
                'callback_object' => $this,
            ),
            'current_state' => array(
                'title' => $this->l('وضعیت'),
                'align' => 'center',
                'class' => 'kfa-ltr',
                'callback' => 'printOrderStatus',
                'callback_object' => $this,
            ),
        );
        
        $title = $this->l('گزارش سفارش‌ها');
        if (($total = count($rows))) {
            $title .= " ($total " . $this->l('مورد') . ')';
        }
        $title .=  " - <span class=\"kfa-date\">$value</span>";
        
        $helper = new HelperList();
        $helper->no_link = true;
        $helper->shopLinkType = '';
        $helper->simple_header = true;
        $helper->listTotal = $total;
        $helper->identifier = 'id_cart';
        $helper->table = 'kfadeliverytime_cart';
        $helper->actions = array();
        $helper->show_toolbar = true;
        $helper->module = $this;
        $helper->title = $title;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex;
        die($helper->generateList($rows, $fields_list));
    }
    
    public function printOrderDate($echo, $tr) {
        if ($tr) {
            // Suppress netbeans warning
        }
        
        if ($this->context->language->iso_code == 'fa') {
            $date = KfaPersianDate::gToS($echo);
            return "<span class=\"kfa-date\">$date</span>";
        }
        
        return $echo;
    }
    
    public function printOrderStatus($echo, $tr) {
        $text_color = Tools::getBrightness($tr['color']) < 128 ? 'white' : 'black';
        return "<span class=\"label color_field\" style=\"background-color: $tr[color]; color: $text_color;\">$echo</span>";
    }
    
    public function printOrderReference($echo, $tr) {
        static $token_order = null;
        if (is_null($token_order)) {
            $token_order = Tools::getAdminToken('AdminOrders' . Tab::getIdFromClassName('AdminOrders') . $this->context->employee->id);
        }
        
        $id_order = $tr['id_order'];
        $url_order = "index.php?controller=AdminOrders&id_order=$id_order&vieworder&token=$token_order";
        return "<a href=\"$url_order\" target=\"_blank\">$echo</a>";
    }
    
    public function prinIdtCart($echo, $tr) {
        static $token_cart = null;
        if (is_null($token_cart)) {
            $token_cart = Tools::getAdminToken('AdminCarts' . Tab::getIdFromClassName('AdminCarts') . $this->context->employee->id);
        }
        
        $id_cart = $tr['id_cart'];
        $url_cart = "index.php?controller=AdminCarts&id_cart=$id_cart&viewcart&token=$token_cart";
        return "<a href=\"$url_cart\" target=\"_blank\">$echo</a>";
    }
    
    public function ajaxProcessGetStatistics() {
        if (($warning = $this->getShopContextWarning())) {
            if (Tools::getValue('ajax')) {
                die($warning);
            } else {
                return $warning;
            }
        }
        
        if (($from = (int) Tools::getValue('from')) >= 0) {
            Configuration::updateValue($this->conf . 'STATISTICS_FROM', $from);
        } else {
            $from = $this->getConf('STATISTICS_FROM');
        }
        if ($from === false) {
            $from = 3;
        }
        
        if (($to = (int) Tools::getValue('to')) >= 0) {
            Configuration::updateValue($this->conf . 'STATISTICS_TO', $to);
        } else {
            $to = $this->getConf('STATISTICS_TO');
        }
        if ($to === false) {
            $to = 3;
        }
        
        $start_date = date('Y-m-d', strtotime(date('Y-m-d') . " -$from days"));
        $today = strtotime(date('Y-m-d'));
        
        $carriers = $this->getCarriers();
        $result = array();
        foreach ($carriers as $carrier) {
            $id_reference = $carrier['id_reference'];
            if ($id_reference == 0) {
                continue;
            }
            
            $id_carrier = $carrier['id_carrier'];
            $program = KfaDeliveryTimeCarrier::getCarrierProgram($id_carrier, $this->context->shop->id);
            switch ($program) {
                case KfaDeliveryTime::CARRIER_PROGRAM_UNAVAILABLE:
                    $options = array(
                        'showInfo' => true,
                        'info' => $this->l('بدون نیاز به زمان تحویل'),
                    );
                    break;
                    
                case KfaDeliveryTime::CARRIER_PROGRAM_APPROXIMATE:
                    $options = array(
                        'showInfo' => true,
                        'info' => $this->l('زمان تقریبی'),
                        'approximate' => 1,
                        'options' => array(),
                    );
                    
                    for ($days = 0; $days < $to + $from; $days++) {
                        $next_day_date = date('Y-m-d', strtotime("$start_date + $days days"));
                        
                        $data = KfaDeliveryTimeCarrier::getCarrierApproximateData($id_reference);
                        $delivery_time = array(
                            'date' => $next_day_date,
                            'date_start' => "$next_day_date 00:00:00",
                            'date_end' => "$next_day_date 23:59:59",
                            'capacity' => $data['approximate_capacity'],
                        );
                        $capacity_info = $this->getCapacityInfo($delivery_time, $id_carrier, true);
                        
                        $options['options'][] = array(
                            'label'         => KfaPersianDate::gToS($next_day_date, false, false),
                            'info'          => $this->getAdminDeliveryTimes(0, $next_day_date, $id_carrier, true)['info'],
                            'nb_orders'     => $capacity_info['nb_orders'],
                            'unlimited'     => $capacity_info['unlimited'],
                            'col_target'    => $capacity_info['col_target'],
                            'col_filled'    => $capacity_info['col_filled'],
                            'col_remaining' => $capacity_info['col_remaining'],
                            'value'         => '',
                        );
                    }
                    break;
                    
                default:
                    $options = $this->getAdminDeliveryTimes(0, $start_date, $id_carrier, true, $to);
                    break;
            }
            $options['name'] = $carrier['name'];
            $options['ids'] = KfaDeliveryTimeCarrier::getListOfIds($id_reference);
            
            if (!empty($options['options'])) {
                $future_days = 0;
                foreach (array_keys($options['options']) as $date) {
                    if (strtotime($date) > $today) {
                        if (++$future_days > $to) {
                            unset($options['options'][$date]);
                        }
                    }
                }
            }
            
            $result[$id_reference] = $options;
        }
        
        $id_cart_alias = $this->l('سبد');
        $order_reference_alias = $this->l('مرجع سفارش');
        $prefix = _DB_PREFIX_;
        $recent_sql = "
            SELECT
                cart.`id_cart` AS `$id_cart_alias`,
                IFNULL(o.`id_order`, '--') AS `سفارش`,
                IFNULL(o.`reference`, '--') AS `$order_reference_alias`,
                CONCAT(cart.`id_carrier`, '- ', carrier.`name`) AS `حامل`, cart.`id_address_delivery` AS `آدرس`,
                cart.`date_add` AS `ایجاد`, cart.`date_upd` AS `آپدیت`,
                IFNULL(kc.`value`, '--') AS `زمان تحویل`,
                CONCAT(cart.`id_customer`, '- ', customer.`firstname`, ' ', customer.`lastname`) AS `مشتری`
            FROM `{$prefix}cart` cart
            LEFT JOIN `{$prefix}kfadeliverytime_cart` kc USING(`id_cart`)
            INNER JOIN `{$prefix}customer` customer USING(`id_customer`)
            LEFT JOIN `{$prefix}carrier` carrier USING(`id_carrier`)
            LEFT JOIN `{$prefix}orders` o USING(`id_cart`)
            ORDER BY cart.`date_upd` DESC
            LIMIT 0, 100
        ";
        if (($recent_rows = Db::getInstance()->executeS($recent_sql))) {
            $first_row = $recent_rows ? reset($recent_rows) : null;
            $recent_columns = array_keys($first_row);
            foreach ($recent_rows as $index => $row) {
                $recent_rows[$index]['ایجاد'] = '<span class="kfa-ltr">' . KfaPersianDate::gToS($row['ایجاد']) . '</span>';
                $recent_rows[$index]['آپدیت'] = '<span class="kfa-ltr">' . KfaPersianDate::gToS($row['آپدیت']) . '</span>';
                
                if (($echo = $recent_rows[$index][$order_reference_alias]) != '--') {
                    $tr = array('id_order' => $recent_rows[$index]['سفارش']);
                    $recent_rows[$index][$order_reference_alias] = $this->printOrderReference($echo, $tr);
                }
                
                if (($echo = $recent_rows[$index][$id_cart_alias]) != '--') {
                    $tr = array('id_cart' => $recent_rows[$index][$id_cart_alias]);
                    $recent_rows[$index][$id_cart_alias] = $this->prinIdtCart($echo, $tr);
                }
            }
        } else {
            $recent_columns = null;
        }
        
        $this->context->smarty->assign(array(
            'from'                  => $from,
            'to'                    => $to === false ? 3 : (int) $to,
            'carriers'              => $result,
            'recent_columns'        => $recent_columns,
            'recent_rows'           => $recent_rows,
            'can_display_orders'    => $this->getConf('ORDER_CHECKING_BY_CARRIER'),
        ));
        $html = $this->display(dirname(__FILE__), 'views/templates/admin/statistics.tpl');
        if (Tools::getValue('ajax')) {
            die($html);
        } else {
            return $html;
        }
    }
    
    private function getAdminDeliveryTimes($id_cart, $start_date, $id_carrier_force = false, $full_capacity_info = false, $future_days_count = false) {
        $cart = new Cart($id_cart);
        $id_carrier = $id_carrier_force === false ? $this->detectIdCarrier($cart) : $id_carrier_force;
        $delivery_times = $this->getDeliveryTimes($cart, $id_carrier_force, $start_date, KfaDeliveryTime::DISABLED_DAYS_DISPLAY, true, $future_days_count);
        $approximate = !empty($delivery_times['approximate']);
        
        $colors = array(
            'enabled'           => $this->getConf('RANGE_COLOR_ENABLED'),
            'disabled'          => $this->getConf('RANGE_COLOR_DISABLED'),
            'unavailable'       => $this->getConf('RANGE_COLOR_UNAVAILABLE'),
            'out_of_capacity'   => $this->getConf('RANGE_COLOR_OUT_OF_CAPACITY'),
        );
        
        $options = array();
        
        if (!$approximate && is_array($delivery_times)) {
            $selected_option = KfaDeliveryTimeCart::getByIdCart($id_cart);
            foreach ($delivery_times as $delivery_time) {
                $group = $delivery_time['date'];
                if ($full_capacity_info && !empty($delivery_time['fixed'])) {
                    continue;
                }
                
                if (!isset($options[$group])) {
                    if (!empty($delivery_time['fixed'])) {
                        $label = $delivery_time['value'];
                    } else {
                        $label = $delivery_time['date_display'];
                    }
                    $options[$group] = array(
                        'label' => $label,
                        'options' => array(),
                    );
                }
                
                if ($delivery_time['unavailable']) {
                    $color = $colors['unavailable'];
                } elseif ($delivery_time['out_of_capacity']) {
                    $color = $colors['out_of_capacity'];
                } elseif ($delivery_time['disabled']) {
                    $color = $colors['disabled'];
                } else {
                    $color = $colors['enabled'];
                }
                
                if (!empty($delivery_time['fixed'])) {
                    $display        = $delivery_time['value'];
                    $col_target     = null;
                    $col_remaining  = null;
                    $col_filled     = null;
                    $unlimited      = null;
                    $nb_orders      = null;
                } else {
                    $info           = $this->getCapacityInfo($delivery_time, $id_carrier, $full_capacity_info);
                    $display        = "$delivery_time[date_display] $delivery_time[time_display] - $info[remaining]";
                    $col_target     = $info['col_target'];
                    $col_remaining  = $info['col_remaining'];
                    $col_filled     = $info['col_filled'];
                    $unlimited      = $info['unlimited'];
                    $nb_orders      = $info['nb_orders'];
                }
                $options[$group]['options'][] = array(
                    'display'           => $display,
                    'date_display'      => $delivery_time['date_display'],
                    'time_display'      => $delivery_time['time_display'],
                    'value'             => $delivery_time['value'],
                    'date_start'        => $delivery_time['date_start'],
                    'date_end'          => $delivery_time['date_end'],
                    'disabled'          => $delivery_time['disabled'],
                    'unavailable'       => $delivery_time['unavailable'],
                    'out_of_capacity'   => $delivery_time['out_of_capacity'],
                    'unlimited'         => $unlimited,
                    'color'             => $color,
                    'col_target'        => $col_target,
                    'col_remaining'     => $col_remaining,
                    'col_filled'        => $col_filled,
                    'nb_orders'         => $nb_orders,
                    'selected'          => $selected_option && $selected_option['date_start'] == $delivery_time['date_start'] && $selected_option['date_end'] == $delivery_time['date_end'],
                );
            }
        }
        
        if ($approximate) {
            if (isset($delivery_times['multiple_date'])) {
                $info = '...';
            } else {
                $info = $delivery_times['value'];
            }
        } else {
            $info = $this->l('این حامل نیازی به انتخاب زمان تحویل سفارش ندارد.');
        }
        $required = $this->isDeliveryTimeRequired($id_carrier, $cart->id_shop);
        return array(
            'id_cart'           => $cart->id,
            'action'            => Tools::getValue('action'),
            'options'           => $options,
            'showWarning'       => empty($options) && $required,
            'showInfo'          => empty($options) && !$required,
            'info'              => $info,
            'approximate'       => $approximate,
            'delivery_times'    => $delivery_times,
            'no_delivery'       => empty($delivery_times),
        );
    }
    
    private function createHistory($id_cart) {
        $rows = KfaDeliveryTimeHistory::get($id_cart, (int) $this->getConf('HISTORY_LIMIT'));
        foreach ($rows as $index => $row) {
            $rows[$index]['value'] = $this->formatOption($row['value'], 'HOOK_ADMIN_ORDER_DETAIL_FORMAT');
        }
        $this->context->smarty->assign(array(
            'rows' => $rows,
        ));
        return $this->display(dirname(__FILE__), 'views/templates/admin/history.tpl');
    }
    
    public function ajaxProcessAdminGetDeliveryTimes() {
        $id_cart = (int) Tools::getValue('id_cart');
        
        $cart = new Cart($id_cart);
        $id_carrier = $this->detectIdCarrier($cart);
        $program = KfaDeliveryTimeCarrier::getCarrierProgram($id_carrier, $cart->id_shop);
        if ($program == KfaDeliveryTime::CARRIER_PROGRAM_UNAVAILABLE) {
            // چون در هوک پس از ثبت سفارش در صورت لزوم زمان تحویل حذف می شود
            // دیگر اینجا نیازی به حذف زمان تحویل نیست
            //KfaDeliveryTimeCart::deleteOption($id_cart);
        } elseif ($program == KfaDeliveryTime::CARRIER_PROGRAM_APPROXIMATE) {
            if (($delivery_times = $this->getDeliveryTimes($cart))) {
                KfaDeliveryTimeCart::selectOption($id_cart
                        , $delivery_times['value']
                        , $delivery_times['process']
                        , $delivery_times['from']
                        , $delivery_times['to']
                        , true);
            } else {
                KfaDeliveryTimeCart::deleteOption($id_cart);
            }
        }
        
        $result = $this->getAdminDeliveryTimes($id_cart, date('Y-m-d'));
        $json = json_encode($result);
        die($json);
    }
    
    public function ajaxProcessAdminSetDeliveryTime() {
        $error = false;
        if (($id_cart = (int) Tools::getValue('id_cart'))) {
            if (($value = Tools::getValue('value'))) {
                $date_process = Tools::getValue('date_process');
                $date_start = Tools::getValue('date_start');
                $date_end = Tools::getValue('date_end');
                $approximate = (bool) Tools::getValue('approximate');
                if (!Validate::isDate($date_start) || !Validate::isDate($date_end)) {
                    $error = $this->l('فرمت زمان تحویل معتبر نیست.');
                } elseif (KfaDeliveryTimeCart::selectOption($id_cart, $value, $date_process, $date_start, $date_end, $approximate)) {
                    $message = $this->l('زمان تحویل تنظیم شد.');
                    $display = $this->formatOption($value, 'HOOK_ADMIN_ORDER_DETAIL_FORMAT');
                }
            } else {
                if (KfaDeliveryTimeCart::deleteOption($id_cart)) {
                    $message = $this->l('زمان تحویل حذف شد.');
                    $display = $this->l('انتخاب نشده است.');
                }
            }
        }
        
        $result = array(
            'success' => empty($message) ? false : $message,
            'display' => empty($display) ? false : $display,
            'history' => $this->createHistory($id_cart),
        );

        if (!empty($error)) {
            $result['error'] = $error;
        }
        $json = json_encode($result);
        die($json);
    }
    
    public function ajaxProcessAdminSetDeliveryTimeAdvanced() {
        if (($id_cart = (int) Tools::getValue('id_cart'))) {
            if (($start_date = Tools::getValue('start_date'))) {
                $data = $this->getAdminDeliveryTimes($id_cart, $start_date);
                $value = $data['info'];
                $date_process = $data['delivery_times']['process'];
                $date_start = $data['delivery_times']['from'];
                $date_end = $data['delivery_times']['to'];
                $approximate = true;
                if (KfaDeliveryTimeCart::selectOption($id_cart, $value, $date_process, $date_start, $date_end, $approximate)) {
                    $message = $this->l('زمان تحویل تنظیم شد.');
                    $display = $value;
                }
            } else {
                if (KfaDeliveryTimeCart::deleteOption($id_cart)) {
                    $message = $this->l('زمان تحویل حذف شد.');
                    $display = $this->l('انتخاب نشده است.');
                }
            }
        }
        
        $result = array(
            'success' => empty($message) ? false : $message,
            'display' => empty($display) ? false : $display,
            'history' => $this->createHistory($id_cart),
        );
        $json = json_encode($result);
        die($json);
    }
    
    public function ajaxProcessGetUpdates() {
        if (!in_array('curl', get_loaded_extensions()) || !function_exists('curl_version')) {
            die('!can do');
        }

        $action = 'get_updates';
        $updates = array();
        $updates[] = $this->getUpdates('http://systemiha.ir/apps/modules/news.php', $action);

        $html = '';
        $all_error = true;
        foreach ($updates as $update) {
            if ($update['error']) {
                continue;
            }
            $html .= $update['response'];
            $all_error = false;
            
            if (isset($update['modules']) && isset($update['modules'][$this->name])) {
                $version_compare = version_compare($this->version, $update['modules'][$this->name]['version']);
                $version_link = $update['modules'][$this->name]['link'];
            }
        }

        if ($all_error) {
            $error = '<div class="alert alert-danger"><p>' . $this->l('خطایی در دریافت آخرین اخبار و به روز رسانی‌ها رخ داد.') . '</p></div>';
        } else {
            $error = '';
        }
        
        if (isset($version_compare)) {
            if ($version_compare === -1) {
                $html =
                    '<div class="alert alert-warning">' .
                    "   <a href=\"$version_link\" target=\"_blank\">" .
                    $this->l('نسخه‌ی جدید این افزونه منتشر شده است. برای دانلود آن کلیک کنید.') .
                    '   </a>' .
                    '</div>' .
                    $html;
            } else {
                $html =
                    '<div class="alert alert-success">' .
                    $this->l('شما در حال استفاده از جدیدترین نسخه‌ی این افزونه هستید.') .
                    '</div>' .
                    $html;
            }
        }
        $response = array(
            'hasError'  => !empty($error),
            'error'     => $error,
            'response'  => $html,
        );
        die(Tools::jsonEncode($response));
    }
    
    private function getUpdates($url, $action) {
        $fields = array(
            'action'    => $action,
            'module'    => $this->name,
            'shop_url'  => _PS_BASE_URL_.__PS_BASE_URI__,
        );
        
        $options = array(
            CURLOPT_URL             => $url,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST            => 1,
            CURLOPT_POSTFIELDS      => http_build_query($fields),
            CURLOPT_HTTPHEADER      => array(
                "cache-control: no-cache"
            ),
        );
        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $json = curl_exec($curl);
        if (curl_error($curl)) {
            $error = true;
        } else {
            $error = false;
        }
        curl_close($curl);

        $html = '';
        $response = Tools::jsonDecode($json, true);
        if (is_array($response) && isset($response['status']) && $response['status'] == '200' && !empty($response['data'])) {
            $html = $response['data'];
        }

        return array(
            'response' => $html,
            'error' => $error,
            'modules' => isset($response['modules']) ? $response['modules'] : null,
        );
    }
}
