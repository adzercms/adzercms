<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use AdzerCMS\Core\Analytics\Marks;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

trait Arrivals
{
    protected const SITE_COOKIE = 'site_marks';

    /** @param array<string, string> $cookies */
    protected function land(string $url, ?Session $session = null, array $cookies = []): void
    {
        $request = Request::create($url, cookies: $cookies);
        $request->setSession($session ?? new Session(new MockArraySessionStorage()));

        self::service(RequestStack::class)->push($request);
        (self::service(Marks::class))(new RequestEvent(self::$kernel ?? self::fail('the kernel is not booted'), $request, HttpKernelInterface::MAIN_REQUEST));
    }

    /**
     * @param array<string, string> $marks
     *
     * @return array<string, string>
     */
    protected static function siteMarks(array $marks): array
    {
        return [self::SITE_COOKIE => json_encode($marks, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE)];
    }
}
