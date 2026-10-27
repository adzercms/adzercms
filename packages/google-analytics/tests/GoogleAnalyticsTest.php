<?php

declare(strict_types=1);

namespace AdzerCMS\GoogleAnalytics\Tests;

use AdzerCMS\Contracts\Ui\ContentSource;
use AdzerCMS\Core\Analytics\ConsentHead;
use AdzerCMS\Core\Analytics\Counters;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\GoogleAnalytics\GoogleAnalyticsCounter;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

final class GoogleAnalyticsTest extends SchemaTestCase
{
    use Configuration;

    private const SETTINGS = 'analytics.googleanalytics.';
    private const MEASUREMENT = 'G-7Q2X9K4M1B';

    #[Group('ACMS-CORE-0974')]
    #[Group('ACMS-US-0037')]
    public function testCounterStartsByItsMeasurementWithAnalyticsConsentOnly(): void
    {
        $this->configure([self::SETTINGS.Switches::ENABLED => '1', self::SETTINGS.GoogleAnalyticsCounter::MEASUREMENT => ' '.self::MEASUREMENT.' ']);

        $counters = self::service(ConsentHead::class)->fragment(null)['context']['counters'] ?? [];
        self::assertSame(
            [['entry' => 'adzercms/google-analytics', 'settings' => [
                'tag' => 'https://www.googletagmanager.com/gtag/js?id='.self::MEASUREMENT,
                'measurement' => self::MEASUREMENT,
                'consent' => ['analytics_storage' => 'granted', 'ad_storage' => 'denied', 'ad_user_data' => 'denied', 'ad_personalization' => 'denied'],
            ]]],
            array_map(static fn (array $counter): array => array_diff_key($counter, ['name' => true]), $counters),
        );

        $sources = self::service(Counters::class)->sources(new Request());
        foreach ([
            ContentSource::SCRIPT => ['https://www.googletagmanager.com'],
            ContentSource::IMG => ['https://www.googletagmanager.com', 'https://*.google-analytics.com'],
            ContentSource::CONNECT => ['https://www.googletagmanager.com', 'https://*.google-analytics.com', 'https://*.google.com'],
        ] as $directive => $origins) {
            self::assertSame([], array_values(array_diff($origins, $sources[$directive] ?? [])), $directive);
        }
    }
}
