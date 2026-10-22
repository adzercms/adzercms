<?php

declare(strict_types=1);

namespace AdzerCMS\Realtime;

use AdzerCMS\Core\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface LiveEntities
{
    /** @return array<class-string, list<string>> */
    public function fields(): array;

    public function owner(object $entity): ?User;

    public function staff(): bool;
}
