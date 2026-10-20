<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

use AdzerCMS\Contracts\Ui\Badged;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum ExtensionState: string implements Badged
{
    case Published = 'published';
    case Missing = 'missing';

    public function label(): TranslatableInterface
    {
        return t('extensions.state.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Published => 'success',
            self::Missing => 'danger',
        };
    }
}
