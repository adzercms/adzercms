<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class Tab
{
    public function __construct(
        public string $key,
        public TranslatableInterface $label,
        public int $weight,
        public ?int $count = null,
    ) {
    }
}
