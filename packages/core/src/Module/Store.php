<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Module\ExtensionType;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Page;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Composer\Package;
use AdzerCMS\Core\Setting\Settings;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\Translation\t;

final readonly class Store implements Owner
{
    public const NAME = 'modules.store';
    public const DEFAULT = 'https://adzercms.com/extensions/store.json';

    private const TIMEOUT = 10.0;
    private const IMAGES = 8;

    public function __construct(private HttpClientInterface $httpClient, private Settings $settings)
    {
    }

    public function settingFields(): array
    {
        return [new Field(self::NAME, t('core.setting.store'), Type::Url, page: Page::Extensions)];
    }

    public function url(): string
    {
        $url = $this->settings->get(self::NAME);

        return '' === $url ? self::DEFAULT : $url;
    }

    public function read(string $url, ?StoreDocument $held): ?StoreDocument
    {
        $held = $url === $held?->url ? $held : null;

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => self::TIMEOUT,
                'headers' => null === $held?->etag ? [] : ['If-None-Match' => $held->etag],
            ]);
            if (null !== $held && Response::HTTP_NOT_MODIFIED === $response->getStatusCode()) {
                return $held;
            }
            $document = $response->toArray();
            $etag = $response->getHeaders()['etag'][0] ?? null;
        } catch (ExceptionInterface) {
            return null;
        }

        $entries = [];
        foreach (\is_array($document['extensions'] ?? null) ? $document['extensions'] : [] as $entry) {
            $name = \is_array($entry) ? Package::text($entry['name'] ?? null) : null;
            if (null !== $name && !isset($entries[$name])) {
                $entries[$name] = new StoreEntry(
                    \is_string($entry['type'] ?? null) ? ExtensionType::tryFrom($entry['type']) : null,
                    Package::link($entry['demo'] ?? null),
                    self::images($entry['images'] ?? null),
                );
            }
        }

        return new StoreDocument($url, $etag, $entries);
    }

    /** @return array<string, null> */
    private static function images(mixed $images): array
    {
        $links = array_filter(array_map(Package::link(...), \is_array($images) ? array_values($images) : []));

        return array_fill_keys(\array_slice(array_values(array_unique($links)), 0, self::IMAGES), null);
    }
}
