<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Module\ExtensionType;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Rule;
use AdzerCMS\Contracts\Setting\Tab;
use AdzerCMS\Core\Composer\Package;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Ui\WebLink;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\Translation\t;

final readonly class Store implements Owner, Rule
{
    public const NAME = 'modules.store';
    public const DEFAULT = 'https://adzercms.com/extensions/store.json';

    private const TIMEOUT = 10.0;

    public function __construct(private HttpClientInterface $httpClient, private Settings $settings)
    {
    }

    public function settingFields(): array
    {
        return [new Field(self::NAME, t('core.setting.store'), section: t('core.setting.section.extensions'), tab: Tab::General)];
    }

    public function check(Field $field, string $value): ?Refusal
    {
        return self::NAME === $field->name && !WebLink::valid($value) ? new Refusal(t('core.setting.url', ['field' => $field->label])) : null;
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
                );
            }
        }

        return new StoreDocument($url, $etag, $entries);
    }
}
