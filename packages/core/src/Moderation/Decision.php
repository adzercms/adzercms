<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Moderation;

use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum Decision: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function action(): TranslatableInterface
    {
        return t('core.moderation.decide.'.$this->value);
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
