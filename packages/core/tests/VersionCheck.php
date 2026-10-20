<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use AdzerCMS\Core\Composer\Package;
use AdzerCMS\Core\Composer\Repositories;
use AdzerCMS\Core\Composer\Source;
use AdzerCMS\Core\Module\Registry;

final readonly class VersionCheck
{
    public const MATRIX = 256;

    public function __construct(private Repositories $repositories)
    {
    }

    /**
     * @param array<mixed> $registry
     *
     * @return list<array{package: string, version: string, require: string, reference: string}>
     */
    public function pending(array $registry): array
    {
        $records = self::records($registry);
        $pending = [];
        foreach ($this->published() as $name => $published) {
            $offered = $published['offered'];
            if (null !== $offered && !isset($records[$name][$offered['reference']])) {
                $pending[] = ['package' => $name, ...$offered];
            }
        }

        return \array_slice($pending, 0, self::MATRIX);
    }

    /**
     * @param array<mixed> $registry
     * @param array<mixed> $pending
     * @param array<mixed> $outcomes
     *
     * @return array<string, array<string, array{version: string, outcome: string}>>
     */
    public function record(array $registry, array $pending, array $outcomes): array
    {
        $records = self::records($registry);
        $checked = [];
        foreach ($outcomes as $outcome) {
            if (\is_array($outcome) && \is_string($outcome['package'] ?? null) && \is_string($outcome['outcome'] ?? null)) {
                $checked[$outcome['package']] = $outcome['outcome'];
            }
        }
        foreach ($pending as $version) {
            if (\is_array($version) && \is_string($version['package'] ?? null) && \is_string($version['version'] ?? null) && \is_string($version['reference'] ?? null) && isset($checked[$version['package']])) {
                $records[$version['package']][$version['reference']] = ['version' => $version['version'], 'outcome' => $checked[$version['package']]];
            }
        }

        $published = $this->published();
        $kept = [];
        foreach ($records as $name => $versions) {
            $versions = array_intersect_key($versions, array_flip($published[$name]['references'] ?? []));
            if ([] !== $versions) {
                ksort($versions, \SORT_STRING);
                $kept[$name] = $versions;
            }
        }
        ksort($kept, \SORT_STRING);

        return $kept;
    }

    /** @return array<string, array{offered: ?array{version: string, require: string, reference: string}, references: list<string>}> */
    private function published(): array
    {
        $packagist = new Source(Repositories::PACKAGIST);
        $names = $this->repositories->names($packagist, Registry::TYPE);
        $answers = null === $names ? null : $this->repositories->answers($packagist, $names);
        if (null === $answers) {
            throw new \RuntimeException(\sprintf('%s is unavailable', Repositories::PACKAGIST));
        }

        $published = [];
        foreach ($answers as $name => [$tagged, $branches]) {
            $stable = Package::of($name, $tagged);
            $package = $stable ?? Package::branch($name, $branches);
            $published[$name] = [
                'offered' => null !== $package && null !== $package->reference && Registry::TYPE === $package->type
                    ? ['version' => $package->version, 'require' => null === $stable ? $package->version.'#'.$package->reference : $package->version, 'reference' => $package->reference]
                    : null,
                'references' => [...Package::references($name, $tagged), ...Package::references($name, $branches)],
            ];
        }
        ksort($published);

        return $published;
    }

    /**
     * @param array<mixed> $registry
     *
     * @return array<string, array<string, array{version: string, outcome: string}>>
     */
    private static function records(array $registry): array
    {
        $records = [];
        foreach ($registry as $name => $versions) {
            foreach (\is_array($versions) ? $versions : [] as $reference => $record) {
                if (\is_array($record) && \is_string($record['version'] ?? null) && \is_string($record['outcome'] ?? null)) {
                    $records[(string) $name][(string) $reference] = ['version' => $record['version'], 'outcome' => $record['outcome']];
                }
            }
        }

        return $records;
    }
}
