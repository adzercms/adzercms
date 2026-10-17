<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Help;

final class HelpLink
{
    public function __construct(
        public readonly string $title,
        public readonly string $summary,
        public readonly string $url,
        public readonly string $category = '',
    ) {
    }
}
