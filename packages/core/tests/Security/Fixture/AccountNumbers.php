<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Security\Fixture;

use AdzerCMS\Contracts\Account\Identifier;

final class AccountNumbers implements Identifier
{
    public function users(array $identifiers): array
    {
        $users = [];
        foreach ($identifiers as $identifier) {
            if (1 === preg_match('/^u(\d+)$/', $identifier, $match)) {
                $users[$identifier] = (int) $match[1];
            }
        }

        return $users;
    }

    public function logins(array $users): array
    {
        return array_combine($users, array_map(static fn (int $user): string => 'u'.$user, $users));
    }
}
