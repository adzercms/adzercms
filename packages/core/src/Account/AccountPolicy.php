<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface AccountPolicy
{
    public function registration(): bool;

    public function registered(User $user): void;

    public function deletion(): bool;

    public function deletionRefusal(User $user): ?Refusal;

    public function removalRefusal(User $user): ?Refusal;

    public function archived(User $user): void;

    public function changed(User $user): void;

    public function removing(User $user): void;
}
