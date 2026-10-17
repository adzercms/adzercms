<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Security\Fixture;

use AdzerCMS\Contracts\Account\Identifier;

final class AccountNumbers implements Identifier
{
    public function user(string $identifier): ?int
    {
        return 1 === preg_match('/^u(\d+)$/', $identifier, $match) ? (int) $match[1] : null;
    }

    public function logins(array $users): array
    {
        return array_combine($users, array_map(static fn (int $user): string => 'u'.$user, $users));
    }
}
