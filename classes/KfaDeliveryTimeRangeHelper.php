<?php

class KfaDeliveryTimeRangeHelper {
    
    /**
     *
     * @var KfaDeliveryTime
     */
    private $module;
    
    private $conf;
    private $is_persian = true;
    private $days;
    
    public function __construct(KfaDeliveryTime $module) {
        $this->module = $module;
        $this->conf = $module->conf;
        $this->days = self::getAllDays();
    }
    
    private function getConf($key) {
        return Configuration::get($this->conf . $key);
    }
    
    private function getConfInt($key) {
        return (int) Configuration::get($this->conf . $key);
    }
    
    private function createDeliveryTimes($date, $ranges, $capacity, $is_off, $no_delivery) {
        return array(
            'date'          => $date,
            'date_display'  => $this->is_persian ? KfaPersianDate::gToS($date, false, false) : $date,
            'ranges'        => $ranges ? $this->parseRanges($ranges) : array(),
            'capacity'      => $capacity,
            'is_off'        => $is_off,
            'no_delivery'   => $no_delivery,
        );
    }
    
    private function isDayOff($id_reference, $date) {
        if ($this->is_persian) {
            $date = KfaPersianDate::gToS($date, false, false);
        }
        $date_parts = explode('-', $date);
        if (count($date_parts) !== 3) {
            return false;
        }
        
        $year = (int) $date_parts[0];
        $month = (int) $date_parts[1];
        $day = (int) $date_parts[2];
        return in_array("$month-$day", KfaDeliveryTimeEvent::getYearlyVacations($id_reference))
                || in_array("$year-$month-$day", KfaDeliveryTimeEvent::getTemporaryVacations($id_reference));
    }
    
    private function isDayNoDelivery($id_reference, $date, $same_day_delivery) {
        if (!$same_day_delivery && $date == date('Y-m-d')) {
            $this->log(__FUNCTION__, "$date is no delivery because same day delivery is off and today is $date.");
            return true;
        }
        
        if ($this->is_persian) {
            $date = KfaPersianDate::gToS($date, false, false);
        }
        $date_parts = explode('-', $date);
        if (count($date_parts) !== 3) {
            return false;
        }
        
        $year = (int) $date_parts[0];
        $month = (int) $date_parts[1];
        $day = (int) $date_parts[2];
        
        if (in_array("$month-$day", KfaDeliveryTimeEvent::getYearlyNoDeliveries($id_reference))) {
            $this->log(__FUNCTION__, "$date is no delivery because KfaDeliveryTimeEvent::getYearlyNoDeliveries($id_reference) returned true.");
            return true;
        }
        
        if (in_array("$year-$month-$day", KfaDeliveryTimeEvent::getTemporaryNoDeliveries($id_reference))) {
            $this->log(__FUNCTION__, "$date is no delivery because KfaDeliveryTimeEvent::getTemporaryNoDeliveries($id_reference) returned true");
            return true;
        }
        
        return false;
    }
    
    private function isWeekdayNoDelivery($id_reference, $date) {
        $weekday = strtolower(date('l', strtotime($date)));
        $id_shop = Context::getContext()->shop->id;
        $program = KfaDeliveryTimeCarrier::getCarrierWeekdayProgramByReference($id_reference, $id_shop, $weekday);
        
        if ($program == KfaDeliveryTime::PROGRAM_DEFAULT_OFF) {
            $program = KfaDeliveryTimeCarrier::getCarrierWeekdayProgramByReference($id_reference, $id_shop, 'off');
            return $program === 0;
        }
        
        return $program == KfaDeliveryTime::PROGRAM_NO_DELIVERY;
    }
    
