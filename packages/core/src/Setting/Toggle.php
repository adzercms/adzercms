<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Setting;

use AdzerCMS\Contracts\Ui\Titled;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum Toggle: string implements Titled
{
    case On = '1';
    case Off = '0';

    public function label(): TranslatableInterface
    {
        return match ($this) {
            self::On => t('core.toggle.on'),
            self::Off => t('core.toggle.off'),
        };
    }
}
