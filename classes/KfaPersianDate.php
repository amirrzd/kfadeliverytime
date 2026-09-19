<?php
/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, November 2017
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

class KfaPersianDate
{
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
    
    public static function gToS($expression, $reverse = false, $show_time = true, $two_digits = true, $show_weekday = false, $separator = '-')
    {
        $temp = date_parse($expression);
        if (!checkdate($temp['month'], $temp['day'], $temp['year'])) {
            return false;
        }

        $iYear = $temp['year'];
        $iMonth = $temp['month'];
        $iDay =  $temp['day'];
        $y = 0;
        $m = 0;
        $d = 0;
        self::gregorianToSolar($iYear, $iMonth, $iDay, $y, $m, $d);
        $m = ($m < 10 ? "0" : "") . $m;
        $d = ($d < 10 ? "0" : "") . $d;

        $hour = ($temp['hour'] < 10 && $two_digits ? "0" : "") . $temp['hour'];
        $minute = ($temp['minute'] < 10 && $two_digits ? "0" : "") . $temp['minute'];
        $second = ($temp['second'] < 10 && $two_digits ? "0" : "") . $temp['second'];
        $time = $show_time ? " $hour:$minute:$second" : "";

        $w = '';
        if ($show_weekday) {
            $w = self::persianWeekday($expression);
            if (!$reverse) {
                $w = ' ' . $w;
            }
        }
        return $reverse ?
            "$w$time $d$separator$m$separator$y" :
            "$y$separator$m$separator$d$w$time";
    }

    public static function stoG($expression)
    {
        if (!self::isSolarDate($expression)) {
            return false;
        }

        $iYear = 0;
        $iMonth = 0;
        $iDay = 0;
        $iHour = 0;
        $iMinute = 0;
        $iSecond = 0;
        $oYear = 0;
        $oMonth = 0;
        $oDay = 0;
        self::splitDate($expression, $iYear, $iMonth, $iDay, $iHour, $iMinute, $iSecond);
        self::solarToGregorian($iYear, $iMonth, $iDay, $oYear, $oMonth, $oDay);
        return new DateTime($oYear .'-'. $oMonth .'-'. $oDay .' '. $iHour .':'. $iMinute .':'. $iSecond);
    }

    public static function splitDate($expression, &$year, &$month, &$day, &$hour, &$minute, &$second)
    {
        $clear_expression = self::removeDoubleSpaces($expression);
        $parts = explode(' ', $clear_expression);
        if (count($parts) < 1 || count($parts) > 2) {
            return false;
        }

        if (strpos($parts[0], '/') !== false) {
            $date_separator = '/';
        } else {
            $date_separator = '-';
        }
        $date_parts = explode($date_separator, $parts[0]);
        if (count($date_parts) != 3) {
            return false;
        }

        $year = (int)$date_parts[0];
        $month = (int)$date_parts[1];
        $day = (int)$date_parts[2];

        if (count($parts) == 2) {
            $time_parts = explode(':', $parts[1]);
            $hour = (int)$time_parts[0];
            $minute = count($time_parts) > 1 ? (int)$time_parts[1] : 0;
            $second = count($time_parts) > 2 ? (int)$time_parts[2] : 0;
        }
        return true;
    }

    public static function correctGregorianDate($expression, $min_year = 2001, $max_year = 3000)
    {
        $iYear = 0;
        $iMonth = 0;
        $iDay = 0;
        $iHour = 0;
        $iMinute = 0;
        $iSecond = 0;
        self::splitDate($expression, $iYear, $iMonth, $iDay, $iHour, $iMinute, $iSecond);

        if (!self::inRange($iYear, $min_year, $max_year)) {
            return false;
        }

        if (!self::inRange($iMonth, 1, 12)) {
            return false;
        }

        $max_day = self::getMaxDayOfMonthGregorian($iYear .'-'. $iMonth .'-'. $iDay);
        if (!self::inRange($iDay, 1, $max_day)) {
            return false;
        }

        $date_time =
            str_pad($iYear, 4, '0', STR_PAD_LEFT) .'-'. str_pad($iMonth, 2, '0', STR_PAD_LEFT) .'-'. str_pad($iDay, 2, '0', STR_PAD_LEFT) .' '.
            str_pad($iHour, 2, '0', STR_PAD_LEFT) .':'. str_pad($iMinute, 2, '0', STR_PAD_LEFT) .':'. str_pad($iSecond, 2, '0', STR_PAD_LEFT);
        if (!DateTime::createFromFormat('Y-m-d H:i:s', $date_time)) {
            return false;
        }
        return $date_time;
    }

    private static function removeDoubleSpaces($input)
    {
        return preg_replace('!\s+!', ' ', $input);
    }

    public static function isSolarDate($expression)
    {
        if (empty($expression)) {
            return false;
        }

        $iYear = 0;
        $iMonth = 0;
        $iDay = 0;
        $iHour = 0;
        $iMinute = 0;
        $iSecond = 0;
        self::splitDate($expression, $iYear, $iMonth, $iDay, $iHour, $iMinute, $iSecond);
        
        if (!self::inRange($iYear, 1000, 2000)) {
            return false;
        }
        
        if (!self::inRange($iMonth, 1, 12)) {
            return false;
        }
        
        if (!self::inRange($iDay, 1, 31)) {
            return false;
        }
        
        if ($iDay > self::getMaxDayOfMonth($expression)) {
            return false;
        }

        return true;
    }

