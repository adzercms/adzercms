<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Attribute\AsTwigFunction;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 8)]
final class CsrfGuard
{
    public const TOKEN_ID = 'panel';
    public const FIELD = '_token';
    public const OWN = '_csrf_own';

    public function __construct(private readonly CsrfTokenManagerInterface $tokens)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || !$request->isMethod('POST') || true === $request->attributes->get(self::OWN)) {
            return;
        }

        if (!$this->tokens->isTokenValid(new CsrfToken(self::TOKEN_ID, $request->request->getString(self::FIELD)))) {
            throw new AccessDeniedHttpException('stale form token');
        }
    }

    #[AsTwigFunction('csrf_field', isSafe: ['html'])]
    public function field(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::FIELD,
            htmlspecialchars($this->tokens->getToken(self::TOKEN_ID)->getValue(), \ENT_QUOTES),
        );
    }
}
