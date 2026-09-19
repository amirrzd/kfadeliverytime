<?php
/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, May 2020
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

class KfaDeliveryTimeAdminTabManager {
    /**
     *
     * @var array
     */
    private $languages;
    
    /**
     *
     * @var string
     */
    private $moduleName;
    
    /**
     *
     * @var string
     */
    private $moduleDisplayName;
    
    /**
     *
     * @var type array
     */
    private $tabs = array(
        'AdminKfaDeliveryTimeStatistics' => array(
            'name' => 'وضعیت بازه‌ها',
            'parent' => 'AdminParentOrders',
            'parent_name' => false,
        ),
        'AdminKfaDeliveryTimeProducts' => array(
            'name' => 'زمان تحویل محصولات',
            'parent' => 'AdminCatalog',
            'parent_name' => false,
        ),
    );
    
    /**
     *
     * @var KfaDeliveryTimeAdminTabManager
     */
    private static $instance = null;
    
    /**
     * 
     * @param array $languages
     * @param string $moduleName
     * @param string $moduleDisplayName
     */
    public function __construct($languages, $moduleName, $moduleDisplayName) {
        $this->languages = $languages;
        $this->moduleName = $moduleName;
        $this->moduleDisplayName = $moduleDisplayName;
    }
    
    /**
     * 
     * @param Array $languages
     * @param String $moduleName
     * @param String $moduleDisplayName
     * @return KfaDeliveryTimeAdminTabManager
     */
    public static function getInstance($languages, $moduleName, $moduleDisplayName) {
        if (is_null(self::$instance)) {
            self::$instance = new KfaDeliveryTimeAdminTabManager($languages, $moduleName, $moduleDisplayName);
        }
        return self::$instance;
    }
    
    /**
     * 
     * @param string $class_name Class name of AdminModuleController
     * @param string $name Title for AdminModuleController page
     * @param string|false $parent_class_name
     * @param string|false $parent_name
     * @return int Returns identifier of added tab or 0 on fail
     */
    private function addTab($class_name, $name, $parent_class_name = false, $parent_name = false) {
        if ($parent_class_name === -1) {
            // Invisible admin controller not shown in any menu
            $id_parent = -1;
        } elseif ($parent_class_name) {
            // Add admin controller link to existing menu
            $id_parent = $this->addTab($parent_class_name, $parent_name);
        } else {
            // Add admin controller link to main menu
            $id_parent = 0;
        }

        $id_tab = Tab::getIdFromClassName($class_name);
        if ($id_tab) {
            $tab = new Tab($id_tab);
            // اگر تب وجود داشت و تب اصلی بود نباید ویرایش شود
            if ($tab->id && !$parent_class_name) {
                return $tab->id;
            }
        } elseif (empty($name)) {
            return false;
        } else {
            $tab = new Tab();
        }
        $tab->module = $this->moduleName;
        $tab->active = 1;
        $tab->class_name = $class_name;
        $tab->id_parent = $id_parent;
        
        $tab->name = array();
        foreach ($this->languages as $lang) {
            $tab->name[$lang['id_lang']] = $name;
        }

        if ($tab->save()) {
            return $tab->id;
        } else {
            return 0;
        }
    }
    
    /**
     * 
     * @param string $class_name
     * @return bool
     */
    private function removeTab($class_name) {
        $id_tab = Tab::getIdFromClassName($class_name);
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }
        return true;
    }
    
    /**
     * 
     * @return bool
     */
    public function addTabs() {
        $result = true;
        foreach ($this->tabs as $tab => $info) {
            $result &= $this->addTab($tab, $info['name'], $info['parent'], $info['parent_name']) > 0;
        }
        return $result;
    }
    
    /**
     * 
     * @return bool
     */
    public function removeTabs() {
        $result = true;
        foreach (array_keys($this->tabs) as $tab) {
            $result &= $this->removeTab($tab);
        }
        return $result;
    }
}
