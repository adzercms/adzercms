<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Moderation;

use AdzerCMS\Contracts\Ui\Badged;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum Decision: string implements Badged
{
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): TranslatableInterface
    {
        return t('core.moderation.decision.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Published => 'success',
            self::Rejected => 'danger',
            self::Withdrawn => 'secondary',
        };
    }

    public function action(): TranslatableInterface
    {
        return t('core.moderation.decide.'.$this->value);
    }

    public function reason(): TranslatableInterface
    {
        return t('core.moderation.reason.'.$this->value);
    }

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Pending => [self::Published, self::Rejected],
            self::Published => [self::Withdrawn],
            self::Rejected, self::Withdrawn => [],
        };
    }
}
