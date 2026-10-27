<?php

declare(strict_types=1);

namespace AdzerCMS\YandexMetrika\Tests;

use AdzerCMS\Contracts\Ui\ContentSource;
use AdzerCMS\Core\Analytics\ConsentHead;
use AdzerCMS\Core\Analytics\Counters;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\HttpAnswers;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\YandexMetrika\OfflineConversion;
use AdzerCMS\YandexMetrika\YandexMetrikaConversions;
use AdzerCMS\YandexMetrika\YandexMetrikaCounter;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\Clock\now;

final class YandexMetrikaTest extends SchemaTestCase
{
    use Configuration;

    private const SETTINGS = 'analytics.yandexmetrika.';
    private const NUMBER = 87654321;

    #[Group('ACMS-CORE-0953')]
    #[Group('ACMS-US-0037')]
    public function testCounterStartsByItsNumberAndOpensMetrikaAddresses(): void
    {
        $this->configure([self::SETTINGS.Switches::ENABLED => '1', self::SETTINGS.YandexMetrikaCounter::COUNTER => (string) self::NUMBER]);

        $counters = self::service(ConsentHead::class)->fragment(null)['context']['counters'] ?? [];
        self::assertSame(
            [['entry' => 'adzercms/yandex-metrika', 'settings' => [
                'tag' => 'https://mc.yandex.ru/metrika/tag.js?id='.self::NUMBER,
                'counter' => self::NUMBER,
                'options' => ['ssr' => true, 'webvisor' => true, 'clickmap' => true, 'ecommerce' => 'dataLayer', 'accurateTrackBounce' => true, 'trackLinks' => true],
            ]]],
            array_map(static fn (array $counter): array => array_diff_key($counter, ['name' => true]), $counters),
        );

        $sources = self::service(Counters::class)->sources(new Request());
        foreach ([
            ContentSource::SCRIPT => ['https://mc.yandex.ru', 'https://yastatic.net'],
            ContentSource::CONNECT => ['https://mc.yandex.ru', 'wss://mc.webvisor.org'],
            ContentSource::IMG => ['https://mc.yandex.ru'],
            ContentSource::FRAME => ['blob:', 'https://mc.yandex.ru'],
            ContentSource::WORKER => ['blob:', 'https://mc.yandex.ru'],
            ContentSource::FRAME_ANCESTORS => ['https://metrika.yandex.ru'],
        ] as $directive => $origins) {
            self::assertSame([], array_values(array_diff($origins, $sources[$directive] ?? [])), $directive);
        }
    }

    #[Group('ACMS-CORE-0953')]
    public function testOfflineConversionsGoToTheCounterByItsToken(): void
    {
        $conversions = new YandexMetrikaConversions(self::service(HttpClientInterface::class), self::service(Switches::class));
        $this->configure([self::SETTINGS.Switches::ENABLED => '1', self::SETTINGS.YandexMetrikaCounter::COUNTER => (string) self::NUMBER]);

        self::assertFalse($conversions->available(), 'the counter without its token takes no conversions');

        $this->configure([self::SETTINGS.YandexMetrikaCounter::TOKEN => 'metrika-token']);
        $sent = [];
        self::service(HttpAnswers::class)->respond(static function (string $method, string $url, array $options) use (&$sent): JsonMockResponse {
            $body = $options['body'] ?? '';
            $content = '';

            while ($body instanceof \Closure && '' !== $chunk = (string) $body(16384)) {
                $content .= $chunk;
            }

            $sent[] = ['url' => $url, 'authorization' => (string) ($options['normalized_headers']['authorization'][0] ?? ''), 'body' => $content];

            return new JsonMockResponse(['uploading' => ['id' => 1, 'status' => 'UPLOADED']]);
        });
        $moment = now()->modify('-1 hour');

        self::assertTrue($conversions->available());
        self::assertNull($conversions->upload([new OfflineConversion('4504935055255666687', 'registration', $moment)]));
        self::assertSame(\sprintf(YandexMetrikaConversions::UPLOAD, self::NUMBER), $sent[0]['url']);
        self::assertSame('Authorization: OAuth metrika-token', $sent[0]['authorization']);
        self::assertStringContainsString("Yclid,Target,DateTime\n4504935055255666687,registration,".$moment->getTimestamp(), $sent[0]['body']);
    }
}
