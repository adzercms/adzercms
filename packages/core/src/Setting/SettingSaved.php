<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Setting;

final readonly class SettingSaved
{
    public function __construct(
        public string $name,
    ) {
    }
}
