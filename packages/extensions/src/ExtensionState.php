<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

use AdzerCMS\Contracts\Ui\Badged;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum ExtensionState: string implements Badged
{
    case Pending = 'pending';
    case Published = 'published';
    case Missing = 'missing';
    case Unclaimed = 'unclaimed';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): TranslatableInterface
    {
        return t('extensions.state.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Published => 'success',
            self::Missing, self::Unclaimed, self::Rejected => 'danger',
            self::Withdrawn => 'secondary',
        };
    }
}
