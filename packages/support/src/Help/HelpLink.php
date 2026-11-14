<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Help;

final readonly class HelpLink
{
    public function __construct(
        public string $title,
        public ?string $summary,
        public string $url,
        public ?string $category = null,
    ) {
    }
}
