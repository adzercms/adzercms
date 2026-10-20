<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

use Composer\MetadataMinifier\MetadataMinifier;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;

final readonly class Package
{
    private const MINIFIED = 'composer/2.0';
    private const STABLE = 'stable';

    /** @param list<string> $categories */
    public function __construct(
        public string $description,
        public string $version,
        public array $categories,
        public ?string $source,
        public ?string $homepage,
    ) {
    }

    /** @param array<mixed> $answer */
    public static function of(string $name, array $answer): ?self
    {
        $versions = \is_array($answer['packages'] ?? null) && \is_array($answer['packages'][$name] ?? null) && array_is_list($answer['packages'][$name])
            ? $answer['packages'][$name]
            : [];
        if (self::MINIFIED === ($answer['minified'] ?? null)) {
            $versions = MetadataMinifier::expand($versions);
        }

        $stable = array_values(array_filter($versions, static fn (mixed $version): bool => \is_array($version)
            && \is_string($version['version'] ?? null)
            && self::STABLE === VersionParser::parseStability($version['version'])));
        if ([] === $stable) {
            return null;
        }

        $numbers = array_map(static fn (array $version): string => $version['version'], $stable);
        $latest = $stable[(int) array_search(Semver::rsort($numbers)[0], $numbers, true)];
        $extra = \is_array($latest['extra']['adzercms'] ?? null) ? $latest['extra']['adzercms'] : [];

        return new self(
            self::text($latest['description'] ?? null) ?? '',
            $latest['version'],
            array_values(array_unique(array_filter(\is_array($extra['categories'] ?? null) ? $extra['categories'] : [], \is_string(...)))),
            self::text($latest['source']['url'] ?? null),
            self::text($latest['homepage'] ?? null),
        );
    }

    private static function text(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
