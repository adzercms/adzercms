<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Account\Fixture;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\AccountPolicy;
use AdzerCMS\Core\Entity\User;

final class OpenPolicy implements AccountPolicy
{
    public bool $registration = false;
    public bool $deletion = false;
    public ?Refusal $refused = null;

    public function registration(): bool
    {
        return $this->registration;
    }

    public function registered(User $user): void
    {
    }

    public function deletion(): bool
    {
        return $this->deletion;
    }

    public function deletionRefusal(User $user): ?Refusal
    {
        return $this->refused;
    }

    public function removalRefusal(User $user): ?Refusal
    {
        return null;
    }

    public function archived(User $user): void
    {
    }

    public function changed(User $user): void
    {
    }

    public function removing(User $user): void
    {
    }
}
