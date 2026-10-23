<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

final readonly class PickupPoint
{
    public function __construct(
        public string $key,
        public string $address,
        public ?string $hours = null,
    ) {
    }
}
