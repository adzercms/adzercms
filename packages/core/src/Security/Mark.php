<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Security;

final readonly class Mark
{
    public function __construct(
        public string $key,
        public ?string $email,
    ) {
    }
}
