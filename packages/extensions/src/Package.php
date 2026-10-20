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
        $stable = array_values(array_filter(self::versions($name, $answer), static fn (array $version): bool => self::STABLE === VersionParser::parseStability($version['version'])));
        if ([] === $stable) {
            return null;
        }

        $numbers = array_map(static fn (array $version): string => $version['version'], $stable);
        $latest = $stable[(int) array_search(Semver::rsort($numbers)[0], $numbers, true)];

        return self::from($latest, $latest['version']);
    }

    /** @param array<mixed> $answer */
    public static function branch(string $name, array $answer): ?self
    {
        foreach (self::versions($name, $answer) as $version) {
            if (true === ($version['default-branch'] ?? null)) {
                $alias = $version['extra']['branch-alias'][$version['version']] ?? null;

                return self::from($version, \is_string($alias) ? $alias : $version['version']);
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $answer
     *
     * @return list<array{version: string, ...<mixed>}>
     */
    private static function versions(string $name, array $answer): array
    {
        $versions = \is_array($answer['packages'] ?? null) && \is_array($answer['packages'][$name] ?? null) && array_is_list($answer['packages'][$name])
            ? $answer['packages'][$name]
            : [];
        if (self::MINIFIED === ($answer['minified'] ?? null)) {
            $versions = MetadataMinifier::expand($versions);
        }

        return array_values(array_filter($versions, static fn (mixed $version): bool => \is_array($version) && \is_string($version['version'] ?? null)));
    }

    /** @param array<mixed> $version */
    private static function from(array $version, string $label): self
    {
        $extra = \is_array($version['extra']['adzercms'] ?? null) ? $version['extra']['adzercms'] : [];

        return new self(
            self::text($version['description'] ?? null) ?? '',
            $label,
            array_values(array_unique(array_filter(\is_array($extra['categories'] ?? null) ? $extra['categories'] : [], \is_string(...)))),
            self::link($version['source']['url'] ?? null),
            self::link($version['homepage'] ?? null),
        );
    }

    private static function text(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    private static function link(mixed $value): ?string
    {
        $link = self::text($value);

        return null !== $link && ExtensionsSettings::web($link) ? $link : null;
    }
}
