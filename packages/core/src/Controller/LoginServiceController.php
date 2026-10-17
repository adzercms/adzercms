<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Controller;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Ui\CabinetEntry;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Login\Authorizations;
use AdzerCMS\Core\Login\ConnectedServices;
use AdzerCMS\Core\Ui\Entries;
use AdzerCMS\Core\Ui\Intent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

#[AsController]
#[Route(requirements: ['service' => '[a-z0-9_]+'], methods: ['GET'])]
final class LoginServiceController
{
    private const LOGIN = 'login';

    public function __construct(
        private readonly Authorizations $authorizations,
        private readonly ConnectedServices $connected,
        private readonly Security $security,
        private readonly HttpUtils $http,
        private readonly Intent $intent,
        private readonly Entries $entries,
    ) {
    }

    #[Route('/login/{service}', name: 'login_service')]
    public function start(Request $request, string $service, #[CurrentUser] ?User $user): Response
    {
        $authorization = $this->authorizations->authorize($service, $request);

        return $authorization instanceof Refusal ? $this->refused($request, $user, $authorization) : new RedirectResponse($authorization);
    }

    #[Route('/login/{service}/callback', name: Authorizations::CALLBACK)]
    public function callback(Request $request, string $service, #[CurrentUser] ?User $user): Response
    {
        $profile = $this->authorizations->profile($service, $request);
        if ($profile instanceof Refusal) {
            return $this->refused($request, $user, $profile);
        }

        if (null === $user) {
            $entered = $this->connected->enter($service, $profile);

            return $entered instanceof Refusal
                ? $this->refused($request, null, $entered)
                : $this->security->login($entered, 'form_login') ?? throw new \LogicException('form login answers its success');
        }

        $refusal = $this->connected->link($user, $service, $profile);
        if (null !== $refusal) {
            return $this->refused($request, $user, $refusal);
        }

        return $this->back($request, 'success', t('core.login.connected', ['service' => $this->connected->name($service)]), $this->intent->take() ?? $this->home($request, $user));
    }

    private function refused(Request $request, ?User $user, Refusal $refusal): Response
    {
        return $this->back($request, 'error', $refusal->reason, $this->intent->detour($refusal, $this->home($request, $user)));
    }

    private function back(Request $request, string $kind, TranslatableInterface $message, string $target): Response
    {
        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($kind, $message);
        }

        return $this->http->createRedirectResponse($request, $target);
    }

    private function home(Request $request, ?User $user): string
    {
        return null === $user ? $this->http->generateUri($request, self::LOGIN) : $this->entries->url(CabinetEntry::Profile) ?? $this->entries->home();
    }
}
