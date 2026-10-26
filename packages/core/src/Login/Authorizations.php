<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Login;

use AdzerCMS\Contracts\Login\Callback;
use AdzerCMS\Contracts\Login\LoginFailed;
use AdzerCMS\Contracts\Login\LoginService;
use AdzerCMS\Contracts\Login\Profile;
use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Setting\Keychain;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\Translation\t;

final class Authorizations
{
    public const CALLBACK = 'login_service_callback';

    private const FLIGHT = 'core.login.flight';
    private const SECRET_BYTES = 32;

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

        $state = self::token();
        $secret = self::token();
        $request->getSession()->set(self::FLIGHT, [$service, $state, $secret]);

        $authorization = $module->authorization($credentials);
        $query = [
            'response_type' => 'code',
            'client_id' => $authorization->client,
            'redirect_uri' => $this->redirect($service),
            'state' => $state,
            'scope' => $authorization->scope,
        ];
        if ($authorization->pkce) {
            $query += ['code_challenge' => self::encode(hash('sha256', $secret, true)), 'code_challenge_method' => 'S256'];
        }
        if ($authorization->nonce) {
            $query['nonce'] = $secret;
        }

        return $authorization->url.'?'.http_build_query($query + $authorization->parameters);
    }

    public function state(Request $request): string
    {
        return self::flight($request->getSession()->get(self::FLIGHT))[1];
    }

    public function profile(string $service, Request $request): Profile|Refusal
    {
        [$kept, $state, $secret] = self::flight($request->getSession()->remove(self::FLIGHT));
        if ($kept !== $service || '' === $state || !hash_equals($state, $request->query->getString('state'))) {
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
            $profile = $module->profile($credentials, new Callback($code, $state, $secret, $this->redirect($service), $parameters), new HttpExchange($this->http, $module->name(), $this->client($module)));
        } catch (LoginFailed $failed) {
            return new Refusal($failed->reason);
        }

        return '' === $profile->id ? new Refusal(t('core.login.nameless', ['service' => $module->name()])) : $profile;
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

    /** @return array{string, string, string} */
    private static function flight(mixed $flight): array
    {
        if (!\is_array($flight) || 3 !== \count($flight)) {
            return ['', '', ''];
        }
        [$service, $state, $secret] = array_map(strval(...), array_values($flight));

        return [$service, $state, $secret];
    }

    private static function token(): string
    {
        return self::encode(random_bytes(self::SECRET_BYTES));
    }

    private static function encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
