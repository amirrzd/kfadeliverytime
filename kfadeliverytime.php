<?php

declare(strict_types=1);

/**
 * @author Written by Mojtaba Malaekeh <info@systemiha.ir>, September 2019
 * @copyright Copyright (C) systemiha.ir - All Rights Reserved
 * @license Unauthorized copying of this file, via any medium is strictly prohibited
 */

if (!defined('_PS_VERSION_')) {
    die;
}

require_once dirname(__FILE__) . '/KfaDeliveryTimeCore.php';
require_once dirname(__FILE__) . '/classes/KfaDeliveryTimeCacheSchema.php';

class KfaDeliveryTime extends KfaDeliveryTimeCore
{
    private const MODULE_VERSION = '1.38.4';

    public function __construct()
    {
        parent::__construct();
        $this->version = self::MODULE_VERSION;
    }

    public function install(): bool
    {
        return parent::install() && KfaDeliveryTimeCacheSchema::ensureIndexes();
    }

    protected function doUpdates(): bool
    {
        return parent::doUpdates() && KfaDeliveryTimeCacheSchema::ensureIndexes();
    }
}
