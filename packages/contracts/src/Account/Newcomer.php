<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Account;

final readonly class Newcomer
{
    public function __construct(
        public string $lastName,
        public string $firstName,
        public string $middleName,
        public ?string $phone,
        public ?string $email,
        public string $password,
    ) {
    }
}
