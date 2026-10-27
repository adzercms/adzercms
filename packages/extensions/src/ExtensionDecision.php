<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

enum ExtensionDecision: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

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