    private function isEmptyRanges($ranges) {
        if (!empty($ranges)) {
            foreach ($ranges as $range) {
                if (empty($range['deleted'])) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    private function getDeliveryTimesByDate($id_reference, $id_reference_off, $id_reference_no_delivery, $date, $same_day_delivery) {
        $this->log('', '');
        $this->log(__FUNCTION__, "STARTED for $date with id_reference: $id_reference, id_reference_no_delivery: $id_reference_no_delivery, same_day_delivery: $same_day_delivery");
        
        if ($this->isDayNoDelivery($id_reference_no_delivery, $date, $same_day_delivery)) {
            $this->log(__FUNCTION__, "$date will be skipped because isDayNoDelivery($id_reference_no_delivery, $date, $same_day_delivery) returned true.");
            return $this->createDeliveryTimes($date, null, '', false, true);
        }
        
        $days = $this->days[$id_reference];
        $strtotime = strtotime($date);
        $weekday = strtolower(date('l', $strtotime));
        $program = (int) $days[$weekday]['program'];
        switch ($program) {
            case KfaDeliveryTime::PROGRAM_DEFAULT:
                $key = 'default';
                break;
            case KfaDeliveryTime::PROGRAM_CUSTOM:
                $key = $weekday;
                break;
            case KfaDeliveryTime::PROGRAM_DEFAULT_OFF:
                if ($days['off']['program']) {
                    $key = 'off';
                } else {
                    $key = ''; // No delivery
                }
                break;
            default:
                $key = ''; // No delivery
                break;
        }
        
        $ranges = $key ? $days[$key]['ranges'] : array();
        
        if (empty($key)) {
            $this->log(__FUNCTION__, "$date will be skipped because program is no delivery.");
            $no_delivery = true;
        } elseif ($this->isEmptyRanges($ranges)) {
            $this->log(__FUNCTION__, "$date will be skipped because isEmptyRanges(ranges) returned true.");
            $no_delivery = true;
        } else {
            $no_delivery = false;
        }
        
        if ($no_delivery) {
            // اگر روز بدون ارسال بود نباید تعطیل بودنش را بررسی کنیم
        } elseif ($this->isDayOff($id_reference_off, date('Y-m-d', $strtotime))) {
            $key = 'off';
            // اگر روز تعطیل بود اطلاعاتش را با اطلاعات روز تعطیل جایگزین می کنیم
            $ranges = $days['off']['ranges'];
            // چون اطلاعات عوض شده اند لازم است دوباره بررسی کنیم که روز بدون ارسال است یا نه
            $no_delivery = $this->isEmptyRanges($ranges);
            if ($no_delivery) {
                $this->log(__FUNCTION__, "$date will be skipped because the key changed to `off` and isEmptyRanges(ranges) returned true.");
            }
        }
        
        $capacity = empty($key) ? 0 : $days[$key]['capacity'];
        $result = $this->createDeliveryTimes($date, $ranges, $capacity, $key == 'off', $no_delivery);
        $this->log(__FUNCTION__, "ENDED for $date with key: `$key` and result: " . var_export($result, true));
        return $result;
    }
    
    /**
     * محاسبه زمان آماده سازی با توجه به تنظیمات موجود در پیکربندی ماژول و محصولات موجود در سبد خرید
     * @param Cart $cart سبد خرید
     * @return int
     */
    private function getPreparationTime(Cart $cart) {
        $result = $this->getConfInt('PREPARATION_TIME');
        
        $type = $this->getConfInt('PREPARATION_TYPE');
        $count = 0;
        if ($type == KfaDeliveryTime::PREPARATION_TIME_BY_QTY) {
            foreach ($cart->getProducts() as $product) {
                $count += (int) $product['cart_quantity'];
            }
        } elseif ($type == KfaDeliveryTime::PREPARATION_TIME_BY_ROWS) {
            $count = count($cart->getProducts());
        }
        
        if ($count > 0) {
            $minimum = $this->getConf('PREPARATION_MINIMUM_VOLUME');
            $step = $this->getConfInt('PREPARATION_EXTRA_STEP');
            if ($step > 0 && $count > $minimum) {
                $extra_items = $count - $minimum;
                $coeff = (int) ($extra_items / $step) + ($extra_items % $step ? 1 : 0);
                $result += $coeff * $this->getConfInt('PREPARATION_EXTRA_TIME');
            }
        }
        
        return $result * 60; // multiply 60 times will convert minutes to seconds
    }
    
    /**
     * بازه هایی را که با توجه به تنظیمات مربوط به زمان آماده سازی از دسترس خارج شده اند غیرفعال یا حذف می کند
     * <br>
     * این که بازه را حذف کند یا غیرفعال کند بستگی به پارامتر سوم دارم
     * @param array $today_times اطلاعات کامل یک روز
     * @param Cart $cart سبد خرید
     * @param bool $remove مشخص کنید که بازه ها حذف شوند یا فقط غیرفعال شوند
     */
    private function trimTodayTimes(Array &$today_times, Cart $cart, $remove) {
        if (empty($today_times['ranges'])) {
            return;
        }
        
        // In comparisions, we use '<=' instead of '<' in order to exclude last second of the end time
        
        $now = strtotime(date('Y-m-d H:i:s'));
        $preparation_time = $this->getPreparationTime($cart);
        foreach ($today_times['ranges'] as $index => $range) {
            $h = $range['end']['h'];
            $m = $range['end']['m'];
            $end_date = strtotime(date("Y-m-d $h:$m:0"));
            if ($end_date <= $now) {
                if ($remove) {
                    unset($today_times['ranges'][$index]);
                    $this->log(__FUNCTION__, "$end_date <= $now and we had removed following range: " . var_export($range, true));
                    continue;
                }
                
                $range['disabled'] = true;
                $this->log(__FUNCTION__, "$end_date <= $now and we had disabled following range: " . var_export($range, true));
            }
            
            if ($range['disabled']) {
                $today_times['ranges'][$index]['disabled'] = true;
            } else {
                // اگر توسط کاربر غیرفعال نشده بود خودمان غیرفعال بودنش را بررسی می کنیم
                $disabled = $end_date - $preparation_time <= $now;
                if ($disabled) {
                    $this->log(__FUNCTION__, "$end_date <= $now and we had disabled following range: " . var_export($range, true));
                }
                // TODO question: Whay not checking $remove parameter and always using disabling range only?
                $today_times['ranges'][$index]['disabled'] = $disabled;
            }
        }
    }
    
    /**
     * تشخیص می دهد که آیا حداقل یکی از زمان های تحویل فعال است یا خیر
     * @param array $options زمان های تحویل موجود در یک روز
     * @return bool
     */
    private function anyActiveOption($options) {
        if (!empty($options)) {
            foreach ($options as $option) {
                if (empty($option['disabled'])) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * همه ی بازه ها را به عنوان غیرفعال علامت گذاری می کند
     * @param array $options
     */
    private function markOptionsAsDisabled(&$options) {
        if (!empty($options)) {
            foreach (array_keys($options) as $index) {
                $options[$index]['disabled'] = true;
            }
        }
    }
    
    private function getApproximateDay($id_reference, $id_reference_off, $id_reference_no_delivery, $start_date, $target, $data) {
        $days = 0;
        $counted = 0;
        $same_day_delivery = KfaDeliveryTimeCarrier::getCarrierSameDayDelivery($id_reference);
        do {
            $date = date('Y-m-d', strtotime("$start_date + $days days"));
            $can_add = true;
            switch (date('w', strtotime($date))) {
                case 0:
                    if ($data['approximate_add_sun']) {
                        $can_add = false;
                    }
                    break;
                case 1:
                    if ($data['approximate_add_mon']) {
                        $can_add = false;
                    }
                    break;
                case 2:
                    if ($data['approximate_add_tue']) {
                        $can_add = false;
                    }
                    break;
                case 3:
                    if ($data['approximate_add_wed']) {
                        $can_add = false;
                    }
                    break;
                case 4:
                    if ($data['approximate_add_thu']) {
                        $can_add = false;
                    }
                    break;
                case 5:
                    if ($data['approximate_add_fri']) {
                        $can_add = false;
                    }
                    break;
                case 6:
                    if ($data['approximate_add_sat']) {
                        $can_add = false;
                    }
                    break;
            }
            if ($can_add && $data['approximate_add_off'] && $this->isDayOff($id_reference_off, $date)) {
                $can_add = false;
            }
            if ($can_add && $data['approximate_add_no_delivery'] && $this->isDayNoDelivery($id_reference_no_delivery, $date, $same_day_delivery)) {
                $can_add = false;
            }
            if ($can_add) {
                $counted++;
            }
            $days++;
        } while ($counted <= $target);
        return $date;
    }
    
    /**
     * 
     * @param int $id_reference مرجع حامل
     * @param int $id_reference_off مرجع حامل روزهای تعطیل
     * @param int $id_reference_no_delivery مرجع حامل روزهای بدون ارسال
     * @param int $id_carrier شناسه حامل
     * @param string $start_date تاریخ شروع محاسبات
     * @param int $delay زمان تحویل اضافه محصولات
     * @return array
     */
    private function getApproximateDeliveryTime($id_reference, $id_reference_off, $id_reference_no_delivery, $id_carrier, $start_date, $delay) {
        $data = KfaDeliveryTimeCarrier::getCarrierApproximateData($id_reference);
        $delivery_time = array(
            'date'          => $start_date,
            'date_start'    => "$start_date 00:00:00",
            'date_end'      => "$start_date 23:59:59",
            'capacity'      => $data['approximate_capacity'],
            'data'          => KfaDeliveryTimeCarrier::getAvailabilityDataFromRawString($data['approximate_data']),
        );
        $diff = $this->isUnavailable($delivery_time, $id_reference, $id_reference_off, $id_reference_no_delivery, true, true);
        
        if ($diff > 0) {
            $days = (int) ($diff / 86400) + ($diff % 86400 ? 1 : 0);
            $new_value = date('Y-m-d', strtotime("$start_date + $days days"));
            $this->log(__FUNCTION__, "start_date is `$start_date` but will change to `$new_value` because isUnavailable() returned `$diff ($days days)`.");
            $start_date = $new_value;
        }
        
        if ($delay) {
            $new_value = date('Y-m-d', strtotime("$start_date + $delay days"));
            $this->log(__FUNCTION__, "start_date is `$start_date` but will change to `$new_value` because delay is `$delay`.");
            $start_date = $new_value;
        }
        
        if ($this->isOutOfCapacity($delivery_time, $id_carrier)) {
            $new_value = date('Y-m-d', strtotime("$start_date + 1 days"));
            $this->log(__FUNCTION__, "start_date is `$start_date` but will change to `$new_value` because isOutOfCapacity() returned true.");
            $start_date = $new_value;
        }
        
        $process = $this->getApproximateDay($id_reference
                , $id_reference_off
                , $id_reference_no_delivery
                , $start_date
                , 0
                , $data);
        $from = $this->getApproximateDay($id_reference
                , $id_reference_off
                , $id_reference_no_delivery
                , $process
                , $data['approximate_min_day']
                , $data);
        $to = $this->getApproximateDay($id_reference
                , $id_reference_off
                , $id_reference_no_delivery
                , $process
                , $data['approximate_max_day']
                , $data);
        return array(
            'approximate'   => true,
            'from'          => "$from 00:00:00",
            'to'            => "$to 23:59:59",
            'process'       => $process,
            'format'        => $data['approximate_format_fo'],
        );
    }
    
    private function getDelayedShippingData($id_product, $id_product_attribute) {
        if (Module::isInstalled('kfadelayedshipping') && Module::isEnabled('kfadelayedshipping')) {
            if (($module = Module::getInstanceByName('kfadelayedshipping'))) {
                if ($module->isInProgram()) {
                    $data = $module->getProductData($id_product, KfaDelayedShipping::TYPE_REMAINING);
                    if (array_key_exists($id_product_attribute, $data)) {
                        return $data[$id_product_attribute];
                    }
                }
            }
        }
        return false;
    }
    
    private function getAdditionalDeliveryTimes($cart) {
        if (!$this->module->getConf('ADDITIONAL_DELIVERY_TIMES') || !Validate::isLoadedObject($cart)) {
            return 0;
        }
        
        $context = Context::getContext();
        $id_lang = (int) $context->language->id;
        $id_shop = (int) $context->shop->id;
        $id_cart = (int) $cart->id;
        $products = KfaDeliveryTimeAdditionalDeliveryTimes::getCartProducts($this->module->is17, $id_shop, $id_lang, $id_cart);
        if (!$products) {
            return 0;
        }

        $use_default_information = 1;
        $use_product_information = 2;
        $default_labels = KfaDeliveryTimeAdditionalDeliveryTimes::getDefaultLabels($this->module, $id_lang);
        $max_delay = 0;
        foreach ($products as $product) {
            $data = $this->getDelayedShippingData($product['id_product'], $product['id_product_attribute']);
            if ($data === false) {
                $available = $product['quantity'] > 0;
                $oos = !$available && Product::isAvailableWhenOutOfStock($product['out_of_stock']);
            } else {
                $available = $data > 0;
                $oos = !$available;
            }
            $time = '';
            $option = $product['additional_delivery_times'];
            if ($available) {
                if ($option == $use_default_information) {
                    $time = $default_labels['in'];
                } elseif ($option == $use_product_information) {
                    $time = $product['delivery_in_stock'];
                }
            } elseif ($oos) {
                if ($option == $use_default_information) {
                    $time = $default_labels['out'];
                } elseif ($option == $use_product_information) {
                    $time = $product['delivery_out_stock'];
                }
            }
            if (empty(trim($time))) {
                continue;
            }
            
            $clean_time = KfaDeliveryTime::correctDigits(trim($time));
            $delay = self::extractDaysFromDelivery($clean_time);
            if ($delay > $max_delay) {
                $max_delay = $delay;
            }
        }
        return $max_delay;
    }
    
    private static function extractDaysFromDelivery($additional_delivery_times) {
        if (empty($additional_delivery_times)) {
            return 0;
        }
        
        if (is_numeric($additional_delivery_times)) {
            return (int) $additional_delivery_times;
        }
        
        if (is_string($additional_delivery_times)) {
            if (preg_match("~^\d{4}(-\d{1,2}){2}$~", $additional_delivery_times)) {
                if (KfaDeliveryTime::calendarIsPersian()) {
                    if (($date = KfaPersianDate::sToG($additional_delivery_times)) instanceof DateTime) {
                        $date = $date->format('Y-m-d 00:00:00');
                    }
                } else {
                    $date = "$additional_delivery_times 00:00:00";
                }
                if (!empty($date)) {
                    // TODO: get now as a parameter
                    $now = date('Y-m-d 00:00:00');
                    $object1 = date_create($now);
                    $object2 = date_create($date);
                    if ($object1 >= $object2) {
                        return 0;
                    }
                    
                    return date_diff($object1, $object2, false)->days;
                }
            }
            
            $pattern = '~(\d+)\s*روز(?:\s*کاری)?(?:\s*دیگر)?$~u';
            $matches = null;
            preg_match_all($pattern, $additional_delivery_times, $matches);
            if (is_array($matches) && !empty($matches[1])) {
                return (int) $matches[1][0];
            }
        }
        
        return 0;
    }
    
    /**
     * 
     * @param int $program_off
     * @param int $id_carrier
     * @return int
     */
    private function detectIdReferenceOff($program_off, $id_carrier) {
        switch ($program_off) {
            case KfaDeliveryTime::CARRIER_EVENTS_DEFAULT:
                // استفاده از رویدادهای پیش فرض
                return 0;
                
            case KfaDeliveryTime::CARRIER_EVENTS_CUSTOM:
                // دلخواه
                return KfaDeliveryTimeCarrier::getCarrierReference($id_carrier);
                
            default;
                // تعطیلی ندارد
                return -1;
        }
    }
    
    /**
     * 
     * @param int $program_no_delivery
     * @param int $id_carrier
     * @return int
     */
    private function detectIdReferenceNoDelivery($program_no_delivery, $id_carrier) {
        switch ($program_no_delivery) {
            case KfaDeliveryTime::CARRIER_EVENTS_DEFAULT:
                return 0;
                
            case KfaDeliveryTime::CARRIER_EVENTS_CUSTOM:
                return KfaDeliveryTimeCarrier::getCarrierReference($id_carrier);
                
            default;
                return -1;
        }
    }
    
    private function log($function, $message) {
        $class = __CLASS__;
        if (empty($message)) {
            KfaDeliveryTime::logCalculations('');
        } else {
            KfaDeliveryTime::logCalculations("$class > $function > $message");
        }
    }
    
    public function getDeliveryTimes($cart, $id_carrier, $start_date, $disabled_days = null, $is_admin = false, $future_days_count = false) {
        $start_date_log = is_array($start_date) ? print_r($start_date, true) : $start_date;
        $this->log(__FUNCTION__, "STARTED for cart: $cart->id, id_carrier: $id_carrier, start_date: $start_date_log, is_admin: $is_admin");
        
        $carrier_info = KfaDeliveryTimeCarrier::getCarrierPrograms($id_carrier, $cart->id_shop);
        $carrier = new Carrier($id_carrier);
        $carrier_info['instance'] = array(
            'id'                    => $carrier->id,
            'name'                  => $carrier->name,
            'id_reference'          => $carrier->id_reference,
            'is_module'             => $carrier->is_module,
            'external_module_name'  => $carrier->external_module_name,
        );
        $this->log(__FUNCTION__, 'carrier_info: ' . var_export($carrier_info, true));
        
        $id_reference_off = $this->detectIdReferenceOff((int) $carrier_info['program_off'], $id_carrier);
        $this->log(__FUNCTION__, "id_reference_off: $id_reference_off");
        
        $id_reference_no_delivery = $this->detectIdReferenceNoDelivery((int) $carrier_info['program_no_delivery'], $id_carrier);
        $this->log(__FUNCTION__, "id_reference_no_delivery: $id_reference_no_delivery");
        
        $delay = $this->getAdditionalDeliveryTimes($cart);
        $this->log(__FUNCTION__, "products additional delivery times: $delay");
        
        switch ($carrier_info['program']) {
            case KfaDeliveryTime::CARRIER_PROGRAM_DEFAULT:
                $fixed_option = KfaDeliveryTimeCarrier::getCarrierFixedOption(KfaDeliveryTimeCarrier::getCarrierReference($id_carrier));
                $id_reference = 0;
                $this->log(__FUNCTION__, "program: default, id_reference: $id_reference");
                break;
                
            case KfaDeliveryTime::CARRIER_PROGRAM_CUSTOM:
                $id_reference = KfaDeliveryTimeCarrier::getCarrierReference($id_carrier);
                $this->log(__FUNCTION__, "program: custom, id_reference: $id_reference");
                $fixed_option = KfaDeliveryTimeCarrier::getCarrierFixedOption($id_reference);
                break;
                
            case KfaDeliveryTime::CARRIER_PROGRAM_APPROXIMATE:
                $id_reference = KfaDeliveryTimeCarrier::getCarrierReference($id_carrier);
                $this->log(__FUNCTION__, "program: approximate, id_reference: $id_reference");
                if (is_array($start_date)) {
                    $result = array(
                        'approximate' => true,
                        'multiple_date' => array(),
                    );
                    for ($i = 0; $i < count($start_date); $i++) {
                        $result['multiple_date'][$start_date[$i]] = $this->getApproximateDeliveryTime($id_reference, $id_reference_off, $id_reference_no_delivery, $id_carrier, $start_date[$i], $delay);
                    }
                } else {
                    $result = $this->getApproximateDeliveryTime($id_reference, $id_reference_off, $id_reference_no_delivery, $id_carrier, $start_date, $delay);
                }
                $this->log(__FUNCTION__, "ENDED with result: " . var_export($result, true));
                return $result;
                
            default;
                $this->log(__FUNCTION__, 'ENDED');
                return null;
        }
        $same_day_delivery = $is_admin || KfaDeliveryTimeCarrier::getCarrierSameDayDelivery($id_reference);
        $this->log(__FUNCTION__, "same_day_delivery: $same_day_delivery");
        
        $first_day = $this->getDeliveryTimesByDate($id_reference, $id_reference_off, $id_reference_no_delivery, $start_date, $same_day_delivery);
        $this->log(__FUNCTION__, 'first day of calculations: ' . var_export($first_day, true));
        $today = date('Y-m-d');
        $today_obj = new DateTime($today);
        $this->log(__FUNCTION__, "today: $today");
        
        if (date('Y-m-d', strtotime($first_day['date'])) == $today) {
            // اگر اولین روز محاسبات برابر امروز بود
            // با توجه به تنظیمات مربوط به زمان آماده سازی ممکن است نیاز به حذف برخی از بازه های امروز باشد
            // هرس کردن بازه های امروز توسط تابع زیر انجام می شود
            $this->trimTodayTimes($first_day, $cart, !$is_admin);
        }
        
        // اکنون که محاسبات اولین روز کامل انجام شده اند می توانیم لیست بازه های این روز را بسازیم
        $first_day['options'] = array();
        foreach ($first_day['ranges'] as $range) {
            if ($range['deleted']) {
                $this->log(__FUNCTION__, 'following range for first day was ignored because it was deleted: ' . var_export($range, true));
                continue;
            }
            
            $first_day['options'][] = $this->createDeliveryTimeFromRange($first_day
                    , $range
                    , $id_carrier
                    , $id_reference
                    , $id_reference_off
                    , $id_reference_no_delivery
                    , $same_day_delivery);
        }
        
        $first_day_any_active_option = $this->anyActiveOption($first_day['options']);
        if ($first_day_any_active_option && $delay > 0) {
            $this->markOptionsAsDisabled($first_day['options']);
            $first_day_any_active_option = false;
            $delay--;
            $this->log(__FUNCTION__, "products additional delivery times decreased to: $delay for $first_day[date_display]");
        }
        
        if ($future_days_count === false) {
            $future_days_count = KfaDeliveryTimeCarrier::getCarrierFutureDays($id_reference);
        }
        
        if (strtotime($start_date) < strtotime($today)) {
            $date_obj = new DateTime($start_date);
            $diff = $date_obj->diff($today_obj)->format('%a');
            if ($diff > 0) {
                // باگ داشت غیرفعال شد
                // باعث می شد کلی روز از آینده لود بشه
                //$future_days_count += $diff;
            }
        }
        
        if (is_null($disabled_days)) {
            $disabled_days = $this->getConf('DISABLED_DAYS');
        }
        switch ($disabled_days) {
            case KfaDeliveryTime::DISABLED_DAYS_DISPLAY:
                $include_disable_days = true;
                $count_disable_days = false;
                break;
            case KfaDeliveryTime::DISABLED_DAYS_DISPLAY_AND_COUNT:
                $include_disable_days = true;
                $count_disable_days = true;
                break;
            default:
                $include_disable_days = false;
                $count_disable_days = false;
                break;
        }
        
        $days = 1;
        $future_days = array();
        for ($i = 1; $i <= $future_days_count; $i++) {
            do {
                $next_day_date = date('Y-m-d', strtotime("$start_date + $days days"));
                
                // اگر تعداد روزهای لود شده زیاد شد
                if (count($future_days) > 30) {
                    $date_obj = new DateTime($next_day_date);
                    $diff = $date_obj->diff($today_obj)->format('%a');
                    // اگر تاریخ بعدی خیلی قدیمی بود از آن صرفنظر می کنیم
                    if ($diff > 30) {
                        $days++;
                        $skip = true;
                        continue;
                    }
                }
                $next_date_info = $this->getDeliveryTimesByDate($id_reference, $id_reference_off, $id_reference_no_delivery, $next_day_date, $same_day_delivery);
                
                $next_date_info['options'] = array();
                foreach ($next_date_info['ranges'] as $range) {
                    if ($range['deleted']) {
                        $this->log(__FUNCTION__, "following range for $next_date_info[date]($next_date_info[date_display]) was ignored because it was deleted: " . var_export($range, true));
                        continue;
                    }
                    
                    $next_date_info['options'][] = $this->createDeliveryTimeFromRange($next_date_info
                            , $range
                            , $id_carrier
                            , $id_reference
                            , $id_reference_off
                            , $id_reference_no_delivery
                            , $same_day_delivery);
                }
                
                $this->log(__FUNCTION__, "Options for $next_date_info[date]: " . var_export($next_date_info['options'], true));
                
                // ابتدا چک می کنیم آیا این روز دارای گزینه ی فعالی هست یا خیر
                $any_active_option = $this->anyActiveOption($next_date_info['options']);
                
                // سپس اگر دارای گزینه ی فعالی بود اما به دلیل تأخیر مربوط به زمان ارسال محصولات نیاز به گذر از این روز بود
                if ($any_active_option && $delay > 0) {
                    $this->markOptionsAsDisabled($next_date_info['options']);
                    // متغیر مربوط به دارا بودن گزینه ی فعال در این روز را دستکاری می کنیم که در ادامه ی محاسبات فرض شود امروز هیچ گزینه ای ندارد
                    $any_active_option = false;
                    // در آخر هم تعداد روزهای مربوط به تأخیر زمان ارسال محصولات را یکی کم می کنیم
                    $delay--;
                    $this->log(__FUNCTION__, "products additional delivery times decreased to: $delay for $next_date_info[date]($next_date_info[date_display])");
                }
                
                // اگر این روز دارای گزینه ی فعال باشد یا تنظیمات طوری باشد که بتوان روزهای بدون گزینه ی فعال را هم نمایش داد
                if ($any_active_option || $include_disable_days) {
                    // این روز را به لیست خروجی اضافه می کنیم
                    $future_days[] = $next_date_info;
                    $this->log(__FUNCTION__, "$next_date_info[date]($next_date_info[date_display]) inserted at end of future_days list.");
                }
                
                if (!$any_active_option && !$count_disable_days) {
                    $skip = true;
                    $this->log(__FUNCTION__, "$next_date_info[date]($next_date_info[date_display]) skipped because any_active_option and count_disable_days are false.");
                } else {
                    $skip = $next_date_info['no_delivery'];
                    if ($skip) {
                        $this->log(__FUNCTION__, "$next_date_info[date]($next_date_info[date_display]) skipped because no_delivery is 1");
                    }
                }
                
                $days++;
            } while ($skip && $days < 365);
        }
        
        if ($first_day_any_active_option && KfaDeliveryTimeCarrier::getCarrierFutureDaysWhenNoDelivery($id_reference) && !$is_admin) {
            // اگر حامل طوری تنظیم شده بود که روزهای آینده را فقط هنگام از دسترس خارج شدن امروز نمایش دهد
            // پس در صورت در دسترس بودن امروز دیگر نیازی به نمایش روزهای آینده نیست
            // و خروجی را تبدیل به آرایه ای می کنیم که فقط شامل امروز است
            $future_days = array($first_day);
            $this->log(__FUNCTION__, "future_days continas $first_day[date_display] because getCarrierFutureDaysWhenNoDelivery is on.");
        } elseif ($first_day_any_active_option || $include_disable_days) {
            // اگر امروز دارای بازه ی فعال بود
            // یا تنظیمات طوری باشند که روزهای غیرفعال را هم بتوان نشان داد
            // امروز را به ابتدای لیست خروجی اضافه می کنیم
            array_unshift($future_days, $first_day);
            $this->log(__FUNCTION__, "$first_day[date_display] inserted at beginning of future_days list.");
        }
        
        // روزهای حاوی اطلاعات اضافه ای هستند که از آن اطلاعات برای محاسبه بازه های در دسترس استفاده شده است
        // اکنون که به پایان محاسبات رسیده ایم فقط بازه های زمانی را نیاز داریم
        // به هم دلیل از اطلاعات موجود در هر روز فقط بازه های زمانی اش را به خروجی نهایی اضافه می کنیم
        $result = array();
        foreach ($future_days as $future_day) {
            foreach ($future_day['options'] as $option) {
                $result[] = $option;
            }
        }
        
        if (isset($fixed_option) && $fixed_option['active']) {
            // اگر گزینه ثابت برای این حامل تعریف شده بود
            // بسته به تنظیمات باید آن را به ابتدا یا انتهای لیست خروجی اضافه کنیم
            $date = $fixed_option['date'];
            $fixed_item = array(
                'fixed' => true,
                'value' => $fixed_option['value'],
                'date' => $date,
                'date_start' => "$date 00:00:00",
                'date_end' => "$date 00:00:00",
                'date_display' => '',
                'start' => '00:00',
                'end' => '00:00',
                'disabled' => 0,
                'capacity' => 0,
                'description' => '',
                'data' => array(
                    'availability_active' => 0,
                    'availability_h' => 0,
                    'availability_m' => 0,
                    'availability_interval' => 0,
                    'availability_unit' => '',
                ),
                'unavailable' => 0,
                'out_of_capacity' => 0,
            );
            if ($fixed_option['active'] == 1) {
                // در صورتی که مقدار اکتیو یک باشد گزینه ثابت را به ابتدای لیست اضافه می کنیم
                array_unshift($result, $fixed_item);
            } else {
                // در صورتی که مقدار اکتیو دو باشد گزینه ثابت را به انتهای لیست اضافه می کنیم
                $result[] = $fixed_item;
            }
        }
        
        $this->log(__FUNCTION__, "ENDED with result: " . var_export($result, true));
        return $result;
    }
    
    private function createDeliveryTimeFromRange($day, $range, $id_carrier, $id_reference, $id_reference_off, $id_reference_no_delivery, $same_day_delivery) {
        if (empty($range['capacity'])) {
            $capacity = $day['capacity'];
        } else {
            $capacity = $range['capacity'];
        }
        $delivery_time = array(
            'date'          => $day['date'],
            'date_start'    => $this->createDate($day, $range['start']),
            'date_end'      => $this->createDate($day, $range['end']),
            'date_display'  => $day['date_display'],
            'start'         => $range['start']['display'],
            'end'           => $range['end']['display'],
            'disabled'      => !empty($range['disabled']) ? 1 : 0,
            'capacity'      => $capacity,
            'description'   => $range['description'],
            'data'          => $range['data'],
        );
        
        if ($this->isUnavailable($delivery_time, $id_reference, $id_reference_off, $id_reference_no_delivery, $same_day_delivery, false)) {
            $delivery_time['unavailable'] = 1;
            $delivery_time['out_of_capacity'] = 0;
            $delivery_time['disabled'] = 1;
        } elseif ($this->isOutOfCapacity($delivery_time, $id_carrier)) {
            $delivery_time['unavailable'] = 0;
            $delivery_time['out_of_capacity'] = 1;
            $delivery_time['disabled'] = 1;
        } else {
            $delivery_time['unavailable'] = 0;
            $delivery_time['out_of_capacity'] = 0;
        }
        
        return $delivery_time;
    }
    
    private function createDate($day, $time) {
        $h = $time['h'] < 10 ? "0$time[h]" : $time['h'];
        $m = $time['m'] < 10 ? "0$time[m]" : $time['m'];
        return "$day[date] $h:$m:00";
    }
    
    private function parseTime($hour, $minute) {
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return false;
        }
        
        return array(
            'h' => $hour,
            'm' => $minute,
            'display' => str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minute, 2, '0', STR_PAD_LEFT),
        );
    }
    
    private function timeToInt($time) {
        return $time['h'] * 60 + $time['m'];
    }
    
    private function parseRanges($ranges) {
        $result = array();
        if (empty($ranges)) {
            return $result;
        }
        
        foreach ($ranges as $range) {
            $start = $this->parseTime($range['from_h'], $range['from_m']);
            $end = $this->parseTime($range['to_h'], $range['to_m']);
            if (!($start && $end) || $this->timeToInt($start) >= $this->timeToInt($end)) {
                continue;
            }
            
            $result[] = array(
                'data'          => $range['data'],
                'description'   => $range['description'],
                'capacity'      => $range['capacity'],
                'deleted'       => $range['deleted'],
                'disabled'      => $range['disabled'],
                'start'         => $start,
                'end'           => $end,
            );
        }
        return $result;
    }
    
    private function getYearlyNoDeliveries() {
        $events = $this->getConf('YEARLY_NO_DELIVERIES');
        $lines = explode("\n", $events);
        $result = array();
        foreach ($lines as $line) {
            $line_parts = explode('//', trim($line));
            $date_parts = explode('-', $line_parts[0]);
            if (count($date_parts) !== 2) {
                continue;
            }
            
            $month = (int) $date_parts[0];
            $day = (int) $date_parts[1];
            $result[] = "$month-$day";
        }
        return $result;
    }
    
    private function getTemporaryNoDeliveries() {
        $events = $this->getConf('TEMPORARY_NO_DELIVERIES');
        $lines = explode("\n", $events);
        $result = array();
        foreach ($lines as $line) {
            $line_parts = explode('//', trim($line));
            $date_parts = explode('-', $line_parts[0]);
            if (count($date_parts) !== 3) {
                continue;
            }
            
            $year = (int) $date_parts[0];
            $month = (int) $date_parts[1];
            $day = (int) $date_parts[2];
            $result[] = "$year-$month-$day";
        }
        return $result;
    }
    
    public static function persianWeekday($expression) {
        switch (date('w', strtotime($expression))) {
            case 0:
                $w = 'یکشنبه';
                break;
            case 1:
                $w = 'دوشنبه';
                break;
            case 2:
                $w = 'سه‌شنبه';
                break;
            case 3:
                $w = 'چهارشنبه';
                break;
            case 4:
                $w = 'پنجشنبه';
                break;
            case 5:
                $w = 'جمعه';
                break;
            case 6:
                $w = 'شنبه';
                break;
        }
        return $w;
    }
    
    public static function getAllDays() {
        $id_shop = Context::getContext()->shop->id;
        $prefix = _DB_PREFIX_;
        $rows_day = Db::getInstance()->executeS("SELECT * FROM `{$prefix}kfadeliverytime_day` WHERE `id_shop` = $id_shop");
        $rows_range = Db::getInstance()->executeS("SELECT * FROM `{$prefix}kfadeliverytime_range` WHERE `id_shop` = $id_shop ORDER BY `from_h`, `from_m`");
        $days = array(
            'default' => array(
                'title' => 'پیش‌فرض',
                'program' => 0,
                'same' => 0,
                'capacity' => '',
                'icon' => 'asterisk',
                'tpl' => 'default',
                'ranges' => array(),
                'data' => array(),
            ),
            'saturday' => array(
                'title' => 'شنبه',
                'program' => 0,
                'same' => 0,
                'capacity' => '',
                'icon' => 'calendar',
                'tpl' => 'weekday',
                'ranges' => array(),
                'data' => array(),
            ),
            'sunday' => array(
                'title' => 'یک‌شنبه',
                'program' => 0,
                'same' => 0,
                'capacity' => '',
                'icon' => 'calendar',
                'tpl' => 'weekday',
                'ranges' => array(),
                'data' => array(),
            ),
            'monday' => array(
                'title' => 'دوشنبه',
                'program' => 0,
                'same' => 0,
                'capacity' => '',
                'tpl' => 'weekday',
                'icon' => 'calendar',
                'ranges' => array(),
                'data' => array(),
            ),
            'tuesday' => array(
                'title' => 'سه‌شنبه',
                'program' => 0,
                'same' => 0,
                'capacity' => '',
                'icon' => 'calendar',
                'tpl' => 'weekday',
                'ranges' => array(),
                'data' => array(),
            ),
            'wednesday' => array(
                'title' => 'چهارشنبه',
                'program' => 0,
                'same' => 0,
                'capacity' => '',
                'icon' => 'calendar',
                'tpl' => 'weekday',
                'ranges' => array(),
                'data' => array(),
            ),
            'thursday' => array(
                'title' => 'پنج‌شنبه',
                'program' => 0,
                'same' => 0,
                'capacity' => '',
                'icon' => 'calendar',
                'tpl' => 'weekday',
                'ranges' => array(),
                'data' => array(),
            ),
            'friday' => array(
                'title' => 'جمعه',
                'program' => 0,
                'same' => 0,
                'capacity' => '',
                'icon' => 'calendar',
                'tpl' => 'weekday',
                'ranges' => array(),
                'data' => array(),
            ),
            'off' => array(
                'title' => 'تعطیل',
                'program' => 0,
                'same' => 0,
                'capacity' => '',
                'icon' => 'coffee',
                'tpl' => 'off',
                'ranges' => array(),
                'data' => array(),
            ),
        );
        
        $result = array(0 => $days);
        $references = self::getCarrierReferences();
        foreach ($references as $id_reference) {
            $result[$id_reference] = $days;
        }
        
        if ($rows_day) {
            foreach ($rows_day as $row) {
                $day = $row['name'];
                if (!array_key_exists($day, $days)) {
                    continue;
                }
                
                $id_reference = (int) $row['id_reference'];
                if (!isset($result[$id_reference])) {
                    continue;
                }
                
                $result[$id_reference][$day]['program'] = (int) $row['program'];
                $result[$id_reference][$day]['same'] = (int) $row['same'];
                $result[$id_reference][$day]['capacity'] = $row['capacity'];
                
                if (!$rows_range) {
                    continue;
                }
                
                foreach ($rows_range as $row) {
                    if ($row['name'] != $day || $row['id_reference'] != $id_reference) {
                        continue;
                    }
                    
                    $string = empty($row['data']) ? '' : $row['data'];
                    $result[$id_reference][$day]['ranges'][] = array(
                        'from_h'        => (int) $row['from_h'],
                        'from_m'        => (int) $row['from_m'],
                        'to_h'          => (int) $row['to_h'],
                        'to_m'          => (int) $row['to_m'],
                        'deleted'       => $row['deleted'] ? 1 : 0,
                        'disabled'      => $row['disabled'] ? 1 : 0,
                        'capacity'      => $row['capacity'],
                        'description'   => $row['description'],
                        'data'          => KfaDeliveryTimeCarrier::getAvailabilityDataFromRawString($string),
                    );
                }
            }
        }
        return $result;
    }
    
    private static function getCarrierReferences() {
        $result = array();
        $prefix = _DB_PREFIX_;
        $rows = Db::getInstance()->executeS("SELECT DISTINCT `id_reference` FROM `{$prefix}carrier` WHERE `deleted` = 0");
        if ($rows) {
            foreach ($rows as $row) {
                $result[] = (int) $row['id_reference'];
            }
        }
        return $result;
    }
    
    public static function update($day, $ranges, $id_reference, $id_shop) {
        $name = $day['name'];
        $result = true;
        
        $where = "name = '$name' AND `id_reference` = $id_reference AND `id_shop` = $id_shop";
        $result &= Db::getInstance()->delete('kfadeliverytime_day', $where);
        $result &= Db::getInstance()->delete('kfadeliverytime_range', $where);
        
        $result &= Db::getInstance()->insert('kfadeliverytime_day', $day);
        $result &= Db::getInstance()->insert('kfadeliverytime_range', $ranges);
        
        return $result;
    }
    
    private function isValidHour($h) {
        return is_numeric($h) && $h >= 0 && $h <= 23;
    }
    
    private function isValidMinute($m) {
        return is_numeric($m) && $m >= 0 && $m <= 59;
    }
    
    private function getAvailabilityTime($data) {
        $h = $data['availability_h'];
        $m = $data['availability_m'];
        
        if ($this->isValidHour($h) && $this->isValidMinute($m)) {
            return "$h:$m:00";
        }
        
        if ($this->isValidHour($h)) {
            return "$h:00:00";
        }
        
        return 'H:i:s';
    }
    
    /**
     * 
     * @param array $delivery_time
     * @param int $id_reference
     * @param int $id_reference_off
     * @param int $id_reference_no_delivery
     * @param int $same_day_delivery
     * @param boolean $return_diff
     * @return boolean|int
     */
    private function isUnavailable($delivery_time, $id_reference, $id_reference_off, $id_reference_no_delivery, $same_day_delivery, $return_diff) {
        if (empty($data = $delivery_time['data'])) {
            $this->log(__FUNCTION__, "return false for $delivery_time[date_start] because data is empty.");
            return false;
        }
        
        if (!$data['availability_active']) {
            $this->log(__FUNCTION__, "return false for $delivery_time[date_start] because active is 0.");
            return false;
        }
        
        if ($data['availability_interval'] === '' && $data['availability_h'] === '' && $data['availability_m'] === '') {
            $this->log(__FUNCTION__, "return false for $delivery_time[date_start] because interval & h & m are 0.");
            return false;
        }
        
        $availability_do_not_skip_off_days = $this->module->getConf('AVAILABILITY_DO_NOT_SKIP_OFF_DAYS');
        $availability_do_not_skip_no_delivery_days = $this->module->getConf('AVAILABILITY_DO_NOT_SKIP_NO_DELIVERY_DAYS');
        $availability_do_not_skip_no_delivery_weekdays = $this->module->getConf('AVAILABILITY_DO_NOT_SKIP_NO_DELIVERY_WEEKDAYS');
        
        $interval = (int) $data['availability_interval'];
        $date_start = $delivery_time['date_start'];
        switch ($data['availability_unit']) {
            case 'm':
                $last_opportunity = date('Y-m-d H:i:s', strtotime("$date_start - $interval minutes"));
                break;
            
            case 'h':
                $last_opportunity = date('Y-m-d H:i:s', strtotime("$date_start - $interval hours"));
                break;
            
            case 'd':
                $date = $date_start;
                $interval_passed = 0;
                while ($interval_passed < $interval) {
                    $date = date("Y-m-d", strtotime("$date - 1 days"));
                    //$skip = $this->isDayNoDelivery($id_reference_no_delivery, $date, $same_day_delivery) TODO: checking
                    
                    $skip = false;
                    if ($return_diff) {
                        /**
                         * وقتی که
                         * return_diff = true
                         * باشد وظیفه این تابع این است که بگوید با توجه به حداکثر زمان در دسترس بودن
                         * چه مدت زمانی باید سپری شود تا اولین تاریخ در دسترس به دست آید
                         * در نتیجهتعطیل بودن یا بدون ارسال بودن روزهای گذشته نباید شمارش شود
                         * فقط مدت زمان نیاز به سپری شدن در آینده حساب می شود
                         * سپس در جایی که این تابع فراخوانی شده
                         * به همان اندازه رو به جلو شمارش خواهد شد تا اولین تاریخ در دسترس به دست آید
                         */
                    } else {
                        if ($this->isDayNoDelivery($id_reference_no_delivery, $date, true)) {
                            if ($availability_do_not_skip_no_delivery_days) {
                                $this->log(__FUNCTION__, "$date did not skippied even isDayNoDelivery returned true for id_reference_no_delivery $id_reference_no_delivery because AVAILABILITY_DO_NOT_SKIP_NO_DELIVERY_DAYS is active in configuration.");
                            } else {
                                $skip = true;
                                $this->log(__FUNCTION__, "$date skippied because isDayNoDelivery returned true for id_reference_no_delivery $id_reference_no_delivery.");
                            }
                        }
                        
                        if (!$skip && $this->isDayOff($id_reference_off, $date)) {
                            if ($availability_do_not_skip_off_days) {
                                $this->log(__FUNCTION__, "$date did not skippied even isDayOff returned true for id_reference_no_delivery $id_reference_no_delivery because AVAILABILITY_DO_NOT_SKIP_OFF_DAYS is active in configuration.");
                            } else {
                                $skip = true;
                                $this->log(__FUNCTION__, "$date skippied because isDayOff returned true for id_reference_no_delivery $id_reference_no_delivery.");
                            }
                        }
                        
                        if (!$skip && $this->isWeekdayNoDelivery($id_reference, $date)) {
                            if ($availability_do_not_skip_no_delivery_weekdays) {
                                $this->log(__FUNCTION__, "$date did not skippied even isWeekdayNoDelivery returned true for id_reference_no_delivery $id_reference_no_delivery because AVAILABILITY_DO_NOT_SKIP_NO_DELIVERY_WEEKDAYS is active in configuration.");
                            } else {
                                $skip = true;
                                $this->log(__FUNCTION__, "$date skippied because isWeekdayNoDelivery returned true for id_reference_no_delivery $id_reference_no_delivery.");
                            }
                        }
                    }
                    
                    if (!$skip) {
                        $interval_passed++;
                    }
                }
                $time = $this->getAvailabilityTime($data);
                $last_opportunity = date("Y-m-d $time", strtotime($date));
                break;
            
            default:
                return false;
        }
        
        if (empty($last_opportunity)) {
            $this->log(__FUNCTION__, "return false for $delivery_time[date_start] because last_opportunity is empty.");
            return false;
        }
        
        if ($this->is_persian) {
            $last_opportunity_fa = KfaPersianDate::gToS($last_opportunity);
            $this->log(__FUNCTION__, "$delivery_time[date_start] last_opportunity is $last_opportunity($last_opportunity_fa).");
        } else {
            $this->log(__FUNCTION__, "$delivery_time[date_start] last_opportunity is $last_opportunity.");
        }
        
        $now = date('Y-m-d H:i:s');
        // قسمت تاریخ شروع بازه را قسمت تاریخ امروز مقایسه می کنیم
        // منظور از قسمت تاریخ یعنی فقط سال و ماه و روز بدون ساعت و دقیقه و ثانیه
        $d1 = date('Y-m-d', strtotime($delivery_time['date_start']));
        $d2 = date('Y-m-d');
        if ($d1 < $d2) {
            // اگر تاریخ شروع بازه قبل از تاریخ امروز بود یعنی این تابع از بخش وضعیت بازه ها در بخش مدیریت فراخوانی شده
            // و چون در بخش وضعیت بازه ها ممکن است بخواهیم وضعیت روزهای قبل را هم نشان دهیم باید زمان حال را زمان شروع بازه فرض کنیم
            $now = $delivery_time['date_start'];
        }
        
        $diff = strtotime($now) - strtotime($last_opportunity);
        if ($return_diff) {
            $this->log(__FUNCTION__, "returned `diff = $diff` for $delivery_time[date_start].");
            return $diff;
        }
        
        
        $bool_as_str = $diff >= 0 ? 'true' : 'false';
        $this->log(__FUNCTION__, "returned `unavailable = $bool_as_str` for $delivery_time[date_start].");
        
        return $diff >= 0;
    }
    
    private function isOutOfCapacity($delivery_time, $id_carrier) {
        $info = $this->module->getCapacityInfo($delivery_time, $id_carrier, false);
        return $info['out_of_capacity'];
    }
}
