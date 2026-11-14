<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Site;

use AdzerCMS\Core\Setting\Language;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE, priority: -8)]
final class PublicCache
{
    public const MAX_AGE = 60;
    public const STALE_WHILE_REVALIDATE = 600;
    public const STALE_IF_ERROR = 86400;

    private const FRAGMENT = 'HX-Request';
    private const LANGUAGE = 'Accept-Language';

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        if (!$event->isMainRequest() || Hosts::SITE !== $request->attributes->get(Hosts::ROLE) || !$request->isMethodCacheable()) {
            return;
        }

        $response->setVary([self::FRAGMENT, ...$request->attributes->getBoolean(Language::VISITOR) ? [self::LANGUAGE] : []], false);
        if ($response->getStatusCode() < Response::HTTP_BAD_REQUEST && [] === $response->headers->getCookies() && !$request->hasPreviousSession() && !($request->hasSession(true) && $request->getSession()->isStarted())) {
            $response->setCache(['public' => true, 'max_age' => self::MAX_AGE, 'stale_while_revalidate' => self::STALE_WHILE_REVALIDATE, 'stale_if_error' => self::STALE_IF_ERROR]);
            $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        }
    }
}
