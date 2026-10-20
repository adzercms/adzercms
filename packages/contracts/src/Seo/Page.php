<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Seo;

use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class Page
{
    /**
     * @param list<string>              $routes
     * @param list<class-string>        $sources
     * @param array<string, string|int> $parameters
     */
    public function __construct(
        public string $route,
        public TranslatableInterface $label,
        public array $routes = [],
        public array $sources = [],
        public array $parameters = [],
    ) {
    }
}
