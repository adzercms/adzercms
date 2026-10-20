<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Rule;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Ui\WebLink;
use Opotopo\SellerApi\ClientConfig;

use function Symfony\Component\Translation\t;

final readonly class OpotopoSettings implements Owner, Rule
{
    public const URL = 'opotopo.url';
    public const KEY = 'opotopo.key';
    public const ORGANIZATION = 'opotopo.organization';
    public const PUBLISH = 'opotopo.publish';

    private const RETRIES = 3;
    private const RETRY_PAUSE_MS = 2000;

    public function __construct(private Settings $settings)
    {
    }

    public function settingFields(): array
    {
        return [
            new Field(self::URL, t('opotopo.setting.url')),
            new Field(self::KEY, t('opotopo.setting.key'), Type::Secret, group: self::KEY),
            new Field(self::ORGANIZATION, t('opotopo.setting.organization')),
            new Field(self::PUBLISH, t('opotopo.setting.publish'), Type::Toggle),
        ];
    }

    public function check(Field $field, string $value): ?Refusal
    {
        return self::URL === $field->name && !WebLink::valid($value) ? new Refusal(t('opotopo.refusal.url')) : null;
    }

    public function config(): ?ClientConfig
    {
        $url = $this->settings->get(self::URL);
        $key = $this->settings->get(self::KEY);
        if ('' === $url || '' === $key) {
            return null;
        }

        $organization = $this->settings->get(self::ORGANIZATION);

        return new ClientConfig($url, $key, '' === $organization ? null : $organization, $this->settings->on(self::PUBLISH), self::RETRIES, self::RETRY_PAUSE_MS);
    }
}
