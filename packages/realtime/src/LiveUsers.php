<?php

declare(strict_types=1);

namespace AdzerCMS\Realtime;

use AdzerCMS\Core\Entity\User;

final readonly class LiveUsers implements LiveEntities
{
    public function fields(): array
    {
        return [User::class => ['status']];
    }

    public function owner(object $entity): User
    {
        \assert($entity instanceof User);

        return $entity;
    }

    public function staff(): bool
    {
        return false;
    }
}
