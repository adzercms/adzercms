<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module\Fixture\Twin;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;

final class CardBundle extends Module
{
    public static function kind(): Kind
    {
        return CoreKind::Feature;
    }
}
