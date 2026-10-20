<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Contracts\Setting\Rule;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Ui\Split;

use function Symfony\Component\Translation\t;

final readonly class ExtensionsSettings implements Owner, Rule
{
    public const REPOSITORIES = 'extensions.repositories';

    private const PACKAGIST = 'https://repo.packagist.org';

    public function __construct(private Settings $settings)
    {
    }

    public function settingFields(): array
    {
        return [new Field(self::REPOSITORIES, t('extensions.setting.repositories'))];
    }

    public function check(Field $field, string $value): ?Refusal
    {
        if (self::REPOSITORIES !== $field->name) {
            return null;
        }

        foreach (Split::items($value) as $repository) {
            if (!self::web($repository)) {
                return new Refusal(t('extensions.refusal.repository', ['repository' => $repository]));
            }
        }

        return null;
    }

    /** @return list<string> */
    public function repositories(): array
    {
        $repositories = Split::items($this->settings->get(self::REPOSITORIES));

        return [] === $repositories ? [self::PACKAGIST] : $repositories;
    }

    public static function web(string $url): bool
    {
        return false !== filter_var($url, \FILTER_VALIDATE_URL)
            && \in_array(strtolower((string) parse_url($url, \PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
