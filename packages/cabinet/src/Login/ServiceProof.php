<?php

declare(strict_types=1);

namespace AdzerCMS\Cabinet\Login;

use AdzerCMS\Cabinet\ContactFlash;
use AdzerCMS\Contracts\Login\LoginListener;
use AdzerCMS\Contracts\Login\Profile;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Confirmation\ConfirmationService;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Login\Authorizations;
use AdzerCMS\Core\Login\ConnectedServices;
use AdzerCMS\Core\Repository\ConnectedServiceRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

final readonly class ServiceProof implements LoginListener
{
    private const FLIGHT = 'cabinet.email.service';

    public function __construct(
        private Authorizations $authorizations,
        private ConnectedServices $services,
        private ConnectedServiceRepository $connected,
        private ConfirmationService $confirmations,
        private Security $security,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function start(string $service, string $contact, Request $request): string|Refusal
    {
        $authorization = $this->authorizations->authorize($service, $request);
        if (\is_string($authorization)) {
            $request->getSession()->set(self::FLIGHT, [$this->authorizations->state($request), $contact]);
        }

        return $authorization;
    }

    public function entered(string $service, Profile $profile, Request $request): ?Response
    {
        $session = $request->getSession();
        $flight = $session->get(self::FLIGHT);
        $user = $this->security->getUser();
        if (!\is_array($flight) || ($flight[0] ?? null) !== $request->query->getString('state') || !$user instanceof User) {
            return null;
        }

        $session->remove(self::FLIGHT);
        $contact = \is_string($flight[1] ?? null) ? $flight[1] : '';
        if ($this->connected->profile($service, $profile->id)?->getUser() !== $user) {
            return $this->back($request, 'error', t('cabinet.email.stranger', ['service' => $this->services->name($service)]), 'cabinet_profile_email', ['contact' => $contact]);
        }

        [$kind, $message] = ContactFlash::of($this->confirmations->releaseTo($user, $contact), $contact, $user);

        return $this->back($request, $kind, $message, 'cabinet_profile');
    }

    /** @param array<string, string> $parameters */
    private function back(Request $request, string $kind, TranslatableMessage $message, string $route, array $parameters = []): Response
    {
        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($kind, $message);
        }

        return new RedirectResponse($this->urls->generate($route, $parameters));
    }
}
