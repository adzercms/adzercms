<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Composer;

use AdzerCMS\Contracts\Module\Category;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Ui\WebLink;
use Composer\MetadataMinifier\MetadataMinifier;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;

final readonly class Package
{
    private const MINIFIED = 'composer/2.0';
    private const STABLE = 'stable';
    private const LIBRARY = 'library';

    /** @param list<Category> $categories */
    public function __construct(
        public Description $description,
        public string $version,
        public array $categories,
        public ?string $source,
        public ?string $homepage,
        public string $type,
        public ?string $kind,
        public ?string $reference,
        public ?string $author = null,
    ) {
    }

    /** @param array<mixed> $manifest */
    public static function manifest(array $manifest, string $version): self
    {
        return self::from($manifest, $version, null);
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

        return self::from($latest, $latest['version'], self::reference($latest));
    }

    /** @param array<mixed> $answer */
    public static function branch(string $name, array $answer): ?self
    {
        foreach (self::versions($name, $answer) as $version) {
            if (true === ($version['default-branch'] ?? null)) {
                $alias = $version['extra']['branch-alias'][$version['version']] ?? null;

                return self::from($version, \is_string($alias) ? $alias : $version['version'], self::reference($version));
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $answer
     *
     * @return list<string>
     */
    public static function references(string $name, array $answer): array
    {
        return array_values(array_filter(array_map(self::reference(...), self::versions($name, $answer))));
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
    private static function from(array $version, string $label, ?string $reference): self
    {
        $extra = \is_array($version['extra']['adzercms'] ?? null) ? $version['extra']['adzercms'] : [];
        $kinds = preg_filter(Registry::IMPLEMENTATION, '$1', array_map(strval(...), array_keys(\is_array($version['provide'] ?? null) ? $version['provide'] : [])));

        return new self(
            Description::of($version),
            $label,
            Category::of(\is_array($extra['categories'] ?? null) ? $extra['categories'] : []),
            self::link($version['source']['url'] ?? null),
            self::link($version['homepage'] ?? null),
            self::text($version['type'] ?? null) ?? self::LIBRARY,
            [] === $kinds ? null : reset($kinds),
            $reference,
            self::text($extra['author'] ?? null),
        );
    }

    /** @param array<mixed> $version */
    private static function reference(array $version): ?string
    {
        return self::text($version['source']['reference'] ?? null) ?? self::text($version['dist']['reference'] ?? null);
    }

    public static function text(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    public static function link(mixed $value): ?string
    {
        $link = self::text($value);

        return null !== $link && WebLink::valid($link) ? $link : null;
    }
}
