<?php

declare(strict_types=1);

namespace AdzerCMS\Cabinet\Controller;

use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Account\ContactOutcome;
use AdzerCMS\Core\Account\Password;
use AdzerCMS\Core\Account\Registration;
use AdzerCMS\Core\Captcha\Captcha;
use AdzerCMS\Core\Confirmation\ConfirmationService;
use AdzerCMS\Core\Login\Authorizations;
use AdzerCMS\Core\Notice\Notifier;
use AdzerCMS\Core\Security\Entry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function Symfony\Component\Translation\t;

final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly ConfirmationService $confirmations,
        private readonly Registration $registration,
        private readonly Authorizations $logins,
        private readonly Captcha $captcha,
        private readonly Entry $entry,
        private readonly Notifier $notifier,
    ) {
    }

    #[Route('/register', name: 'cabinet_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        $this->open();
        $contact = trim($request->request->getString('contact'));
        $error = null;

        if ($request->isMethod('POST')) {
            $error = match (true) {
                !$this->captcha->passed($request) => t('cabinet.register.captcha'),
                default => match ($this->confirmations->requestRegistration($contact)) {
                    ContactOutcome::Sent => null,
                    ContactOutcome::TooOften => t('core.confirmation.too_often'),
                    default => t('cabinet.register.unavailable'),
                },
            };

            if (null === $error) {
                return $this->redirectToRoute('cabinet_register_confirm', ['contact' => $contact]);
            }
        }

        $kinds = $this->notifier->reachableContacts();
        $wanted = '' === $contact ? ContactKind::tryFrom($request->query->getString('kind')) : ContactKind::of($contact);

        return $this->render('@Cabinet/cabinet/register.html.twig', [
            'contact' => $contact,
            'error' => $error,
            'captcha' => $this->captcha->widget(),
            'logins' => $this->logins->enabled(),
            'kinds' => $kinds,
            'active' => \in_array($wanted, $kinds, true) ? $wanted : ($kinds[0] ?? null),
        ]);
    }

    #[Route('/register/confirm', name: 'cabinet_register_confirm', methods: ['GET', 'POST'])]
    public function confirm(Request $request): Response
    {
        $this->open();
        $contact = $request->request->has('contact') ? $request->request->getString('contact') : $request->query->getString('contact');
        $error = null;

        if ($request->isMethod('POST')) {
            $password = $request->request->getString('password');
            $error = Password::refusal($password)->reason
                ?? ($password !== $request->request->getString('repeat') ? t('cabinet.register.mismatch') : null);

            if (null === $error) {
                $user = $this->confirmations->completeRegistration($contact, $request->request->getString('code'), $password);

                if (null !== $user) {
                    $this->addFlash('success', t('cabinet.register.done'));

                    return $this->entry->open($user);
                }

                $error = t('cabinet.register.code');
            }
        }

        return $this->render('@Cabinet/cabinet/register_confirm.html.twig', ['contact' => $contact, 'error' => $error]);
    }

    private function open(): void
    {
        if (!$this->registration->offered()) {
            throw $this->createNotFoundException();
        }
    }
}
