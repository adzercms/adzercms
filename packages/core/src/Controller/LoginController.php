<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Controller;

use AdzerCMS\Core\Security\CsrfGuard;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class LoginController
{
    #[Route('/login', name: 'login', defaults: [CsrfGuard::OWN => true], methods: ['POST'])]
    public function __invoke(): never
    {
        throw new \LogicException('the login form is handled by the firewall');
    }
}
