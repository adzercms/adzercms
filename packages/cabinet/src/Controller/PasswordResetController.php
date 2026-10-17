<?php

declare(strict_types=1);

namespace AdzerCMS\Cabinet\Controller;

use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Account\Password;
use AdzerCMS\Core\Captcha\Captcha;
use AdzerCMS\Core\Confirmation\ConfirmationService;
use AdzerCMS\Core\Confirmation\Purpose;
use AdzerCMS\Core\Notice\Notifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function Symfony\Component\Translation\t;

final class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly ConfirmationService $resets,
        private readonly Captcha $captcha,
        private readonly Notifier $notifier,
    ) {
    }

    #[Route('/forgot', name: 'cabinet_forgot', methods: ['GET', 'POST'])]
    public function forgot(Request $request): Response
    {
        $error = null;
        $code = $this->notifier->reachable(ContactKind::Phone);
        $login = trim($request->request->has('login') ? $request->request->getString('login') : $request->query->getString('login'));

        if ($request->isMethod('POST')) {
            if (!$this->captcha->passed($request)) {
                $error = t('cabinet.register.captcha');
            } else {
                $this->resets->request($login);
                $this->addFlash('success', t($code ? 'cabinet.reset.code_sent' : 'cabinet.reset.link_sent'));

                return $code
                    ? $this->redirectToRoute('cabinet_reset', ['login' => $login])
                    : $this->redirectToRoute('cabinet_login');
            }
        }

        return $this->render('@Cabinet/cabinet/forgot.html.twig', [
            'error' => $error,
            'login' => $login,
            'captcha' => $this->captcha->widget(),
            'code' => $code,
        ]);
    }

    #[Route('/reset', name: 'cabinet_reset', methods: ['GET', 'POST'])]
    public function reset(Request $request): Response
    {
        $login = $request->request->has('login') ? $request->request->getString('login') : $request->query->getString('login');
        $error = null;

        if ($request->isMethod('POST')) {
            $password = $request->request->getString('password');

            if (null !== $refusal = Password::refusal($password)) {
                $error = $refusal->reason;
            } elseif (null === $this->resets->complete($login, $request->request->getString('code'), $password)) {
                $error = t('cabinet.reset.code');
            } else {
                $this->addFlash('success', t('cabinet.reset.done'));

                return $this->redirectToRoute('cabinet_login');
            }
        }

        return $this->render('@Cabinet/cabinet/reset.html.twig', ['login' => $login, 'error' => $error]);
    }

    #[Route('/password/{token}', name: 'cabinet_password_change', requirements: ['token' => '[0-9a-f]{64}'], methods: ['GET', 'POST'])]
    public function change(Request $request, string $token): Response
    {
        $reset = $this->resets->pending(Purpose::Password, $token);
        if (null === $reset) {
            $this->addFlash('error', t('cabinet.reset.link'));

            return $this->redirectToRoute('cabinet_login');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $password = $request->request->getString('password');

            if (null !== $refusal = Password::refusal($password)) {
                $error = $refusal->reason;
            } elseif ($password !== $request->request->getString('repeat')) {
                $error = t('cabinet.register.mismatch');
            } else {
                $this->resets->completeLink($reset, $password);
                $this->addFlash('success', t('cabinet.reset.changed'));

                return $this->redirectToRoute('cabinet_login');
            }
        }

        return $this->render('@Cabinet/cabinet/password_change.html.twig', ['user' => $reset->requireUser(), 'error' => $error]);
    }
}
