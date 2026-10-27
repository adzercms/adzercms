<?php

declare(strict_types=1);

namespace AdzerCMS\GoogleAnalytics;

use AdzerCMS\Contracts\Analytics\Counter;
use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Guided;
use AdzerCMS\Contracts\Ui\ContentSource;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final readonly class GoogleAnalyticsCounter implements Counter, Guided
{
    public const MEASUREMENT = 'measurement';
    public const TAG = 'https://www.googletagmanager.com/gtag/js?id=%s';
    public const CONSENT = ['analytics_storage' => 'granted', 'ad_storage' => 'denied', 'ad_user_data' => 'denied', 'ad_personalization' => 'denied'];

    private const ENTRY = 'adzercms/google-analytics';
    private const TAG_MANAGER = 'https://www.googletagmanager.com';
    private const COLLECTION = 'https://*.google-analytics.com';
    private const GOOGLE = 'https://*.google.com';

    public function name(): TranslatableInterface
    {
        return t('googleanalytics.name');
    }

    public function credentials(): array
    {
        return [new Field(self::MEASUREMENT, t('googleanalytics.measurement'), required: true)];
    }

    public function guide(): array
    {
        return [t('googleanalytics.guide.streams'), t('googleanalytics.guide.stream'), t('googleanalytics.guide.measurement')];
    }

    public function entry(): string
    {
        return self::ENTRY;
    }

    public function settings(Credentials $credentials): array
    {
        $measurement = trim($credentials->get(self::MEASUREMENT));

        return ['tag' => \sprintf(self::TAG, rawurlencode($measurement)), 'measurement' => $measurement, 'consent' => self::CONSENT];
    }

    public function origins(): array
    {
        return [
            ContentSource::SCRIPT => [self::TAG_MANAGER],
            ContentSource::IMG => [self::TAG_MANAGER, self::COLLECTION],
            ContentSource::CONNECT => [self::TAG_MANAGER, self::COLLECTION, self::GOOGLE],
        ];
    }
}
