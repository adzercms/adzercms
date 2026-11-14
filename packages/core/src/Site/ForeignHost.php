<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Site;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\ExceptionInterface as Unmatched;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
final readonly class ForeignHost
{
    public function __construct(private Hosts $hosts, private RouterInterface $router)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $error = $event->getThrowable();
        $own = $this->router->getContext();
        $other = $this->hosts->other($own);
        if (!$event->isMainRequest() || !$error instanceof NotFoundHttpException || !$request->isMethodSafe() || null === $other
            || (!$this->hosts->serves(Hosts::SITE, $own->getHost()) && !$error->getPrevious() instanceof ResourceNotFoundException)) {
            return;
        }

        try {
            Hosts::match($this->router, $other, $request->getPathInfo());
        } catch (Unmatched) {
            return;
        }

        $query = $request->getQueryString();
        $event->setResponse(new RedirectResponse(Hosts::url($other, $request->getPathInfo().(null === $query ? '' : '?'.$query)), Response::HTTP_MOVED_PERMANENTLY));
    }
}
