<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Login;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface LoginListener
{
    public function entered(string $service, Profile $profile, Request $request): ?Response;
}
