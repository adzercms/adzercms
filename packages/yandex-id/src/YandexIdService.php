<?php

declare(strict_types=1);

namespace AdzerCMS\YandexId;

use AdzerCMS\Contracts\Login\Authorization;
use AdzerCMS\Contracts\Login\Callback;
use AdzerCMS\Contracts\Login\Client;
use AdzerCMS\Contracts\Login\Exchange;
use AdzerCMS\Contracts\Login\LoginService;
use AdzerCMS\Contracts\Login\Profile;
use AdzerCMS\Contracts\Module\Credentials;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final class YandexIdService implements LoginService
{
    public const AUTHORIZE = 'https://oauth.yandex.ru/authorize';
    public const TOKEN = 'https://oauth.yandex.ru/token';
    public const USER_INFO = 'https://login.yandex.ru/info?format=json';

    private const SCOPE = 'login:info login:email login:default_phone';

    public function name(): TranslatableInterface
    {
        return t('yandexid.name');
    }

    public function color(): string
    {
        return '#fc3f1d';
    }

    public function credentials(): array
    {
        return [Client::Id->field(), Client::Secret->field()];
    }

    public function authorization(Credentials $credentials): Authorization
    {
        return new Authorization(self::AUTHORIZE, $credentials->get(Client::Id->value), self::SCOPE);
    }

    public function profile(Credentials $credentials, Callback $callback, Exchange $exchange): Profile
    {
        $token = $exchange->post(
            self::TOKEN,
            ['grant_type' => 'authorization_code', 'code' => $callback->code, 'code_verifier' => $callback->secret],
            Client::basic($credentials),
        );
        $user = $exchange->get(self::USER_INFO, ['Authorization' => 'OAuth '.$token->text('access_token')]);

        return new Profile(
            $user->text('id') ?? '',
            phone: $user->text('default_phone', 'number'),
            email: $user->text('default_email'),
            firstName: $user->text('first_name'),
            lastName: $user->text('last_name'),
            account: $user->text('login'),
        );
    }
}
