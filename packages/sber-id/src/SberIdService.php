<?php

declare(strict_types=1);

namespace AdzerCMS\SberId;

use AdzerCMS\Contracts\Login\Attesting;
use AdzerCMS\Contracts\Login\Authorization;
use AdzerCMS\Contracts\Login\Callback;
use AdzerCMS\Contracts\Login\Client;
use AdzerCMS\Contracts\Login\Exchange;
use AdzerCMS\Contracts\Login\LoginFailed;
use AdzerCMS\Contracts\Login\Profile;
use AdzerCMS\Contracts\Login\Redirects;
use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Guided;
use AdzerCMS\Contracts\Setting\Type;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final readonly class SberIdService implements Attesting, Guided
{
    public const AUTHORIZE = 'https://id.sber.ru/CSAFront/oidc/authorize.do';
    public const TOKEN = 'https://oauth.sber.ru/ru/prod/tokens/v2/oidc';
    public const USER_INFO = 'https://oauth.sber.ru/ru/prod/sberbankid/v2.1/userinfo';
    public const COMPLETED = 'https://oauth.sber.ru/api/v2/auth/completed';
    public const CONTAINER = 'container';

    private const SCOPE = 'openid name email mobile';
    private const CLIENT_TYPE = 'PRIVATE';
    private const ACCEPTED = 204;

    public function __construct(
        private Redirects $redirects,
    ) {
    }

    public function name(): TranslatableInterface
    {
        return t('sberid.name');
    }

    public function color(): string
    {
        return '#21a038';
    }

    public function credentials(): array
    {
        return [Client::Id->field(), Client::Secret->field(), new Field(self::CONTAINER, t('sberid.container'), Type::Container, required: true)];
    }

    public function guide(): array
    {
        return [t('sberid.guide.offer'), t('sberid.guide.app', ['url' => $this->redirects->uri($this)]), t('sberid.guide.client'), t('sberid.guide.certificate')];
    }

    public function authorization(Credentials $credentials): Authorization
    {
        return new Authorization(self::AUTHORIZE, $credentials->get(Client::Id->value), self::SCOPE, pkce: false, nonce: true, parameters: ['client_type' => self::CLIENT_TYPE]);
    }

    public function profile(Credentials $credentials, Callback $callback, Exchange $exchange): Profile
    {
        $token = $exchange->post(self::TOKEN, [
            'grant_type' => 'authorization_code',
            'code' => $callback->code,
            'client_id' => $credentials->get(Client::Id->value),
            'client_secret' => $credentials->get(Client::Secret->value),
            'redirect_uri' => $callback->redirect,
        ], ['RqUID' => self::rqUid(), 'Accept' => 'application/json']);
        $access = $token->text('access_token') ?? '';
        $claims = self::claims($token->text('id_token') ?? '');
        if (!\in_array($credentials->get(Client::Id->value), array_map(strval(...), array_filter((array) ($claims['aud'] ?? []), \is_scalar(...))), true)) {
            throw new LoginFailed(t('core.login.alien', ['service' => $this->name()]));
        }
        if (!hash_equals($callback->secret, \is_string($claims['nonce'] ?? null) ? $claims['nonce'] : '')) {
            throw new LoginFailed(t('sberid.foreign'));
        }

        $user = $exchange->get(self::USER_INFO, ['Authorization' => 'Bearer '.$access, 'x-introspect-rquid' => self::rqUid(), 'Accept' => 'application/json']);
        if (self::ACCEPTED !== $exchange->status('GET', self::COMPLETED, ['Authorization' => 'Bearer '.$access, 'rquid' => self::rqUid()])) {
            throw new LoginFailed(t('sberid.unconfirmed'));
        }

        return new Profile(
            $user->text('sub') ?? '',
            phone: $user->text('phone_number'),
            email: $user->text('email'),
            firstName: $user->text('given_name'),
            lastName: $user->text('family_name'),
        );
    }

    /** @return array<array-key, mixed> */
    private static function claims(string $token): array
    {
        $parts = explode('.', $token);
        $payload = 3 === \count($parts) ? base64_decode(strtr($parts[1], '-_', '+/'), true) : false;
        $claims = false === $payload ? null : json_decode($payload, true);

        return \is_array($claims) ? $claims : [];
    }

    private static function rqUid(): string
    {
        return bin2hex(random_bytes(16));
    }
}
