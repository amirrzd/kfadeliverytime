CREATE TABLE IF NOT EXISTS `PREFIX_kfadeliverytime_carrier` (
  `id_reference` int(10) UNSIGNED NOT NULL,
  `id_shop` int(10) UNSIGNED NOT NULL,
  `program` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `program_off` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `program_no_delivery` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `same_day_delivery` tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
  `required` tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
  `auto_select` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `future_days` tinyint(1) UNSIGNED NOT NULL DEFAULT '3',
  `future_days_when_no_delivery` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `scroll_active` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `scroll_selector` varchar(255) DEFAULT NULL,
  `scroll_offset` int(10) NOT NULL DEFAULT '0',
  `scroll_speed` int(10) UNSIGNED NOT NULL DEFAULT '1000',
  `approximate_format_bo` text,
  `approximate_format_fo` text,
  `approximate_capacity` varchar(16) DEFAULT NULL,
  `approximate_min_day` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `approximate_max_day` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `approximate_add_no_delivery` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `approximate_add_off` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `approximate_add_sat` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `approximate_add_sun` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `approximate_add_mon` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `approximate_add_tue` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `approximate_add_wed` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `approximate_add_thu` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `approximate_add_fri` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `approximate_data` text,
  `heading` text,
  `fixed_option_active` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `fixed_option_value` varchar(1024) DEFAULT NULL,
  `fixed_option_date` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id_reference`, `id_shop`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_kfadeliverytime_carrier_lang` (
  `id_reference` int(10) UNSIGNED NOT NULL,
  `id_shop` int(10) UNSIGNED NOT NULL,
  `id_lang` int(10) UNSIGNED NOT NULL,
  `heading` text,
  `approximate_format_bo` text,
  `approximate_format_fo` text,
  `fixed_option_value` varchar(1024) DEFAULT NULL,
  PRIMARY KEY (`id_reference`,`id_shop`,`id_lang`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_kfadeliverytime_cart` (
  `id_cart` int(10) UNSIGNED NOT NULL,
  `value` text,
  `approximate` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `date_process` datetime DEFAULT NULL,
  `date_start` datetime DEFAULT NULL,
  `date_end` datetime DEFAULT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_cart`),
  KEY `idx_kfadt_cart_date_upd` (`date_upd`,`id_cart`),
  KEY `idx_kfadt_cart_date_start` (`date_start`,`id_cart`),
  KEY `idx_kfadt_cart_date_process` (`date_process`,`id_cart`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_kfadeliverytime_day` (
  `id_reference` int(10) UNSIGNED NOT NULL,
  `id_shop` int(10) UNSIGNED NOT NULL,
  `name` varchar(16) NOT NULL,
  `program` tinyint(1) UNSIGNED NOT NULL,
  `same` tinyint(1) UNSIGNED NOT NULL,
  `capacity` varchar(16) DEFAULT NULL,
  PRIMARY KEY (`id_reference`,`id_shop`,`name`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_kfadeliverytime_product` (
  `id_product` int(10) UNSIGNED NOT NULL,
  `additional_delivery_times` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_product`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_kfadeliverytime_product_lang` (
  `id_product` int(10) UNSIGNED NOT NULL,
  `id_shop` int(10) UNSIGNED NOT NULL,
  `id_lang` int(10) UNSIGNED NOT NULL,
  `delivery_in_stock` varchar(255) DEFAULT NULL,
  `delivery_out_stock` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_product`,`id_shop`,`id_lang`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_kfadeliverytime_range` (
  `id_reference` int(10) UNSIGNED NOT NULL,
  `id_shop` int(10) UNSIGNED NOT NULL,
  `name` varchar(16) NOT NULL,
  `from_h` tinyint(2) UNSIGNED ZEROFILL NOT NULL,
  `from_m` tinyint(2) UNSIGNED ZEROFILL NOT NULL,
  `to_h` tinyint(2) UNSIGNED ZEROFILL NOT NULL,
  `to_m` tinyint(2) UNSIGNED ZEROFILL NOT NULL,
  `deleted` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `disabled` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `capacity` varchar(16) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `data` text
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_kfadeliverytime_event` (
  `id_reference` int(10) UNSIGNED NOT NULL,
  `event_type` tinyint(1) UNSIGNED NOT NULL,
  `deleted` tinyint(1) UNSIGNED NOT NULL,
  `y` int(10) UNSIGNED NOT NULL,
  `m` tinyint(1) UNSIGNED NOT NULL,
  `d` tinyint(1) UNSIGNED NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_kfadeliverytime_history` (
  `id_history` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_cart` int(10) UNSIGNED NOT NULL,
  `id_employee` int(10) UNSIGNED NOT NULL,
  `value` text,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_history`),
  KEY `idx_kfadt_history_date_add` (`date_add`,`id_cart`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_kfadeliverytime_order_display` (
  `id_order` int(10) UNSIGNED NOT NULL,
  `deliverytime` varchar(1024) DEFAULT NULL,
  PRIMARY KEY (`id_order`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_kfadeliverytime_cached_orders_list` (
  `id_cache` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `valid` tinyint(1) UNSIGNED DEFAULT NULL,
  `current_state` varchar(1024) DEFAULT NULL,
  `id_carrier` varchar(1024) DEFAULT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_cache`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_kfadeliverytime_cached_orders` (
  `id_cache` int(10) UNSIGNED NOT NULL,
  `id_order` int(10) UNSIGNED NOT NULL,
  `id_cart` int(10) UNSIGNED NOT NULL,
  `id_currency` int(10) UNSIGNED NOT NULL,
  `total_paid` decimal(20,6) NOT NULL,
  `date_process` datetime DEFAULT NULL,
  `date_start` datetime DEFAULT NULL,
  `date_end` datetime DEFAULT NULL,
  `value` text,
  PRIMARY KEY (`id_cache`,`id_order`),
  KEY `idx_id_order` (`id_order`),
  KEY `idx_id_cart` (`id_cart`),
  KEY `idx_date_start` (`date_start`),
  KEY `idx_date_end` (`date_end`),
  KEY `idx_kfa_cache_dates` (`id_cache`,`date_start`,`date_end`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;
