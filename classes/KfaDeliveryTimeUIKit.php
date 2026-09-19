<?php
/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, March 2020
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

class KfaDeliveryTimeUIKit {
    
    const PREFIX = 'KFADELIVERYTIME_';
    public static $yes = null;
    public static $no = null;
    
    public static function createSeparator() {
        return array(
            'type' => 'html',
            'name' => '<hr>',
        );
    }
    
    /**
     * 
     * @param String $label
     * @param String $name
     * @param Array $query
     * @param String $form_group_class
     * @return Array
     */
    public static function createCheckBoxConf($label, $name, $query, $form_group_class = null) {
        return array(
            'type' => 'checkbox',
            'label' => $label,
            'name' => self::PREFIX . $name,
            'values' => array(
                'query' => $query,
                'id' => 'id',
                'name' => 'name',
            ),
            'form_group_class' => $form_group_class,
        );
    }
    
    /**
     * 
     * @param String $label
     * @param String $key
     * @param String $default
     * @param Boolean $required
     * @param String $desc
     * @param String $hint
     * @param String $form_group_class
     * @return Array
     */
    public static function createColorInput($label, $key, $default = null, $required = false, $desc = null, $hint = null, $form_group_class = null) {
        $input = array(
            'type' => 'color',
            'label' => $label,
            'name' => $key,
            'class' => 'kfa-color',
        );
        
        if ($default) {
            $input['default_value'] = $default;
        }
        if ($required) {
            $input['required'] = true;
        }
        if ($desc) {
            $input['desc'] = $desc;
        }
        if ($hint) {
            $input['hint'] = $hint;
        }
        if ($form_group_class) {
            $input['form_group_class'] = $form_group_class;
        }
        
        return $input;
    }
    
    /**
     * 
     * @param String $label
     * @param String $key
     * @param String $default
     * @param Boolean $required
     * @param String $desc
     * @param String $hint
     * @param String $form_group_class
     * @return Array
     */
    public static function createColorInputConf($label, $key, $default = null, $required = false, $desc = null, $hint = null, $form_group_class = null) {
        return self::createColorInput($label, self::PREFIX . $key, $default, $required, $desc, $hint, $form_group_class);
    }
    
    /**
     * 
     * @param String $label
     * @param String $name
     * @param String $default
     * @param String $desc
     * @param Boolean $required
     * @param String $suffix
     * @param String $form_group_class
     * @param string $validate
     * @return Array
     */
    public static function createNumberInput($label, $name, $default = null, $desc = null, $required = false, $suffix = null, $form_group_class = null, $validate = 'isUnsignedInt') {
        $result = array(
            'type' => 'text',
            'label' => $label,
            'name' => $name,
            'class' => 'fixed-width-xs kfa-ltr',
        );
        if ($desc) {
            $result['desc'] = $desc;
        }
        if ($required) {
            $result['required'] = $required;
        }
        if ($default !== null) {
            $result['default'] = $default;
            $result['default_value'] = $default;
        }
        if ($suffix) {
            $result['suffix'] = $suffix;
            if ($suffix == '%') {
                $validate = 'isPercentage';
            }
        }
        if ($validate) {
            $result['validate'] = $validate;
        }
        if ($form_group_class) {
            $result['form_group_class'] = $form_group_class;
        }
        return $result;
    }
    
    /**
     * 
     * @param String $label
     * @param String $name
     * @param String $default
     * @param String $desc
     * @param Boolean $required
     * @param String $suffix
     * @param String $form_group_class
     * @param String $validate
     * @return Array
     */
    public static function createNumberInputConf($label, $name, $default = null, $desc = null, $required = false, $suffix = null, $form_group_class = null, $validate = 'isUnsignedInt') {
        return self::createNumberInput($label, self::PREFIX . $name, $default, $desc, $required, $suffix, $form_group_class, $validate);
    }
    
