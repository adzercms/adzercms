<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class Column
{
    /** @param (\Closure(string): string)|null $order */
    public function __construct(
        public string $key,
        public TranslatableInterface $label,
        public ?\Closure $order,
        public bool $secondary = false,
    ) {
    }
}
