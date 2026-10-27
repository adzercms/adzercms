<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

enum ExtensionDecision: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function action(): TranslatableInterface
    {
        return t('extensions.decide.'.$this->value);
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

    public function state(): ExtensionState
    {
        return match ($this) {
            self::Pending => ExtensionState::Pending,
            self::Published => ExtensionState::Published,
            self::Rejected => ExtensionState::Rejected,
            self::Withdrawn => ExtensionState::Withdrawn,
        };
    }
}