    /**
     * 
     * @param String $name
     * @param String $label
     * @param String $values
     * @param Int $default
     * @param String $desc
     * @param String $form_group_class
     * @return Array
     */
    public static function createRadio($name, $label, $values, $default = false, $desc = null, $form_group_class = null) {
        foreach ($values as &$value) {
            $value['id'] = $name . '_' . $value['value'];
        }
        $result = array(
            'type' => 'radio',
            'name' => $name,
            'values' => $values,
            'label' => $label,
        );
        if ($default) {
            $result['default'] = $default;
        }
        if ($desc) {
            $result['desc'] = $desc;
        }
        if ($form_group_class !== null) {
            $result['form_group_class'] = $form_group_class;
        }
        return $result;
    }
    
    /**
     * 
     * @param String $name
     * @param String $label
     * @param String $values
     * @param Int $default
     * @param String $desc
     * @param String $form_group_class
     * @return Array
     */
    public static function createRadioConf($name, $label, $values, $default = false, $desc = null, $form_group_class = null) {
        return self::createRadio(self::PREFIX . $name, $label, $values, $default, $desc, $form_group_class);
    }
    
    /**
     * 
     * @param String $label
     * @param String $name
     * @param Array $query
     * @param String $desc
     * @param Integer $default
     * @param String $empty_value
     * @param Boolean $required
     * @param String $form_group_class
     * @param String $onchange
     * @return Array
     */
    public static function createSelect($label, $name, $query, $desc = null, $default= null, $empty_value = null, $required = false, $form_group_class = null, $onchange = null) {
        $result = array(
            'type' => 'select',
            'label' => $label,
            'name' => $name,
            'empty_value' => $empty_value,
            'options' => array(
                'id' => 'id',
                'name' => 'name',
                'query' => $query,
            ),
        );
        
        if ($default) {
            $result['default'] = $default;
        }
        if ($empty_value) {
            $result['empty_value'] = $empty_value;
        }
        if ($desc) {
            $result['desc'] = $desc;
        }
        if ($required) {
            $result['required'] = $required;
        }
        if ($form_group_class) {
            $result['form_group_class'] = $form_group_class;
        }
        if ($onchange) {
            $result['onchange'] = $onchange;
        }
        return $result;
    }
    
    /**
     * 
     * @param String $label
     * @param String $name
     * @param Array $query
     * @param String $desc
     * @param Integer $default_value
     * @param String $empty_value
     * @param Boolean $required
     * @param String $form_group_class
     * @param String $onchange
     * @return Array
     */
    public static function createSelectConf($label, $name, $query, $desc = null, $default_value = null, $empty_value = null, $required = false, $form_group_class = null, $onchange = null) {
        return self::createSelect($label, self::PREFIX . $name, $query, $desc, $default_value, $empty_value, $required, $form_group_class, $onchange);
    }
    
    /**
     * 
     * @param String $label
     * @param String $name
     * @param String $desc
     * @param Integer $default_value
     * @param String $required
     * @param String $form_group_class
     * @param String $hint
     * @return Array
     */
    public static function createSwitch($label, $name, $desc = null, $default_value = 0, $required = false, $form_group_class = null, $hint = null) {
        $result = array(
            'type' => 'switch',
            'label' => $label,
            'name' => $name,
            'is_bool' => true,
            'values' => array(
                array(
                    'id' => "{$name}_on",
                    'value' => 1,
                    'label' => self::$yes,
                ),
                array(
                    'id' => "{$name}_off",
                    'value' => 0,
                    'label' => self::$no,
                ),
            ),
            'default_value' => (bool)$default_value,
        );
        if ($desc) {
            $result['desc'] = $desc;
        }
        if ($required) {
            $result['required'] = $required;
        }
        if ($form_group_class) {
            $result['form_group_class'] = $form_group_class;
        }
        if ($hint) {
            $result['hint'] = $hint;
        }
        return $result;
    }
    
