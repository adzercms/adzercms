<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module\Fixture;

use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;

final class CashBundle extends Module
{
    public static function kind(): Kind
    {
        return Gateway::Payment;
    }
}
