<?php

declare(strict_types=1);

namespace AdzerCMS\YandexMetrika;

use AdzerCMS\Contracts\Refusal;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

final readonly class YandexMetrikaConversions
{
    public const UPLOAD = 'https://api-metrika.yandex.net/management/v1/counter/%s/offline_conversions/upload';

    private const COLUMNS = ['Yclid', 'Target', 'DateTime'];

    public function __construct(private YandexMetrikaApi $api)
    {
    }

    public function available(): bool
    {
        return $this->api->available();
    }

    /** @param non-empty-list<OfflineConversion> $conversions */
    public function upload(array $conversions): ?Refusal
    {
        $form = new FormDataPart(['file' => new DataPart(self::table($conversions), 'conversions.csv', 'text/csv')]);
        $answer = YandexMetrikaApi::answer($this->api->request('POST', self::UPLOAD, [
            'headers' => $form->getPreparedHeaders()->toArray(),
            'body' => $form->bodyToIterable(),
        ]));

        return $answer instanceof Refusal ? $answer : null;
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
