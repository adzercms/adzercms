<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Firewall\SwitchUserListener;
use Symfony\Component\Security\Http\HttpUtils;

#[AsEventListener]
final class SwitchReturn
{
    public function __construct(
        private readonly Security $security,
        private readonly HttpUtils $http,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $thrown = $event->getThrowable();
        if ((!$thrown instanceof AccessDeniedException && !$thrown->getPrevious() instanceof AccessDeniedException) || null === $this->security->getUser()) {
            return;
        }

        $request = $event->getRequest();
        $event->setResponse($this->security->isGranted(AuthenticatedVoter::IS_IMPERSONATOR)
            ? new RedirectResponse($request->getPathInfo().'?'.http_build_query(['_switch_user' => SwitchUserListener::EXIT_VALUE] + $request->query->all()))
            : $this->http->createRedirectResponse($request, '/'));
    }
}
