<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Module;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

abstract class Module extends AbstractBundle
{
    abstract public static function kind(): Kind;

    /** @return list<Kind> */
    public static function kinds(): array
    {
        return [];
    }
}
