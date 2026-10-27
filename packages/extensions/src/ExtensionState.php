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

    public function decision(): ExtensionDecision
    {
        return match ($this) {
            self::Pending => ExtensionDecision::Pending,
            self::Rejected => ExtensionDecision::Rejected,
            self::Withdrawn => ExtensionDecision::Withdrawn,
            self::Published, self::Missing, self::Unclaimed => ExtensionDecision::Published,
        };
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
