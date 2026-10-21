<?php

declare(strict_types=1);

namespace AdzerCMS\Underlay;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;

final class UnderlayBundle extends Module
{
    public static function kind(): Kind
    {
        return CoreKind::Theme;
    }
}
