<?php

declare(strict_types=1);

namespace AdzerCMS\Leads;

use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class Detail
{
    public function __construct(
        public TranslatableInterface $label,
        public string $value,
    ) {
    }
}
