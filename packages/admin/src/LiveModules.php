<?php

declare(strict_types=1);

namespace AdzerCMS\Admin;

use AdzerCMS\Core\Entity\ModuleOperation;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Realtime\LiveEntities;

final readonly class LiveModules implements LiveEntities
{
    public function fields(): array
    {
        return [ModuleOperation::class => ['state']];
    }

    public function owner(object $entity): ?User
    {
        return null;
    }

    public function staff(): bool
    {
        return true;
    }
}
