<?php

declare(strict_types=1);

namespace AdzerCMS\Threadbare;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;

final class ThreadbareBundle extends Module
{
    public static function kind(): Kind
    {
        return CoreKind::Theme;
    }
}