    /**
     * 
     * @param String $label
     * @param String $name
     * @param String $desc
     * @param Integer $default_value
     * @param String $required
     * @param String $form_group_class
     * @param String $hint
     * @return Array
     */
    public static function createSwitchConf($label, $name, $desc = null, $default_value = 0, $required = false, $form_group_class = null, $hint = null) {
        return self::createSwitch($label, self::PREFIX . $name, $desc, $default_value, $required, $form_group_class, $hint);
    }
    
    public static function createSmsTemplateFieldConf($label, $name, $desc = null, $hint = null, $disabled = null) {
        $result = array(
            'type' => 'text',
            'label' => $label,
            'name' => self::PREFIX . $name,
            'class' => 'kfa-ltr sms-field sms-field-only-template',
            'desc' => $desc,
            'hint' => $hint,
            'disabled' => $disabled,
        );
        
        if ($desc) {
            $result['desc'] = $desc;
        }
        if ($hint) {
            $result['hint'] = $hint;
        }
        if ($disabled) {
            $result['disabled'] = $disabled;
        }
        
        return $result;
    }
    
    /* createTextArea */
    
    public static function createTextArea($label, $name, $desc = null, $form_group_class = null) {
        $result = array(
            'type' => 'textarea',
            'label' => $label,
            'name' => $name,
        );
        if ($desc) {
            $result['desc'] = $desc;
        }
        if ($form_group_class) {
            $result['form_group_class'] = $form_group_class;
        }
        return $result;
    }
    
    public static function createTextAreaLang($label, $name, $desc = null, $form_group_class = null) {
        $result = self::createTextArea($label, $name, $desc, $form_group_class);
        $result['lang'] = true;
        return $result;
    }
    
    public static function createTextAreaLtr($label, $name, $desc = null, $form_group_class = null) {
        $result = self::createTextArea($label, $name, $desc, $form_group_class);
        $result['class'] = 'kfa-ltr' . (empty($result['class']) ? '' : ' ' . $result['class']);
        return $result;
    }
    
    public static function createTextAreaLangLtr($label, $name, $desc = null, $form_group_class = null) {
        $result = self::createTextAreaLtr($label, $name, $desc, $form_group_class);
        $result['lang'] = true;
        return $result;
    }
    
    /* createTextArea Conf */
    
    public static function createTextAreaConf($label, $name, $desc = null, $form_group_class = null) {
        return self::createTextArea($label, self::PREFIX . $name, $desc, $form_group_class);
    }
    
    public static function createTextAreaLangConf($label, $name, $desc = null, $form_group_class = null) {
        return self::createTextAreaLang($label, self::PREFIX . $name, $desc, $form_group_class);
    }
    
    public static function createTextAreaLtrConf($label, $name, $desc = null, $form_group_class = null) {
        return self::createTextAreaLtr($label, self::PREFIX . $name, $desc, $form_group_class);
    }
    
    public static function createTextAreaLangLtrConf($label, $name, $desc = null, $form_group_class = null) {
        return self::createTextAreaLangLtr($label, self::PREFIX . $name, $desc, $form_group_class);
    }
    
    /* createTextEditor */
    
    public static function createTextEditor($label, $name, $desc = null, $form_group_class = null) {
        $result = array(
            'type' => 'textarea',
            'label' => $label,
            'name' => $name,
            'autoload_rte' => true,
        );
        if ($desc) {
            $result['desc'] = $desc;
        }
        if ($form_group_class) {
            $result['form_group_class'] = $form_group_class;
        }
        return $result;
    }
    
    public static function createTextEditorLang($label, $name, $desc = null, $form_group_class = null) {
        $result = self::createTextEditor($label, $name, $desc, $form_group_class);
        $result['lang'] = true;
        return $result;
    }
    
    /* createTextEditor Conf */
    
    public static function createTextEditorConf($label, $name, $desc = null, $form_group_class = null) {
        return self::createTextEditor($label, self::PREFIX . $name, $desc, $form_group_class);
    }
    
