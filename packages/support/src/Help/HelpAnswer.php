<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Help;

use AdzerCMS\Support\HelpView;

final readonly class HelpAnswer
{
    /** @param list<HelpLink> $links */
    public function __construct(
        public HelpView $view,
        public string $home,
        public array $links,
    ) {
    }
}
