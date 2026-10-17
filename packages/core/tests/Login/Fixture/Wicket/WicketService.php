<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Login\Fixture\Wicket;

use AdzerCMS\Contracts\Login\Authorization;
use AdzerCMS\Contracts\Login\Callback;
use AdzerCMS\Contracts\Login\Exchange;
use AdzerCMS\Contracts\Login\LoginService;
use AdzerCMS\Contracts\Login\Profile;
use AdzerCMS\Contracts\Module\Credentials;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final class WicketService implements LoginService
{
    public const SETTINGS = 'login.wicket.';
    public const AUTHORIZE = 'https://wicket.test/authorize';
    public const PROFILE = 'user';

    public function name(): TranslatableInterface
    {
        return t('Wicket');
    }

    public function color(): string
    {
        return '#6b2a4f';
    }

    public function credentials(): array
    {
        return [];
    }

    public function authorization(Credentials $credentials): Authorization
    {
        return new Authorization(self::AUTHORIZE, 'wicket', 'openid', pkce: false, nonce: true, parameters: ['display' => 'page']);
    }

    public function profile(Credentials $credentials, Callback $callback, Exchange $exchange): Profile
    {
        return new Profile($callback->parameter(self::PROFILE));
    }
}
