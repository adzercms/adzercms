<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Login\Fixture\Latch;

use AdzerCMS\Contracts\Login\Authorization;
use AdzerCMS\Contracts\Login\Callback;
use AdzerCMS\Contracts\Login\Exchange;
use AdzerCMS\Contracts\Login\LoginService;
use AdzerCMS\Contracts\Login\Profile;
use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Setting\Field;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final class LatchService implements LoginService
{
    public const SETTINGS = 'login.latch.';
    public const CLIENT = 'client';
    public const AUTHORIZE = 'https://latch.test/authorize';
    public const TOKEN = 'https://latch.test/token';
    public const PROFILE = 'https://latch.test/me';
    public const COLOR = '#2a6b4f';

    public function name(): TranslatableInterface
    {
        return t('Latch');
    }

    public function color(): string
    {
        return self::COLOR;
    }

    public function credentials(): array
    {
        return [new Field(self::CLIENT, t('Latch client'), required: true)];
    }

    public function authorization(Credentials $credentials): Authorization
    {
        return new Authorization(self::AUTHORIZE, $credentials->get(self::CLIENT), 'profile phone');
    }

    public function profile(Credentials $credentials, Callback $callback, Exchange $exchange): Profile
    {
        $token = $exchange->post(self::TOKEN, [
            'code' => $callback->code,
            'code_verifier' => $callback->secret,
            'redirect_uri' => $callback->redirect,
            'client_id' => $credentials->get(self::CLIENT),
        ]);
        $me = $exchange->get(self::PROFILE, ['Authorization' => 'Bearer '.$token->text('access_token')]);

        return new Profile($me->text('id') ?? '', phone: $me->text('phone'), account: $me->text('login'));
    }
}
