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
            ]]],
            array_map(static fn (array $counter): array => array_diff_key($counter, ['name' => true, 'cookies' => true]), $counters),
        );
        foreach (['_ym_uid' => 1, '_ym_d' => 1, 'mdd' => 1, '_ytm_preview' => 1, 'cc_cookie' => 0, 'mdd_x' => 0] as $cookie => $metrika) {
            self::assertSame($metrika, preg_match('/'.($counters[0]['cookies'] ?? '').'/', $cookie), $cookie);
        }

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
}
