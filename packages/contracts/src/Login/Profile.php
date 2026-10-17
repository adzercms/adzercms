<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Login;

final readonly class Profile
{
    public function __construct(
        public string $id,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $account = null,
    ) {
    }
}
