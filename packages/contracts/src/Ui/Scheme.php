<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class Scheme
{
    public function __construct(
        public string $key,
        public TranslatableInterface $label,
        public string $color,
    ) {
    }
}
