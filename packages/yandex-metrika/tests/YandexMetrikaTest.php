<?php

declare(strict_types=1);

namespace AdzerCMS\YandexMetrika\Tests;

use AdzerCMS\Contracts\Analytics\Report;
use AdzerCMS\Contracts\Analytics\Step;
use AdzerCMS\Contracts\Analytics\Visits;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Ui\ContentSource;
use AdzerCMS\Core\Analytics\ConsentHead;
use AdzerCMS\Core\Analytics\Counters;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\HttpAnswers;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\YandexMetrika\OfflineConversion;
use AdzerCMS\YandexMetrika\YandexMetrikaApi;
use AdzerCMS\YandexMetrika\YandexMetrikaConversions;
use AdzerCMS\YandexMetrika\YandexMetrikaCounter;
use AdzerCMS\YandexMetrika\YandexMetrikaTraffic;
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
        $conversions = new YandexMetrikaConversions(self::api());
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

    #[Group('ACMS-CORE-1027')]
    public function testTrafficComesFromTheCounterReportsByItsToken(): void
    {
        $traffic = new YandexMetrikaTraffic(self::api());
        $this->configure([self::SETTINGS.Switches::ENABLED => '1', self::SETTINGS.YandexMetrikaCounter::COUNTER => (string) self::NUMBER]);

        self::assertFalse($traffic->available(), 'the counter without its token gives no reports');

        $this->configure([self::SETTINGS.YandexMetrikaCounter::TOKEN => 'metrika-token']);
        $asked = [];
        self::service(HttpAnswers::class)->respond(static function (string $method, string $url, array $options) use (&$asked): JsonMockResponse {
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
            $asked[(string) parse_url($url, \PHP_URL_PATH)] = ['query' => $query, 'authorization' => (string) ($options['normalized_headers']['authorization'][0] ?? '')];

            return match (true) {
                str_ends_with($url, '/counter/'.self::NUMBER) => new JsonMockResponse(['counter' => ['id' => self::NUMBER, 'create_time' => '2024-03-05T10:00:00+03:00']]),
                str_contains($url, '/bytime') => new JsonMockResponse(['time_intervals' => [['2026-10-01', '2026-10-04'], ['2026-10-05', '2026-10-11']], 'totals' => [[5.0, 8.0], [6.0, 9.0], [20.0, 21.0]]]),
                default => new JsonMockResponse(['totals' => [12.0, 15.0, 41.0]]),
            };
        });
        $from = new \DateTimeImmutable('2026-10-01');
        $last = new \DateTimeImmutable('2026-10-11');

        self::assertTrue($traffic->available());
        self::assertEquals(new Report(new Visits($from, $last, 12, 15, 41), [
            new Visits($from, new \DateTimeImmutable('2026-10-04'), 5, 6, 20),
            new Visits(new \DateTimeImmutable('2026-10-05'), $last, 8, 9, 21),
        ], new \DateTimeImmutable('2024-03-05')), $traffic->report($from, $last, Step::Week));

        $period = ['ids' => (string) self::NUMBER, 'metrics' => YandexMetrikaTraffic::METRICS, 'date1' => '2026-10-01', 'date2' => '2026-10-11', 'accuracy' => 'full'];
        self::assertSame(['/management/v1/counter/'.self::NUMBER, '/stat/v1/data', '/stat/v1/data/bytime'], array_keys($asked));
        self::assertEquals($period, $asked['/stat/v1/data']['query']);
        self::assertEquals($period + ['group' => 'week'], $asked['/stat/v1/data/bytime']['query']);
        self::assertSame(['Authorization: OAuth metrika-token'], array_values(array_unique(array_column($asked, 'authorization'))));

        self::service(HttpAnswers::class)->respond(static fn (): JsonMockResponse => new JsonMockResponse(['message' => 'Access denied'], ['http_code' => 403]));
        $refusal = $traffic->report($from, $last, Step::Day);
        self::assertInstanceOf(Refusal::class, $refusal);
        self::assertSame(self::phrase('yandexmetrika.refused', ['reason' => 'Access denied']), self::phrase($refusal->reason));
    }

    private static function api(): YandexMetrikaApi
    {
        return new YandexMetrikaApi(self::service(HttpClientInterface::class), self::service(Switches::class));
    }
}
