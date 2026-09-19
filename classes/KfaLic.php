<?php
/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, September 2019
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

class KfaDeliveryTimeLic
{
    public static function getActivationKey($module)
    {
        $key = $module->conf . 'ACTIVATION_KEY';
        if (Tools::isSubmit($key)) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
        $activation_key = Configuration::get($key);
        return $activation_key;
    }
    
    public static function generateForm($module, $context)
    {
        $key = $module->conf . 'ACTIVATION_KEY';
        $fields_form1 = array();
        $fields_form1['form'] = array(
            'legend' => array(
                'title' => $module->l('فعال سازی'),
                'icon' => '',
            ),
            'input' => array(
                array(
                    'name' => $key,
                    'type' => 'text',
                    'class' => 'kfa-ltr',
                    'label' => $module->l('کد مجوز'),
                    'suffix' => '<i class="icon-key"></i>',
                ),
            ),
            'submit' => array(
                'icon' => 'process-icon- icon-check',
                'title' => $module->l('فعال سازی'),
                'class' => 'btn btn-default pull-right',
            )
        );
        
        $fields_forms = array($fields_form1);
        
        $helper = new HelperForm();
        $helper->module = $module;
        $helper->name_controller = $module->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$module->name;
        $helper->default_form_language = $context->language->id;
        $helper->allow_employee_form_lang = $context->language->id;
        $helper->title = $module->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = "submit_{$module->name}_activation";
        $helper->fields_value[$key] = Tools::getValue($key, Configuration::get($key));
        
        if (Tools::isSubmit("submit_{$module->name}_activation")) {
            $error = $module->displayError($module->l('کد مجوز وارد شده صحیح نیست.'));
        } else {
            $error = '';
        }
        
        return $error . self::getContent($module) . $helper->generateForm($fields_forms);
    }
    
    private static function getContent($module)
    {
        $domain = '<span style="display: inline-block; direction: ltr;">' . self::getDomain() . '</span>';
        $display_name = 'ماژول ' . $module->displayName;
        $path = _MODULE_DIR_ . $module->name;
        if ($module->author == 'FasleAval.com') {
            $copyright =
                '<div style="text-align: center;" class="tab-pane panel active">' .
                '	<p><a href="https://fasleaval.com/" target="_blank"><img src="' . $path . '/logo.png"></a></p>' .
                "	<h2>$display_name</h2>" .
                '	<p>&nbsp;</p>' .
                "	<p>جهت استفاده از این ماژول، نیاز به مجوز برای $domain دارید.</p>" .
                '	<p>&nbsp;</p>' .
                '	<a class="btn btn-default" href="https://fasleaval.com" title="خرید لایسنس"><i class="process-icon- icon-shopping-cart"></i><div>خرید لایسنس از فصل اول</div></a>' .
                '	<hr />' .
                '	<p>تمامی حقوق این نرم افزار برای <a href="https://fasleaval.com/" target="_blank">فصل اول</a> محفوظ است.</p>' .
                '</div>';
        } else {
            $copyright =
                '<div style="text-align: center;" class="tab-pane panel active">' .
                '	<p><a href="http://systemiha.ir/" target="_blank"><img src="' . $path . '/logo.png"></a></p>' .
                "	<h2>$display_name</h2>" .
                "	<p>جهت استفاده از این ماژول، نیاز به مجوز برای $domain دارید.</p>" .
                '	<hr>' .
                '	<p>تمامی حقوق این نرم افزار برای <a href="http://systemiha.ir/" target="_blank">سیستمی‌ها (کلبه فناوری)</a> محفوظ است.</p>' .
                '</div>';
        }
        return $copyright;
    }

    public static function getDomain($url = null, $return_host = false)
    {
        if (is_null($url)) {
            // قبلا بر اساس دامنه بود
            //$url = $_SERVER['SERVER_NAME'];
            
            // الان بر اساس دامنه و سابفولدر
            $context = Context::getContext();
            $url = trim($context->shop->getBaseURL(), '/');
            if (Language::isMultiLanguageActivated($context->shop->id)) {
                // example: fa
                $iso_code = Language::getIsoById($context->language->id);
                if (substr($url, strlen($url) - strlen($iso_code)) == $iso_code) {
                    $url = trim(substr($url, 0, strlen($url) - strlen($iso_code)), '/');
                }
            }
        }
        if (true) {
            $url = trim(mb_strtolower($url));
        }
        
        if (strpos($url, 'http://') === false && strpos($url, 'https://') === false) {
            $url = 'http://' . $url;
        }
        
        $details = parse_url(str_replace(' ', '', $url));
        
        $host = empty($details['host']) ? '' : trim($details['host']);
        $path = empty($details['path']) ? '' : trim($details['path']);
        
        if ($return_host) {
            if (empty($host) && !empty($path)) {
                $host = explode('/', $path)[0];
            }
            if (strpos($host, 'www.') === 0) {
                $host = substr($host, 4);
            }
            return $host;
        }
        
        if (empty($host) && empty($path)) {
            return '';
        }
        
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }
        
