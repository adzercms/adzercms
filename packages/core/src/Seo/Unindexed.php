<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Seo;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE)]
final class Unindexed
{
    public const ROUTE = '_unindexed';

    private const HEADER = 'X-Robots-Tag';
    private const CLOSED = 'noindex, nofollow';

    public function __invoke(ResponseEvent $event): void
    {
        if ($event->getRequest()->attributes->getBoolean(self::ROUTE)) {
            $event->getResponse()->headers->set(self::HEADER, self::CLOSED);
        }
    }
}
