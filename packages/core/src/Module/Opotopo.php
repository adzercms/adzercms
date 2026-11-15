<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Login\Answer;
use AdzerCMS\Contracts\Login\LoginFailed;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Login\Flight;
use AdzerCMS\Core\Login\HttpExchange;
use AdzerCMS\Core\Setting\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Clock\now;
use function Symfony\Component\Translation\t;

final readonly class Opotopo
{
    public const API = 'https://api.opotopo.com';
    public const RESOURCE = self::API.'/rest/v1/order/public/packages';

    private const METADATA = self::API.'/.well-known/oauth-authorization-server';
    private const FLIGHT = 'core.opotopo.flight';
    private const CLIENT = 'modules.opotopo.client';
    private const REFRESH = 'modules.opotopo.refresh';
    private const ACCESS = 'modules.opotopo.access';
    private const EXPIRES = 'modules.opotopo.expires';

    public function __construct(
        private HttpClientInterface $http,
        private Settings $settings,
        private EntityManagerInterface $em,
    ) {
    }

    public function authorize(SessionInterface $session, string $client, string $redirect): string|Refusal
    {
        try {
            $endpoint = $this->endpoint('authorization_endpoint');
        } catch (LoginFailed $failed) {
            return new Refusal($failed->reason);
        }
        $flight = Flight::open($session, self::FLIGHT, $client);

        return $endpoint.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $client,
            'redirect_uri' => $redirect,
            'state' => $flight->state,
            'code_challenge' => $flight->challenge(),
            'code_challenge_method' => 'S256',
            'resource' => self::RESOURCE,
        ]);
    }

    /** @param array<string, string> $answer */
    public function connect(SessionInterface $session, array $answer, string $client, string $redirect): ?Refusal
    {
        $flight = Flight::land($session, self::FLIGHT, $client, $answer['state'] ?? '');
        if (null === $flight) {
            return new Refusal(t('core.login.expired'));
        }
        if (isset($answer['error'])) {
            return new Refusal(t('core.login.declined', ['service' => self::name(), 'error' => $answer['error_description'] ?? $answer['error']]));
        }
        if (!isset($answer['code'])) {
            return new Refusal(t('core.login.cancelled', ['service' => self::name()]));
        }

        try {
            if (self::API !== ($answer['iss'] ?? null)) {
                throw new LoginFailed(t('core.login.alien', ['service' => self::name()]));
            }
            $tokens = $this->exchange()->post($this->endpoint('token_endpoint'), [
                'grant_type' => 'authorization_code',
                'code' => $answer['code'],
                'redirect_uri' => $redirect,
                'client_id' => $client,
                'code_verifier' => $flight->secret,
                'resource' => self::RESOURCE,
            ]);
            $this->keep($client, $tokens);
        } catch (LoginFailed $failed) {
            return new Refusal($failed->reason);
        }

        return null;
    }

    public function connected(): bool
    {
        return '' !== $this->settings->get(self::REFRESH);
    }

    public function disconnect(): ?Refusal
    {
        $refusal = null;
        try {
            $this->exchange()->post($this->endpoint('revocation_endpoint'), [
                'token' => $this->settings->get(self::REFRESH),
                'token_type_hint' => 'refresh_token',
                'client_id' => $this->settings->get(self::CLIENT),
            ]);
        } catch (LoginFailed $failed) {
            $refusal = new Refusal($failed->reason);
        }
        foreach ([self::CLIENT, self::REFRESH, self::ACCESS, self::EXPIRES] as $name) {
            $this->settings->clear(self::field($name));
        }
        $this->em->flush();

        return $refusal;
    }

    private function keep(string $client, Answer $tokens): void
    {
        $access = $tokens->text('access_token');
        $refresh = $tokens->text('refresh_token');
        $lifetime = (int) $tokens->text('expires_in');
        if (null === $access || null === $refresh || $lifetime <= 0) {
            throw new LoginFailed(t('core.login.invalid', ['service' => self::name()]));
        }

        $this->settings->save(self::field(self::CLIENT), $client);
        $this->settings->save(self::field(self::REFRESH), $refresh);
        $this->settings->save(self::field(self::ACCESS), $access);
        $this->settings->stamp(self::field(self::EXPIRES), now()->modify(\sprintf('+%d seconds', $lifetime)));
        $this->em->flush();
    }

    private function endpoint(string $name): string
    {
        $server = $this->exchange()->get(self::METADATA);
        $endpoint = $server->text($name);
        if (self::API !== $server->text('issuer') || null === $endpoint) {
            throw new LoginFailed(t('core.login.alien', ['service' => self::name()]));
        }

        return $endpoint;
    }

    private function exchange(): HttpExchange
    {
        return new HttpExchange($this->http, self::name());
    }

    private static function field(string $name): Field
    {
        return new Field($name, self::name(), \in_array($name, [self::REFRESH, self::ACCESS], true) ? Type::Secret : Type::Text);
    }

    private static function name(): TranslatableInterface
    {
        return t('core.opotopo.name');
    }
}
