<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Login;

use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Module\Switchable;
use Symfony\Contracts\Translation\TranslatableInterface;

interface LoginService extends Switchable
{
    public function name(): TranslatableInterface;

    public function color(): string;

    public function authorization(Credentials $credentials): Authorization;

    /** @throws LoginFailed */
    public function profile(Credentials $credentials, Callback $callback, Exchange $exchange): Profile;
}
