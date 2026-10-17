<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Login;

use AdzerCMS\Contracts\Login\Profile;
use AdzerCMS\Core\Entity\User;

final readonly class ServiceConnected
{
    public function __construct(public User $user, public string $service, public Profile $profile)
    {
    }
}
