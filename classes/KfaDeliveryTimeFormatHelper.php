<?php
/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, September 2019
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

class KfaDeliveryTimeFormatHelper {
    private static function persianMonth($expression) {
        $date = KfaPersianDate::gToS($expression);
        if (!$date) {
            return '';
        }
        
        $month = explode('-', $date)[1];
        switch ($month) {
            case 1:
                return 'فروردین';
            case 2:
                return 'اردیبهشت';
            case 3:
                return 'خرداد';
            case 4:
                return 'تیر';
            case 5:
                return 'مرداد';
            case 6:
                return 'شهریور';
            case 7:
                return 'مهر';
            case 8:
                return 'آبان';
            case 9:
                return 'آذر';
            case 10:
                return 'دی';
            case 11:
                return 'بهمن';
            case 12:
                return 'اسفند';
        }
        return '???';
    }
    
    private static function getWeekdayName($date, $fa) {
        return $fa ? KfaDeliveryTimeRangeHelper::persianWeekday($date) : date('l', strtotime($date));
    }
    
    private static function getMonthName($date, $fa) {
        return $fa ? self::persianMonth($date) : date('F', strtotime($date));
    }
    
    private static function getSolarDate($date, $reverse = false) {
        return KfaPersianDate::gToS($date, $reverse, false);
    }
    
    public static function formatDualDate($date, $format) {
        $keys = array('process', 'from', 'to');
        $empty_date = '0000-00-00 00:00:00';
        
        foreach ($keys as $key) {
            if (!preg_match('~^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$~', $date[$key])) {
                if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $date[$key])) {
                    $date[$key] = $date[$key] . ' 00:00:00';
                } else {
                    $date[$key] = $empty_date;
                }
            }
        }
                
        $fa = Context::getContext()->language->iso_code == 'fa';
        $dateParts = array();
        $weekday = array();
        $month = array();
        foreach ($keys as $key) {
            $temp               = $fa && $date[$key] != $empty_date ? self::getSolarDate($date[$key]) : $date[$key];
            $dateParts[$key]    = explode('-', $temp);
            $weekday[$key]      = self::getWeekdayName($date[$key], $fa);
            $month[$key]        = self::getMonthName($date[$key], $fa);
        }
        
        $search = array(
            '{process_y}',
            '{process_m}',
            '{process_d}',
            '{process_w}',
            '{process_mm}',
            
            '{from_y}',
            '{from_m}',
            '{from_d}',
            '{from_w}',
            '{from_mm}',
            
            '{to_y}',
            '{to_m}',
            '{to_d}',
            '{to_w}',
            '{to_mm}',
        );
        
        $replace = array(
            (int) $dateParts['process'][0],
            (int) $dateParts['process'][1],
            (int) $dateParts['process'][2],
            $weekday['process'],
            $month['process'],
            
            (int) $dateParts['from'][0],
            (int) $dateParts['from'][1],
            (int) $dateParts['from'][2],
            $weekday['from'],
            $month['from'],
            
            (int) $dateParts['to'][0],
            (int) $dateParts['to'][1],
            (int) $dateParts['to'][2],
            $weekday['to'],
            $month['to'],
        );

        return str_replace($search, $replace, $format);
    }
    
    public static function isNormalOption($option) {
        return preg_match('~^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}-\d{2}:\d{2}$~', $option);
    }
    
    private static function formatRemainingDays($date) {
        $context = Context::getContext();
        $id_lang = $context->language->id;
        $remaining_days = floor((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);
        
        switch ($remaining_days) {
            case 0:
                return Configuration::get('KFADELIVERYTIME_TODAY_TEXT', $id_lang);
                
            case 1:
                return Configuration::get('KFADELIVERYTIME_TOMORROW_TEXT', $id_lang);
                
            default:
                return sprintf(Configuration::get('KFADELIVERYTIME_REMAINING_DAYS_TEXT', $id_lang), $remaining_days);
        }
    }
    
    public static function formatOption($option, $key, $format = false) {
        if (!KfaDeliveryTimeFormatHelper::isNormalOption($option)) {
            return $option;
        }
        
        $context = Context::getContext();
        
        if ($key) {
            $format = Configuration::get($key, $context->language->id);
        }
        
        $explode = explode(' ', $option);
        $rangeParts = explode('-', $explode[1]);
        
        $fa = $context->language->iso_code == 'fa';
        $dateObject = KfaPersianDate::stoG($explode[0]);
        $date = $dateObject->format('Y-m-d');
        $dateString = $fa ? $explode[0] : $date;
        $dateParts = explode('-', $dateString);
        $weekday = self::getWeekdayName($date, $fa);
        $month = self::getMonthName($date, $fa);
        $from = $rangeParts[0];
        $to = $rangeParts[1];
        
        $from_parts = explode(':', $from);
        $to_parts = explode(':', $to);
        $from_h = (int) $from_parts[0];
        $to_h = (int) $to_parts[0];
        
        $search = array(
            '{y}',
            '{m}',
            '{d}',
            '{w}',
            '{mm}',
            '{from}',
            '{from_h}',
            '{to}',
            '{to_h}',
            '{r}',
            '{enter}',
        );
        
        $replace = array(
            (int) $dateParts[0],
            (int) $dateParts[1],
            (int) $dateParts[2],
            $weekday,
            $month,
            $from,
            $from_h,
            $to,
            $to_h,
            self::formatRemainingDays($date),
            '<br>',
        );
        
        return str_replace($search, $replace, $format);
    }
    
    public static function formatDate($date, $key, $format = false) {
        $context = Context::getContext();
        
        if ($key) {
            $format = Configuration::get($key, $context->language->id);
        }
        
        $fa = $context->language->iso_code == 'fa';
        $dateString = $fa ? self::getSolarDate($date) : $date;
        $dateParts = explode('-', $dateString);
        $weekday = self::getWeekdayName($date, $fa);
        $month = self::getMonthName($date, $fa);

        $search = array(
            '{y}',
            '{m}',
            '{d}',
            '{w}',
            '{mm}',
            '{r}',
            '{enter}',
        );
        
        $replace = array(
            (int) $dateParts[0],
            (int) $dateParts[1],
            (int) $dateParts[2],
            $weekday,
            $month,
            self::formatRemainingDays($date),
            '<br>',
        );

        return str_replace($search, $replace, $format);
    }
    
    public static function displayDate($date, $reverse) {
        $context = Context::getContext();
        $fa = $context->language->iso_code == 'fa';
        return $fa ? self::getSolarDate($date, $reverse) : $date;
    }
}
