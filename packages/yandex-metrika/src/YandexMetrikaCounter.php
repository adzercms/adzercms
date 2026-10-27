<?php

declare(strict_types=1);

namespace AdzerCMS\YandexMetrika;

use AdzerCMS\Contracts\Analytics\Counter;
use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Guided;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Contracts\Ui\ContentSource;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

final readonly class YandexMetrikaCounter implements Counter, Guided
{
    public const COUNTER = 'counter';
    public const TOKEN = 'token';
    public const TAG = 'https://mc.yandex.ru/metrika/tag.js?id=%d';
    public const OPTIONS = ['ssr' => true, 'webvisor' => true, 'clickmap' => true, 'ecommerce' => 'dataLayer', 'accurateTrackBounce' => true, 'trackLinks' => true];

    private const ENTRY = 'adzercms/yandex-metrika';
    private const ACCESS = 'access';
    private const BLOB = 'blob:';
    private const HOSTS = [
        'mc.yandex.ru', 'mc.yandex.az', 'mc.yandex.by', 'mc.yandex.co.il', 'mc.yandex.com', 'mc.yandex.com.am', 'mc.yandex.com.ge',
        'mc.yandex.com.tr', 'mc.yandex.ee', 'mc.yandex.fr', 'mc.yandex.kg', 'mc.yandex.kz', 'mc.yandex.lt', 'mc.yandex.lv',
        'mc.yandex.md', 'mc.yandex.tj', 'mc.yandex.tm', 'mc.yandex.uz', 'mc.webvisor.com', 'mc.webvisor.org',
    ];
    private const STATIC = 'yastatic.net';
    private const INTERFACE = [
        'metrika.yandex.ru', 'analytics.yandex.by', 'analytics.yandex.com', 'analytics.yandex.com.tr', 'analytics.yandex.kz',
        'analytics.yandex.ru', 'metr.yandex.by', 'metr.yandex.com', 'metr.yandex.com.tr', 'metr.yandex.kz', 'metr.yandex.ru',
        'metrica.ya.ru', 'metrica.yandex', 'metrica.yandex.by', 'metrica.yandex.com', 'metrica.yandex.com.tr', 'metrica.yandex.kz',
        'metrica.yandex.ru', 'metrika.ya.ru', 'metrika.yandex', 'metrika.yandex.by', 'metrika.yandex.com', 'metrika.yandex.com.tr',
        'metrika.yandex.kz', 'metrika.yandex.uz',
    ];

    public function name(): TranslatableInterface
    {
        return t('yandexmetrika.name');
    }

    public function credentials(): array
    {
        return [
            new Field(self::COUNTER, t('yandexmetrika.counter'), required: true),
            new Field(self::TOKEN, t('yandexmetrika.token'), Type::Secret, group: self::ACCESS),
        ];
    }

    public function guide(): array
    {
        return [t('yandexmetrika.guide.counter'), t('yandexmetrika.guide.app'), t('yandexmetrika.guide.allow'), t('yandexmetrika.guide.token')];
    }

    public function entry(): string
    {
        return self::ENTRY;
    }

    public function settings(Credentials $credentials): array
    {
        $counter = (int) $credentials->get(self::COUNTER);

        return ['tag' => \sprintf(self::TAG, $counter), 'counter' => $counter, 'options' => self::OPTIONS];
    }

    public function origins(): array
    {
        $https = self::scheme('https', [...self::HOSTS, self::STATIC]);

        return [
            ContentSource::SCRIPT => $https,
            ContentSource::CONNECT => [...$https, ...self::scheme('wss', self::HOSTS)],
            ContentSource::IMG => $https,
            ContentSource::FRAME => [self::BLOB, ...$https],
            ContentSource::WORKER => [self::BLOB, ...$https],
            ContentSource::FRAME_ANCESTORS => self::scheme('https', self::INTERFACE),
        ];
    }

    /**
     * @param list<string> $hosts
     *
     * @return list<string>
     */
    private static function scheme(string $scheme, array $hosts): array
    {
        return array_map(static fn (string $host): string => $scheme.'://'.$host, $hosts);
    }
}
