<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Login;

use AdzerCMS\Contracts\Login\Callback;
use AdzerCMS\Contracts\Login\LoginFailed;
use AdzerCMS\Contracts\Login\LoginService;
use AdzerCMS\Contracts\Login\Profile;
use AdzerCMS\Contracts\Login\Redirects;
use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Setting\Keychain;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\Translation\t;

final class Authorizations implements Redirects
{
    public const CALLBACK = 'login_service_callback';

    private const FLIGHT = 'core.login.flight';

    public function __construct(
        private readonly Switches $switches,
        private readonly HttpClientInterface $http,
        private readonly UrlGeneratorInterface $urls,
        private readonly Keychain $keychain,
    ) {
    }

    public function authorize(string $service, Request $request): string|Refusal
    {
        $enabled = $this->module($service);
        if (null === $enabled) {
            return new Refusal(t('core.login.unavailable'));
        }
        [$module, $credentials] = $enabled;

        $flight = Flight::open($request->getSession(), self::FLIGHT, $service);
        $authorization = $module->authorization($credentials);
        $query = [
            'response_type' => 'code',
            'client_id' => $authorization->client,
            'redirect_uri' => $this->redirect($service),
            'state' => $flight->state,
            'scope' => $authorization->scope,
        ];
        if ($authorization->pkce) {
            $query += ['code_challenge' => $flight->challenge(), 'code_challenge_method' => 'S256'];
        }
        if ($authorization->nonce) {
            $query['nonce'] = $flight->secret;
        }

        return $authorization->url.'?'.http_build_query($query + $authorization->parameters);
    }

    public function state(Request $request): string
    {
        return Flight::held($request->getSession(), self::FLIGHT)->state ?? '';
    }

    public function profile(string $service, Request $request): Profile|Refusal
    {
        $flight = Flight::land($request->getSession(), self::FLIGHT, $service, $request->query->getString('state'));
        if (null === $flight) {
            return new Refusal(t('core.login.expired'));
        }

        $enabled = $this->module($service);
        if (null === $enabled) {
            return new Refusal(t('core.login.unavailable'));
        }
        [$module, $credentials] = $enabled;

        $parameters = array_filter($request->query->all(), \is_string(...));
        $code = $parameters['code'] ?? '';
        if ('' === $code) {
            return new Refusal(t('core.login.cancelled', ['service' => $module->name()]));
        }

        try {
            $profile = $module->profile($credentials, new Callback($code, $flight->state, $flight->secret, $this->redirect($service), $parameters), new HttpExchange($this->http, $module->name(), $this->client($module)));
        } catch (LoginFailed $failed) {
            return new Refusal($failed->reason);
        }

        return '' === $profile->id ? new Refusal(t('core.login.nameless', ['service' => $module->name()])) : $profile;
    }

    public function uri(LoginService $service): string
    {
        return $this->redirect($this->switches->key($service));
    }

    /** @return array<string, LoginService> */
    public function enabled(): array
    {
        return array_map(static fn (array $module): LoginService => $module[0], $this->switches->enabled(LoginService::class));
    }

    /** @return array{LoginService, Credentials}|null */
    private function module(string $service): ?array
    {
        return $this->switches->enabled(LoginService::class)[$service] ?? null;
    }

    /** @return array{local_cert?: string, local_pk?: string} */
    private function client(LoginService $module): array
    {
        foreach ($module->credentials() as $field) {
            if (Type::Container === $field->type) {
                return $this->keychain->client($this->switches->setting($module, $field)) ?? throw new LoginFailed(t('core.login.unavailable'));
            }
        }

        return [];
    }

    private function redirect(string $service): string
    {
        return $this->urls->generate(self::CALLBACK, ['service' => $service], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
