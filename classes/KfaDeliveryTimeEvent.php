<?php
/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, April 2020
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

class KfaDeliveryTimeEvent {
    
    const ENTITY_OFF = 0;
    const ENTITY_NO_DELIVERY = 1;
    
    public static function getAllEvents($type, $carriers = null) {
        $table = _DB_PREFIX_ . 'kfadeliverytime_event';
        $rows = Db::getInstance()->executeS("SELECT * FROM `$table` WHERE `event_type` = $type");
        if (!$rows) {
            $rows = array();
        }
        
        if (!$carriers) {
            return $rows;
        }
        
        $result = array();
        foreach ($carriers as $carrier) {
            $id_reference = (int) $carrier['id_reference'];
            if (!isset($result[$id_reference])) {
                $result[$id_reference] = array();
            }
            
            foreach ($rows as $row) {
                if ($row['id_reference'] != $id_reference) {
                    continue;
                }
                
                $result[$id_reference][] = $row;
            }
        }
        return $result;
    }
    
    private static function getCachedEvents($id_reference, $event_type, $with_year) {
        static $result = null;
        if (is_null($result)) {
            $result = array(
                KfaDeliveryTimeEvent::ENTITY_OFF => array(
                    'with_year' => array(),
                    'without_year' => array(),
                ),
                KfaDeliveryTimeEvent::ENTITY_NO_DELIVERY => array(
                    'with_year' => array(),
                    'without_year' => array(),
                ),
            );
            $prefix = _DB_PREFIX_;
            $sql = "
                SELECT *
                FROM `{$prefix}kfadeliverytime_event`
                WHERE `deleted` = 0
            ";
            if (($rows = Db::getInstance()->executeS($sql))) {
                foreach ($rows as $row) {
                    if ((int) $row['event_type'] == KfaDeliveryTimeEvent::ENTITY_OFF) {
                        if ((int) $row['y'] > 0) {
                            $result[KfaDeliveryTimeEvent::ENTITY_OFF]['with_year'][(int) $row['id_reference']][] = array(
                                'y' => (int) $row['y'],
                                'm' => (int) $row['m'],
                                'd' => (int) $row['d'],
                            );
                        } else {
                            $result[KfaDeliveryTimeEvent::ENTITY_OFF]['without_year'][(int) $row['id_reference']][] = array(
                                'm' => (int) $row['m'],
                                'd' => (int) $row['d'],
                            );
                        }
                        continue;
                    }
                    
                    if ((int) $row['event_type'] == KfaDeliveryTimeEvent::ENTITY_NO_DELIVERY) {
                        if ((int) $row['y'] > 0) {
                            $result[KfaDeliveryTimeEvent::ENTITY_NO_DELIVERY]['with_year'][(int) $row['id_reference']][] = array(
                                'y' => (int) $row['y'],
                                'm' => (int) $row['m'],
                                'd' => (int) $row['d'],
                            );
                        } else {
                            $result[KfaDeliveryTimeEvent::ENTITY_NO_DELIVERY]['without_year'][(int) $row['id_reference']][] = array(
                                'm' => (int) $row['m'],
                                'd' => (int) $row['d'],
                            );
                        }
                        continue;
                    }
                }
            }
        }
        $year_key = $with_year ? 'with_year' : 'without_year';
        if (isset($result[$event_type][$year_key][$id_reference])) {
            return $result[$event_type][$year_key][$id_reference];
        }
        
        return array();
    }
    
    private static function getEvents($id_reference, $event_type, $with_year) {
        if (true) {
            // New way - fast
            $rows = self::getCachedEvents($id_reference, $event_type, $with_year);
        } else {
            // Old way - slow
            $prefix = _DB_PREFIX_;
            if ($with_year) {
                $sql = "
                    SELECT `y`, `m`, `d`
                    FROM `{$prefix}kfadeliverytime_event`
                    WHERE `y` > 0 AND `deleted` = 0 AND `id_reference` = $id_reference AND `event_type` = $event_type
                ";
            } else {
                $sql = "
                    SELECT `m`, `d`
                    FROM `{$prefix}kfadeliverytime_event`
                    WHERE `y` = 0 AND `deleted` = 0 AND `id_reference` = $id_reference AND `event_type` = $event_type
                ";
            }
            $rows = Db::getInstance()->executeS($sql);
        }
        $result = array();
        if ($rows) {
            foreach ($rows as $row) {
                if ($with_year) {
                    $year       = (int) $row['y'];
                    $month      = (int) $row['m'];
                    $day        = (int) $row['d'];
                    $result[]   = "$year-$month-$day";
                } else {
                    $month      = (int) $row['m'];
                    $day        = (int) $row['d'];
                    $result[]   = "$month-$day";
                }
            }
        }
        return $result;
    }
    
    public static function getYearlyVacations($id_reference) {
        return self::getEvents($id_reference, KfaDeliveryTimeEvent::ENTITY_OFF, false);
    }
    
    public static function getTemporaryVacations($id_reference) {
        return self::getEvents($id_reference, KfaDeliveryTimeEvent::ENTITY_OFF, true);
    }
    
    public static function getYearlyNoDeliveries($id_reference) {
        return self::getEvents($id_reference, KfaDeliveryTimeEvent::ENTITY_NO_DELIVERY, false);
    }
    
    public static function getTemporaryNoDeliveries($id_reference) {
        return self::getEvents($id_reference, KfaDeliveryTimeEvent::ENTITY_NO_DELIVERY, true);
    }
    
    public static function update($id_reference, $type, $events) {
        $result = true;
        $result &= Db::getInstance()->delete('kfadeliverytime_event', "`id_reference` = $id_reference AND `event_type` = $type");
        
        foreach ($events as &$event) {
            $event['id_reference'] = $id_reference;
            $event['event_type'] = $type;
        }
        $result &= Db::getInstance()->insert('kfadeliverytime_event', $events);
        return $result;
    }
}
