<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

final readonly class SiteLink
{
    public function __construct(
        public string $label,
        public string $url,
        public bool $current = false,
        public bool $external = false,
    ) {
    }
}