        $path_len = strlen($path);
        $pos = strpos($path, '/index.php');
        if ($pos === $path_len - 10) {
            $path = substr($path, 0, $pos);
        }
        
        $trimmed_path = trim($path, ' /');
        if (empty($trimmed_path)) {
            $final_path = '';
        } else {
            $final_path = $trimmed_path;
        }
        
        $return = $host . (empty($final_path) || empty($host) ? '' : '/') . $final_path;
        if (strpos($return, 'www.') === 0) {
            $return = substr($return, 4);
        }
        
        return $return;
    }
    
    private static function pbkdf2($p, $s, $c = 1000, $kl = 32, $algo = 'sha1')
    {
        // https://github.com/dchymko/.NET--PHP-encryption
        $hl = strlen(hash($algo, null, true)); # Hash length
        $kb = ceil($kl / $hl);              # Key blocks to compute
        $dk = '';                           # Derived key
        
        # Create key
        for ($block = 1; $block <= $kb; $block++) {
            # Initial hash for this block
            $ib = $b = hash_hmac($algo, $s . pack('N', $block), $p, true);

            # Perform block iterations
            for ($i = 1; $i < $c; $i++) {
                # XOR each iterate
                $ib ^= ($b = hash_hmac($algo, $b, $p, true));
            }
            $dk .= $ib; # Append iterated block
        }
        
        # Return derived key of correct length
        return substr($dk, 0, $kl);
    }
    
    public static function validate($activation_key, $password, $name)
    {
        $host = self::getDomain();
        $decrypted = self::decrypt72($activation_key, $password);
        $result = $decrypted == "$name:$host";
        if (!$result && version_compare(PHP_VERSION, '7.2') < 0) {
            $decrypted = self::decrypt71($activation_key, $password);
            $result = $decrypted == "$name:$host";
        }
        return $result;
    }
    
    public static function encrypt($input, $passphrase)
    {
        return self::encrypt72($input, $passphrase);
    }
    
    public static function decrypt($input, $passphrase)
    {
        return self::decrypt72($input, $passphrase);
    }
    
    private static function encrypt71($input, $passphrase, $salt = null, $iv = null)
    {
        // https://github.com/dchymko/.NET--PHP-encryption
        if (is_null($salt)) {
            $salt = openssl_random_pseudo_bytes(32);
        }
        if (is_null($iv)) {
            $iv = openssl_random_pseudo_bytes(32);
        }
        $password = self::pbkdf2($passphrase, $salt);
        $encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $password, $input, MCRYPT_MODE_CBC, $iv);
        return base64_encode($salt . $iv . $encrypted);
    }
    
    private static function decrypt71($input, $passphrase, $salt = null, $iv = null)
    {
        // https://github.com/dchymko/.NET--PHP-encryption
        if (is_null($salt) && is_null($iv) && strlen($input) > 64) {
            $base64_decode = base64_decode($input);
            $salt = substr($base64_decode, 0, 32);
            $iv = substr($base64_decode, 32, 32);
            $input = substr($base64_decode, 64);
        }
        if (empty($salt) || empty($iv)) {
            return null;
        }
        $password = self::pbkdf2($passphrase, $salt);
        $decrypted = @mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $password, $input, MCRYPT_MODE_CBC, $iv);
        return rtrim($decrypted, "\0");
    }
    
    private static function encrypt72($plainText, $key)
    {
        // http://helpdoc.info/mcrypt_rijndael_128-is-not-working-with-php-7-x-with-ccavenue/
        $password = hex2bin(md5($key));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $openMode = openssl_encrypt($plainText, 'AES-128-CBC', $password, OPENSSL_RAW_DATA, $initVector);
        $encryptedText = bin2hex($openMode);
        return $encryptedText;
    }
    
    private static function decrypt72($encryptedText, $key)
    {
        if (!ctype_xdigit($encryptedText)) {
            return null;
        }
        
        // http://helpdoc.info/mcrypt_rijndael_128-is-not-working-with-php-7-x-with-ccavenue/
        $password = hex2bin(md5($key));
        try {
            $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
            $data = @hex2bin($encryptedText);
            $decryptedText = openssl_decrypt($data, 'AES-128-CBC', $password, OPENSSL_RAW_DATA, $initVector);
            return $decryptedText;
        } catch (Exception $e) {
            return null;
        }
    }
}
