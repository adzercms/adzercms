<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Seo;

use AdzerCMS\Contracts\Seo\Page;

final readonly class Head
{
    public function __construct(
        public ?Page $page,
        public string $canonical,
        public bool $indexed,
    ) {
    }
}
