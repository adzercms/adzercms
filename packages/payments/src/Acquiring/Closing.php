<?php

declare(strict_types=1);

namespace AdzerCMS\Payments\Acquiring;

final readonly class Closing
{
    public function __construct(
        public string $amount,
        public string $item,
        public ?string $phone,
        public ?string $email,
        public string $key,
    ) {
    }
}
