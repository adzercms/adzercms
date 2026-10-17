<?php

declare(strict_types=1);

namespace AdzerCMS\Payments\Acquiring;

final readonly class BoundCard
{
    public function __construct(
        public string $id,
        public string $label,
        public ?string $expires,
        public ?string $issuer = null,
    ) {
    }
}
