<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Login\Fixture;

use AdzerCMS\Contracts\Login\LoginListener;
use AdzerCMS\Contracts\Login\Profile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CatchListener implements LoginListener
{
    public const PROFILE = 'caught';

    public function entered(string $service, Profile $profile, Request $request): ?Response
    {
        return self::PROFILE === $profile->id ? new Response($service.' '.$profile->id) : null;
    }
}
