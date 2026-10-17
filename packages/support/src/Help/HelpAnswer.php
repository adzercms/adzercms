<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Help;

use AdzerCMS\Support\HelpView;

final class HelpAnswer
{
    /** @param list<HelpLink> $links */
    public function __construct(
        public readonly HelpView $view,
        public readonly string $home,
        public readonly array $links = [],
    ) {
    }

    public function missing(): bool
    {
        return HelpView::Missing === $this->view;
    }
}
