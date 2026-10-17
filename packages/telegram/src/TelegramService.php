<?php

declare(strict_types=1);

namespace AdzerCMS\Telegram;

use AdzerCMS\Contracts\Login\Authorization;
use AdzerCMS\Contracts\Login\Callback;
use AdzerCMS\Contracts\Login\Client;
use AdzerCMS\Contracts\Login\Exchange;
use AdzerCMS\Contracts\Login\LoginService;
use AdzerCMS\Contracts\Login\Profile;
use AdzerCMS\Contracts\Module\Credentials;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final class TelegramService implements LoginService
{
    public const ISSUER = 'https://oauth.telegram.org';
    public const AUTHORIZE = self::ISSUER.'/auth';
    public const TOKEN = self::ISSUER.'/token';
    public const KEYS = self::ISSUER.'/.well-known/jwks.json';

    public const BOT_SCOPE = 'telegram:bot_access';

    private const SCOPE = 'openid profile phone '.self::BOT_SCOPE;

    public function name(): TranslatableInterface
    {
        return t('telegram.name');
    }

    public function color(): string
    {
        return '#2aabee';
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
        $client = $credentials->get(Client::Id->value);
        $token = $exchange->post(self::TOKEN, [
            'grant_type' => 'authorization_code',
            'code' => $callback->code,
            'redirect_uri' => $callback->redirect,
            'client_id' => $client,
            'code_verifier' => $callback->secret,
        ], Client::basic($credentials));
        $user = $exchange->claims($token->text('id_token') ?? '', self::KEYS, self::ISSUER, $client);
        $username = $user->text('preferred_username');

        return new Profile(
            $user->text('id') ?? '',
            phone: $user->text('phone_number'),
            firstName: $user->text('given_name'),
            lastName: $user->text('family_name'),
            account: null === $username ? $user->text('name') : '@'.$username,
        );
    }
}
