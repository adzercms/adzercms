<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Security;

use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Ui\Entries;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

final class Entry
{
    public function __construct(
        private readonly Security $security,
        private readonly Entries $entries,
    ) {
    }

    public function open(User $user): Response
    {
        return $this->security->login($user, 'form_login')
            ?? new RedirectResponse($this->entries->home());
    }
}
