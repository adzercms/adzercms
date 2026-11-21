<?php

declare(strict_types=1);

namespace AdzerCMS\Cabinet\Controller;

use AdzerCMS\Core\Account\Registration;
use AdzerCMS\Core\Login\Authorizations;
use AdzerCMS\Core\Security\LoginTarget;
use AdzerCMS\Core\Security\RecordDeleted;
use AdzerCMS\Core\Ui\Back;
use AdzerCMS\Core\Ui\Fragment;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class SecurityController extends AbstractController
{
    use TargetPathTrait;

    #[Route('/login', name: 'cabinet_login', methods: ['GET'])]
    public function login(Request $request, AuthenticationUtils $utils, Authorizations $logins, Registration $registration): Response
    {
        if ($this->getUser()) {
            $home = $this->generateUrl('cabinet_account');

            return $request->headers->has('HX-Request') ? Fragment::go($home) : $this->redirect($home);
        }

        $back = Back::local($request->query->getString(Back::FIELD));
        if (null !== $back) {
            $this->saveTargetPath($request->getSession(), LoginTarget::FIREWALL, $back);
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
