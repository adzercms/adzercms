<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class MenuItem
{
    /** @param list<string> $routes */
    public function __construct(
        public string $route,
        public TranslatableInterface $label,
        public int $weight,
        public array $routes = [],
        public ?TranslatableInterface $group = null,
        public ?int $count = null,
        public bool $dialog = false,
        public ?string $icon = null,
        public ?TranslatableInterface $short = null,
    ) {
    }
}
