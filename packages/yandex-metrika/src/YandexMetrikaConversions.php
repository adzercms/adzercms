<?php

declare(strict_types=1);

namespace AdzerCMS\YandexMetrika;

use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Module\Switches;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function Symfony\Component\Translation\t;

final readonly class YandexMetrikaConversions
{
    public const UPLOAD = 'https://api-metrika.yandex.net/management/v1/counter/%s/offline_conversions/upload';

    private const COLUMNS = ['Yclid', 'Target', 'DateTime'];

    public function __construct(private HttpClientInterface $httpClient, private Switches $switches)
    {
    }

    public function available(): bool
    {
        return null !== $this->access();
    }

    /** @param non-empty-list<OfflineConversion> $conversions */
    public function upload(array $conversions): ?Refusal
    {
        $access = $this->access();

        if (null === $access) {
            return new Refusal(t('yandexmetrika.conversions_closed'));
        }

        $form = new FormDataPart(['file' => new DataPart(self::table($conversions), 'conversions.csv', 'text/csv')]);

        try {
            $response = $this->httpClient->request('POST', \sprintf(self::UPLOAD, rawurlencode(trim($access->get(YandexMetrikaCounter::COUNTER)))), [
                'headers' => ['Authorization' => 'OAuth '.trim($access->get(YandexMetrikaCounter::TOKEN))] + $form->getPreparedHeaders()->toArray(),
                'body' => $form->bodyToIterable(),
            ]);

            return 200 === $response->getStatusCode()
                ? null
                : new Refusal(t('yandexmetrika.conversions_refused', ['reason' => self::reason($response)]));
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

    private static function reason(ResponseInterface $response): string
    {
        try {
            $answer = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            $answer = [];
        }

        return \is_string($answer['message'] ?? null) ? $answer['message'] : (string) $response->getStatusCode();
    }

    /** @param non-empty-list<OfflineConversion> $conversions */
    private static function table(array $conversions): string
    {
        $table = fopen('php://memory', 'r+');

        if (false === $table) {
            throw new \RuntimeException('the conversions buffer did not open');
        }

        fputcsv($table, self::COLUMNS, ',', '"', '');

        foreach ($conversions as $conversion) {
            fputcsv($table, [$conversion->click, $conversion->target, $conversion->reachedAt->getTimestamp()], ',', '"', '');
        }

        rewind($table);
        $csv = (string) stream_get_contents($table);
        fclose($table);

        return $csv;
    }
}
