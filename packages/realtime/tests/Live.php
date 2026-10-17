<?php

declare(strict_types=1);

namespace AdzerCMS\Realtime\Tests;

use AdzerCMS\Core\Entity\User;
use AdzerCMS\Realtime\Channels;
use AdzerCMS\Realtime\Hub;
use AdzerCMS\Realtime\LiveHead;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

trait Live
{
    /** @return array<string, mixed>|null */
    private function head(User $user): ?array
    {
        self::service(TokenStorageInterface::class, 'security.token_storage')->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $requests = new RequestStack();
        $requests->push(Request::create('http://localhost/'));

        return (new LiveHead(self::service(Hub::class), self::service(Channels::class), self::service(Security::class, 'security.helper'), $requests))->fragment(null);
    }

    private function terminate(): void
    {
        $kernel = self::$kernel ?? self::fail('the kernel of the run is not booted');
        self::service(EventDispatcherInterface::class, 'event_dispatcher')->dispatch(new TerminateEvent($kernel, Request::create('/'), new Response()), KernelEvents::TERMINATE);
    }
}
