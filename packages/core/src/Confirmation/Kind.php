<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Confirmation;

enum Kind: string
{
    case Code = 'code';
    case Link = 'link';
    case Bot = 'bot';

    public function minutes(): int
    {
        return match ($this) {
            self::Code, self::Bot => 15,
            self::Link => 60,
        };
    }
}
