<?php

declare(strict_types=1);

namespace AdzerCMS\YandexMetrika;

use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Module\Switches;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function Symfony\Component\Translation\t;

final readonly class YandexMetrikaApi
{
    public function __construct(private HttpClientInterface $httpClient, private Switches $switches)
    {
    }

    public function available(): bool
    {
        return null !== $this->access();
    }

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): ResponseInterface|Refusal
    {
        $access = $this->access();

        if (null === $access) {
            return new Refusal(t('yandexmetrika.closed'));
        }

        $options['headers'] = ['Authorization' => 'OAuth '.trim($access->get(YandexMetrikaCounter::TOKEN))] + (array) ($options['headers'] ?? []);

        return $this->httpClient->request($method, \sprintf($url, rawurlencode(trim($access->get(YandexMetrikaCounter::COUNTER)))), $options);
    }

    /** @return array<mixed>|Refusal */
    public static function answer(ResponseInterface|Refusal $response): array|Refusal
    {
        if ($response instanceof Refusal) {
            return $response;
        }

        try {
            if (200 === $response->getStatusCode()) {
                return $response->toArray();
            }

            try {
                $answer = $response->toArray(false);
            } catch (DecodingExceptionInterface) {
                $answer = [];
            }

            return new Refusal(t('yandexmetrika.refused', ['reason' => \is_string($answer['message'] ?? null) ? $answer['message'] : (string) $response->getStatusCode()]));
        } catch (ExceptionInterface $failure) {
            return new Refusal(t('yandexmetrika.unavailable', ['reason' => $failure->getMessage()]));
        }
    }

    private function access(): ?Credentials
    {
        $counter = $this->switches->first(YandexMetrikaCounter::class);

        if (null === $counter) {
            return null;
        }

        $credentials = $counter[1];

        return '' === trim($credentials->get(YandexMetrikaCounter::COUNTER)) || '' === trim($credentials->get(YandexMetrikaCounter::TOKEN))
            ? null
            : $credentials;
    }
}
