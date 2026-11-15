<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Guided;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Setting\Settings;
use Opotopo\SellerApi\ClientConfig;

use function Symfony\Component\Translation\t;

final readonly class OpotopoSettings implements Owner, Guided
{
    public const API = 'https://api.opotopo.com';
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
            new Field(self::KEY, t('opotopo.setting.key'), Type::Secret, group: self::KEY),
            new Field(self::ORGANIZATION, t('opotopo.setting.organization')),
            new Field(self::PUBLISH, t('opotopo.setting.publish'), Type::Toggle),
        ];
    }

    public function guide(): array
    {
        return [t('opotopo.guide.key')];
    }

    public function config(): ?ClientConfig
    {
        $key = $this->settings->get(self::KEY);
        if ('' === $key) {
            return null;
        }

        $organization = $this->settings->get(self::ORGANIZATION);

        return new ClientConfig(self::API, $key, '' === $organization ? null : $organization, $this->settings->on(self::PUBLISH), self::RETRIES, self::RETRY_PAUSE_MS);
    }
}
