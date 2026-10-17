<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Login;

use AdzerCMS\Contracts\Login\Answer;
use AdzerCMS\Contracts\Login\Exchange;
use AdzerCMS\Contracts\Login\LoginFailed;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Clock\now;
use function Symfony\Component\Translation\t;

#[Exclude]
final readonly class HttpExchange implements Exchange
{
    private const TIMEOUT = 15;
    private const OK = 200;
    private const LEEWAY = 60;

    /** @param array{local_cert?: string, local_pk?: string} $client */
    public function __construct(
        private HttpClientInterface $http,
        private TranslatableInterface $service,
        private array $client = [],
    ) {
    }

    public function post(string $url, array $form, array $headers = []): Answer
    {
        return new Answer($this->fields('POST', $url, ['body' => $form, 'headers' => $headers]));
    }

    public function get(string $url, array $headers = []): Answer
    {
        return new Answer($this->fields('GET', $url, ['headers' => $headers]));
    }

    public function status(string $method, string $url, array $headers = []): int
    {
        try {
            return $this->http->request($method, $url, ['headers' => $headers, 'timeout' => self::TIMEOUT] + $this->client)->getStatusCode();
        } catch (ExceptionInterface $failure) {
            throw new LoginFailed(t('core.login.silent', ['service' => $this->service]), $failure);
        }
    }

    public function claims(string $token, string $keys, string $issuer, string $audience): Answer
    {
        $set = $this->fields('GET', $keys, []);
        [$leeway, $timestamp] = [JWT::$leeway, JWT::$timestamp];
        [JWT::$leeway, JWT::$timestamp] = [self::LEEWAY, now()->getTimestamp()];
        try {
            $claims = (array) json_decode(json_encode(JWT::decode($token, JWK::parseKeySet($set)), \JSON_THROW_ON_ERROR), true);
        } catch (\UnexpectedValueException|\DomainException|\InvalidArgumentException|\JsonException $failure) {
            throw new LoginFailed(t('core.login.invalid', ['service' => $this->service]), $failure);
        } finally {
            [JWT::$leeway, JWT::$timestamp] = [$leeway, $timestamp];
        }

        $audiences = array_map(strval(...), array_filter((array) ($claims['aud'] ?? []), \is_scalar(...)));
        if ($issuer !== ($claims['iss'] ?? null) || !\in_array($audience, $audiences, true)) {
            throw new LoginFailed(t('core.login.alien', ['service' => $this->service]));
        }

        return new Answer($claims);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<array-key, mixed>
     */
    private function fields(string $method, string $url, array $options): array
    {
        try {
            $response = $this->http->request($method, $url, $options + ['timeout' => self::TIMEOUT] + $this->client);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (ExceptionInterface $failure) {
            throw new LoginFailed(t('core.login.silent', ['service' => $this->service]), $failure);
        }

        $decoded = json_decode($content, true);
        $fields = \is_array($decoded) ? $decoded : [];
        if (self::OK !== $status || isset($fields['error'])) {
            $answer = new Answer($fields);
            $error = $answer->text('error_description') ?? $answer->text('error') ?? (string) $status;
            throw new LoginFailed(t('core.login.declined', ['service' => $this->service, 'error' => $error]));
        }

        return $fields;
    }
}
