<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Login;

interface Redirects
{
    public function uri(LoginService $service): string;
}
