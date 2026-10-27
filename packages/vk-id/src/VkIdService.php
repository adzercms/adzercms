<?php

declare(strict_types=1);

namespace AdzerCMS\VkId;

use AdzerCMS\Contracts\Login\Authorization;
use AdzerCMS\Contracts\Login\Callback;
use AdzerCMS\Contracts\Login\Client;
use AdzerCMS\Contracts\Login\Exchange;
use AdzerCMS\Contracts\Login\LoginService;
use AdzerCMS\Contracts\Login\Profile;
use AdzerCMS\Contracts\Login\Redirects;
use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Setting\Guided;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final class VkIdService implements LoginService, Guided
{
    public const AUTHORIZE = 'https://id.vk.ru/authorize';
    public const TOKEN = 'https://id.vk.ru/oauth2/auth';
    public const USER_INFO = 'https://id.vk.ru/oauth2/user_info';

    private const SCOPE = 'vkid.personal_info email phone';
    private const DEVICE = 'device_id';

    public function __construct(
        private readonly Redirects $redirects,
    ) {
    }

    public function name(): TranslatableInterface
    {
        return t('vkid.name');
    }

    public function color(): string
    {
        return '#0077ff';
    }

    public function credentials(): array
    {
        return [Client::Id->field()];
    }

    public function guide(): array
    {
        $url = $this->redirects->uri($this);

        return [t('vkid.guide.app'), t('vkid.guide.redirect', ['host' => parse_url($url, \PHP_URL_HOST), 'url' => $url]), t('vkid.guide.profile'), t('vkid.guide.access'), t('vkid.guide.client')];
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
            'code_verifier' => $callback->secret,
            'client_id' => $client,
            self::DEVICE => $callback->parameter(self::DEVICE),
            'redirect_uri' => $callback->redirect,
            'state' => $callback->state,
        ]);
        $user = $exchange->post(self::USER_INFO, ['client_id' => $client, 'access_token' => $token->text('access_token') ?? '']);

        return new Profile(
            $user->text('user', 'user_id') ?? '',
            phone: $user->text('user', 'phone'),
            email: $user->text('user', 'email'),
            firstName: $user->text('user', 'first_name'),
            lastName: $user->text('user', 'last_name'),
        );
    }
}
