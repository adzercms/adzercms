<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Contracts\Account\Identifier;
use AdzerCMS\Core\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Twig\Attribute\AsTwigFunction;

final readonly class LoginNames
{
    /** @param iterable<Identifier> $identifiers */
    public function __construct(
        #[AutowireIterator(Identifier::class)]
        private iterable $identifiers,
    ) {
    }

    #[AsTwigFunction('login')]
    public function of(User $user): string
    {
        return $this->ofMany([$user])[(int) $user->getId()];
    }

    /**
     * @param iterable<?User> $users
     *
     * @return array<int, string>
     */
    #[AsTwigFunction('logins')]
    public function ofMany(iterable $users): array
    {
        $users = array_filter([...$users]);
        $ids = array_map(static fn (User $user): int => (int) $user->getId(), $users);
        $found = [];
        foreach ($this->identifiers as $identifier) {
            $rest = array_values(array_diff($ids, array_keys($found)));
            if ([] === $rest) {
                break;
            }
            $found += $identifier->logins($rest);
        }

        $names = [];
        foreach ($users as $user) {
            $id = (int) $user->getId();
            $names[$id] = $found[$id] ?? $user->getEmail() ?? $user->getPhone() ?? '#'.$id;
        }

        return $names;
    }
}
