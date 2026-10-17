<?php

declare(strict_types=1);

namespace AdzerCMS\Realtime;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class Channels
{
    private const STAFF = 'staff:';
    private const INSTANCE = '@';

    private readonly string $instance;

    public function __construct(#[Autowire('%env(DEFAULT_URI)%')] string $uri)
    {
        $this->instance = (string) parse_url($uri, \PHP_URL_HOST);
    }

    public function subject(User $user): string
    {
        return $user->getId().self::INSTANCE.$this->instance;
    }

    public function staff(): string
    {
        return self::STAFF.$this->instance;
    }

    /** @return list<string> */
    public function granted(User $user): array
    {
        return Role::Admin === $user->getRole() ? [$this->staff()] : [];
    }
}
