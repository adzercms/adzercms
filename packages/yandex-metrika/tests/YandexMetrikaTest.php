<?php

declare(strict_types=1);

namespace AdzerCMS\YandexMetrika\Tests;

use AdzerCMS\Contracts\Ui\ContentSource;
use AdzerCMS\Core\Analytics\ConsentHead;
use AdzerCMS\Core\Analytics\Counters;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\YandexMetrika\YandexMetrikaCounter;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

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
            ], 'cookies' => '^_ym']],
            array_map(static fn (array $counter): array => array_diff_key($counter, ['name' => true]), $counters),
        );

        $sources = self::service(Counters::class)->sources(new Request());
        foreach ([ContentSource::SCRIPT, ContentSource::CONNECT, ContentSource::IMG, ContentSource::FRAME, ContentSource::WORKER] as $directive) {
            self::assertContains('https://mc.yandex.ru', $sources[$directive] ?? [], $directive);
        }
        self::assertContains('https://yastatic.net', $sources[ContentSource::SCRIPT] ?? []);
        self::assertContains('wss://mc.webvisor.org', $sources[ContentSource::CONNECT] ?? []);
        self::assertContains('blob:', $sources[ContentSource::FRAME] ?? []);
        self::assertContains('blob:', $sources[ContentSource::WORKER] ?? []);
        self::assertContains('https://metrika.yandex.ru', $sources[ContentSource::FRAME_ANCESTORS] ?? []);
    }
}
