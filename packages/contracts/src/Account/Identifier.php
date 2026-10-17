<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Account;

interface Identifier
{
    public function user(string $identifier): ?int;

    /**
     * @param list<int> $users
     *
     * @return array<int, string>
     */
    public function logins(array $users): array;
}
