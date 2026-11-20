<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Style;

enum Screen: string
{
    case Desktop = 'desktop';
    case Tablet = 'tablet';
    case Phone = 'phone';

    public const TABLET = 1024;
    public const PHONE = 640;

    public function width(): ?int
    {
        return match ($this) {
            self::Desktop => null,
            self::Tablet => self::TABLET,
            self::Phone => self::PHONE,
        };
    }
}
