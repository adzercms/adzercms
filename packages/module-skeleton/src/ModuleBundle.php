<?php

declare(strict_types=1);

namespace Vendor\Module;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;

final class ModuleBundle extends Module
{
    public static function kind(): Kind
    {
        return CoreKind::Feature;
    }
}
