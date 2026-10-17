<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class Column
{
    public function __construct(
        public string $key,
        public TranslatableInterface $label,
        public bool $secondary = false,
    ) {
    }
}