    public static function createTextEditorLangConf($label, $name, $desc = null, $form_group_class = null) {
        return self::createTextEditorLang($label, self::PREFIX . $name, $desc, $form_group_class);
    }
    
    /* createText */
    
    public static function createText($label, $name, $desc = null, $form_group_class = null, $class = null, $required = false, $default = null, $maxchar = 0, $suffix = null, $validate = null) {
        $result = array(
            'type' => 'text',
            'label' => $label,
            'name' => $name,
            'autoload_rte' => true,
        );
        
        if ($desc) {
            $result['desc'] = $desc;
        }
        if ($form_group_class) {
            $result['form_group_class'] = $form_group_class;
        }
        if ($class) {
            $result['class'] = $class;
        }
        if ($required) {
            $result['required'] = (bool) $required;
        }
        if ($default) {
            $result['default'] = $default;
        }
        if ($maxchar) {
            $result['maxchar'] = (int) $maxchar;
        }
        if ($suffix) {
            $result['suffix'] = $suffix == 'search' ? '<i class="icon-search"></i>' : $suffix;
        }
        
        if (strpos($name, 'POST_CODE') !== false) {
            $validate = 'isPostCode';
        } 
        if ($validate) {
            $result['validate'] = $validate;
        }
        
        return $result;
    }
    
    public static function createTextLang($label, $name, $desc = null, $form_group_class = null, $class = null, $required = false, $default = null, $maxchar = 0, $suffix = null, $validate = null) {
        $result = self::createText($label, $name, $desc, $form_group_class, $class, $required, $default, $maxchar, $suffix, $validate);
        $result['lang'] = true;
        return $result;
    }
    
    public static function createTextLtr($label, $name, $desc = null, $form_group_class = null, $class = null, $required = false, $default = null, $maxchar = 0, $suffix = null, $validate = null) {
        $result = self::createText($label, $name, $desc, $form_group_class, $class, $required, $default, $maxchar, $suffix, $validate);
        $result['class'] = 'kfa-ltr' . (empty($result['class']) ? '' : ' ' . $result['class']);
        return $result;
    }
    
    public static function createTextLangLtr($label, $name, $desc = null, $form_group_class = null, $class = null, $required = false, $default = null, $maxchar = 0, $suffix = null, $validate = null) {
        $result = self::createTextLtr($label, $name, $desc, $form_group_class, $class, $required, $default, $maxchar, $suffix, $validate);
        $result['lang'] = true;
        return $result;
    }
    
    /* createText Conf */
    
    public static function createTextConf($label, $name, $desc = null, $form_group_class = null, $class = null, $required = false, $default = null, $maxchar = 0, $suffix = null, $validate = null) {
        return self::createText($label, self::PREFIX . $name, $desc, $form_group_class, $class, $required, $default, $maxchar, $suffix, $validate);
    }
    
    public static function createTextLangConf($label, $name, $desc = null, $form_group_class = null, $class = null, $required = false, $default = null, $maxchar = 0, $suffix = null, $validate = null) {
        return self::createTextLang($label, self::PREFIX . $name, $desc, $form_group_class, $class, $required, $default, $maxchar, $suffix, $validate);
    }
    
    public static function createTextLtrConf($label, $name, $desc = null, $form_group_class = null, $class = null, $required = false, $default = null, $maxchar = 0, $suffix = null, $validate = null) {
        return self::createTextLtr($label, self::PREFIX . $name, $desc, $form_group_class, $class, $required, $default, $maxchar, $suffix, $validate);
    }
    
    public static function createTextLangLtrConf($label, $name, $desc = null, $form_group_class = null, $class = null, $required = false, $default = null, $maxchar = 0, $suffix = null, $validate = null) {
        return self::createTextLangLtr($label, self::PREFIX . $name, $desc, $form_group_class, $class, $required, $default, $maxchar, $suffix, $validate);
    }
}
