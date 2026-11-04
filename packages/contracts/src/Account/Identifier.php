<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Account;

interface Identifier
{
    /**
     * @param list<string> $identifiers
     *
     * @return array<string, int>
     */
    public function users(array $identifiers): array;

    /**
     * @param list<int> $users
     *
     * @return array<int, string>
     */
    public function logins(array $users): array;
}
