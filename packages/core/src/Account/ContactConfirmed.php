<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Core\Entity\User;

final readonly class ContactConfirmed
{
    public function __construct(public User $user, public ContactKind $kind, public string $contact)
    {
    }
}
