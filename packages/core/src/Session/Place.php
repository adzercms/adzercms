<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Session;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use MaxMind\Db\Reader\InvalidDatabaseException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Translation\LocaleSwitcher;

final class Place
{
    private ?Reader $reader = null;

    public function __construct(
        #[Autowire(env: 'GEOIP_CITY_DB')] private readonly string $database,
        private readonly LocaleSwitcher $locales,
    ) {
    }

    public function of(?string $address): ?string
    {
        if (null === $address || false === filter_var($address, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE) || !is_readable($this->database)) {
            return null;
        }

        try {
            $found = ($this->reader ??= new Reader($this->database))->city($address);
        } catch (AddressNotFoundException|InvalidDatabaseException) {
            return null;
        }

        $locale = str_replace('_', '-', $this->locales->getLocale());

        return self::named($found->city->names, $locale) ?? $found->city->name ?? self::named($found->country->names, $locale) ?? $found->country->name;
    }

    /** @param array<string, string> $names */
    private static function named(array $names, string $locale): ?string
    {
        return $names[$locale] ?? $names[explode('-', $locale)[0]] ?? null;
    }
}
