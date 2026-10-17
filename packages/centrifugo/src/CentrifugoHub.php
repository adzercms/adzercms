<?php

declare(strict_types=1);

namespace AdzerCMS\Centrifugo;

use AdzerCMS\Realtime\RealtimeHub;
use Firebase\JWT\JWT;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\Clock\now;

final class CentrifugoHub implements RealtimeHub
{
    private const TOKEN_TTL = 3600;
    private const PUBLISH_TIMEOUT = 1.0;
    private const ALGORITHM = 'HS256';
    private const PERSONAL = 'personal:#';
    private const CLIENT = 'bundles/centrifugo/client.js';

    /** @param \Closure(): HttpClientInterface $http */
    public function __construct(
        #[Autowire('%env(string:default::REALTIME_URL)%')] private readonly string $url,
        #[Autowire('%env(string:default::REALTIME_WS_PATH)%')] private readonly string $path,
        #[Autowire('%env(string:default::REALTIME_TOKEN_SECRET)%')] #[\SensitiveParameter] private readonly string $secret,
        #[Autowire('%env(string:default::REALTIME_API_KEY)%')] #[\SensitiveParameter] private readonly string $key,
        #[AutowireServiceClosure('http_client')] private readonly \Closure $http,
    ) {
    }

    public function ready(): bool
    {
        return '' !== $this->url && '' !== $this->path && '' !== $this->secret && '' !== $this->key;
    }

    public function endpoint(Request $request): string
    {
        return \sprintf('%s://%s%s', $request->isSecure() ? 'wss' : 'ws', $request->getHttpHost(), $this->path);
    }

    public function personal(string $subject): string
    {
        return self::PERSONAL.$subject;
    }

    public function publish(array $channels, array $data): void
    {
        if ([] === $channels) {
            return;
        }

        try {
            ($this->http)()->request('POST', rtrim($this->url, '/').'/api/broadcast', [
                'headers' => ['X-API-Key' => $this->key],
                'json' => ['channels' => $channels, 'data' => $data],
                'timeout' => self::PUBLISH_TIMEOUT,
                'max_duration' => self::PUBLISH_TIMEOUT,
            ])->getStatusCode();
        } catch (ExceptionInterface) {
            return;
        }
    }

    public function connectionToken(string $subject): string
    {
        return $this->token(['sub' => $subject]);
    }

    public function subscriptionToken(string $subject, string $channel): string
    {
        return $this->token(['sub' => $subject, 'channel' => $channel]);
    }

    public function client(): string
    {
        return self::CLIENT;
    }

    /** @param array<string, string> $claims */
    private function token(array $claims): string
    {
        return JWT::encode($claims + ['exp' => now()->getTimestamp() + self::TOKEN_TTL], $this->secret, self::ALGORITHM);
    }
}
