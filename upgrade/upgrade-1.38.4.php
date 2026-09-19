<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/classes/KfaDeliveryTimeCacheSchema.php';

function upgrade_module_1_38_4(object $_module): bool
{
    return KfaDeliveryTimeCacheSchema::ensureIndexes();
}