    public static function getMaxDayOfMonthGregorian($expression)
    {
        $iYear = 0;
        $iMonth = 0;
        $iDay = 0;
        $iHour = 0;
        $iMinute = 0;
        $iSecond = 0;
        self::splitDate($expression, $iYear, $iMonth, $iDay, $iHour, $iMinute, $iSecond);
        $date_time = new DateTime("{$iYear}-{$iMonth}-27");
        $date_time_with_max_day = $date_time->format('Y-m-t');
        $date_parts = explode('-', $date_time_with_max_day);
        $max_day_of_month = $date_parts[2];
        return $max_day_of_month;
    }

    public static function getMaxDayOfMonth($expression)
    {
        $sYear = 0;
        $sMonth = 0;
        $sDay = 0;
        $mYear = 0;
        $iHour = 0;
        $iMinute = 0;
        $iSecond = 0;

        $mMonth = 0;
        $mDay = 0;
        $max = 0;
        self::splitDate($expression, $sYear, $sMonth, $sDay, $iHour, $iMinute, $iSecond);
        $cM = $sMonth;
        self::solarToGregorian($sYear, $sMonth, 27, $mYear, $mMonth, $mDay);
        
        $mDate = new DateTime($mYear .'-'. $mMonth .'-'. $mDay);
        while ($cM == $sMonth) {
            $max    = $sDay;
            $mDate  = $mDate->add(new DateInterval('P1D'));
            $mDateAsString = $mDate->format('Y-m-d');
            $temp   = date_parse($mDateAsString);
            $mYear  = $temp['year'];
            $mMonth = $temp['month'];
            $mDay   = $temp['day'];
            self::gregorianToSolar($mYear, $mMonth, $mDay, $sYear, $sMonth, $sDay);
        }
        return $max;
    }

    private static function inRange($value, $min, $max)
    {
        return $value >= $min && $value <= $max;
    }

    private static function gregorianToSolar($iYear, $iMonth, $iDay, &$oYear, &$oMonth, &$oDay)
    {
        $limit = 0;
        $maxDay = 0;
        $y = ($iYear - 5 < 0 ? ($iYear - 5) * -1 : $iYear - 5) % 4;
        switch ($iMonth) {
            case 1:
                $limit = $y == 0 ? 19 : 20;
                $maxDay = 30;
                break;
            case 2:
                $limit = $y == 0 ? 18 : 19;
                $maxDay = 30;
                break;
            case 3:
                $limit = $y == 3 ? 19 : 20;
                $maxDay = $y == 0 ? 30 : 29;
                break;
            case 4:
                $limit = $y == 3 ? 19 : 20;
                $maxDay = 31;
                break;
            case 5:
                $limit = $y == 3 ? 20 : 21;
                $maxDay = 31;
                break;
            case 6:
                $limit = $y == 3 ? 20 : 21;
                $maxDay = 31;
                break;
            case 7:
                $limit = $y == 3 ? 21 : 22;
                $maxDay = 31;
                break;
            case 8:
                $limit = $y == 3 ? 21 : 22;
                $maxDay = 31;
                break;
            case 9:
                $limit = $y == 3 ? 21 : 22;
                $maxDay = 31;
                break;
            case 10:
                $limit = $y == 3 ? 21 : 22;
                $maxDay = 30;
                break;
            case 11:
                $limit = $y == 3 ? 20 : 21;
                $maxDay = 30;
                break;
            case 12:
                $limit = $y == 3 ? 20 : 21;
                $maxDay = 30;
                break;
        }
        $criticalDay = $y == 3 ? 19 : 20;
        $oDay = $iDay <= $limit ? $iDay - $limit + $maxDay : $iDay - $limit;
        $oMonth = $iDay <= $limit ? $iMonth + 9 : $iMonth + 10;
        if ($oMonth > 12) {
            $oMonth -= 12;
        }
        $oYear = $iYear - (($iMonth < 3) || ($iMonth == 3 && $iDay <= $criticalDay) ? 622 : 621);
    }

    private static function solarToGregorian($iYear, $iMonth, $iDay, &$oYear, &$oMonth, &$oDay)
    {
        $limit = 0;
        $maxDay = 0;
        $ly = $iYear % 4 == 3;
        $al = $iYear % 4 == 2;
        $tt = $iYear % 4 == 2 || $iYear % 4 == 3;
        switch ($iMonth) {
            case 1:
                $limit = $ly ? 12 : 11;
                $maxDay = 31;
                break;
            case 2:
                $limit = $ly ? 11 : 10;
                $maxDay = 30;
                break;
            case 3:
                $limit = $ly ? 11 : 10;
                $maxDay = 31;
                break;
            case 4:
                $limit = $ly ? 10 : 9;
                $maxDay = 30;
                break;
            case 5:
                $limit = $ly ? 10 : 9;
                $maxDay = 31;
                break;
            case 6:
                $limit = $ly ? 10 : 9;
                $maxDay = 31;
                break;
            case 7:
                $limit = $ly ? 9 : 8;
                $maxDay = 30;
                break;
            case 8:
                $limit = $ly ? 10 : 9;
                $maxDay = 31;
                break;
            case 9:
                $limit = $ly ? 10 : 9;
                $maxDay = 30;
                break;
            case 10:
                $limit = $ly ? 11 : 10;
                $maxDay = 31;
                break;
            case 11:
                $limit = $ly ? 12 : 11;
                $maxDay = 31;
                break;
            case 12:
                $limit = $tt ? 10 : 9;
                $maxDay = $al ? 29 : 28;
                break;
        }
        $criticalDay = $ly ? 11 : 10;
        $oDay = $iDay <= $limit ? $iDay - $limit + $maxDay : $iDay - $limit;
        $oMonth = $iDay <= $limit ? $iMonth + 2 : $iMonth + 3;
        if ($oMonth > 12) {
            $oMonth -= 12;
        }
        $oYear = $iYear + (($iMonth < 10) || ($iMonth == 10 && $iDay <= $criticalDay) ? 621 : 622);
    }
}
