<?php

declare(strict_types=1);

namespace AdzerCMS\Cabinet\Controller;

use AdzerCMS\Core\Account\Registration;
use AdzerCMS\Core\Login\Authorizations;
use AdzerCMS\Core\Security\RecordDeleted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'cabinet_login', methods: ['GET'])]
    public function login(AuthenticationUtils $utils, Authorizations $logins, Registration $registration): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('cabinet_account');
        }

        $error = $utils->getLastAuthenticationError();

        return $this->render('@Cabinet/cabinet/login.html.twig', [
            'error' => $error,
            'restore' => $error instanceof RecordDeleted,
            'login' => $utils->getLastUsername(),
            'logins' => $logins->enabled(),
            'register' => $registration->offered(),
        ]);
    }
}
