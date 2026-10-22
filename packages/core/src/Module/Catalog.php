<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Core\Composer\Artifacts;
use AdzerCMS\Core\Composer\Package;
use AdzerCMS\Core\Composer\Repositories;
use Composer\InstalledVersions;
use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function Symfony\Component\Clock\now;

final readonly class Catalog
{
    private const SNAPSHOT = 'core.modules.catalog';
    private const LIFETIME = 604800;
    private const AGE = '-1 day';
    private const DOWN_AGE = '-15 minutes';
    private const DEV = 'dev';

    /** @param array<string, class-string> $bundles */
    public function __construct(
        private Repositories $repositories,
        private Snapshot $snapshot,
        private Sources $sources,
        private Artifacts $artifacts,
        private Marks $marks,
        #[Autowire('%kernel.bundles%')]
        private array $bundles,
    ) {
    }

    public function due(): bool
    {
        $held = $this->held();

        return null === $held['at']
            || $held['at'] <= now()->modify([] === $held['down'] ? self::AGE : self::DOWN_AGE)
            || $held['sources'] !== $this->sources->fingerprint();
    }

    public function row(string $name): ?CatalogRow
    {
        return array_values(array_filter($this->rows()['rows'], static fn (CatalogRow $row): bool => $name === $row->name))[0] ?? null;
    }

    /** @return list<string> */
    public function refresh(): array
    {
        $fingerprint = $this->sources->fingerprint();
        $kept = $this->held();
        $held = $kept['packages'];
        $marks = $this->marks->passed();
        $sources = $this->sources->repositories();
        $zipped = $this->artifacts->packages();
        $names = [];
        $down = [];
        foreach ($sources as $source) {
            $listed = $this->repositories->names($source, Registry::TYPE);
            if (null === $listed) {
                $down[] = $source->url;
            }
            array_push($names, ...$listed ?? []);
        }

        $names = array_values(array_diff(array_unique($names), array_keys($zipped)));
        $lookup = $this->repositories->lookup($sources, $names);
        $rows = array_filter(
            $held,
            static fn (array $row, string $name): bool => \in_array($row['repository'], $down, true) || (\in_array($name, $names, true) && !$lookup->decided($name)),
            \ARRAY_FILTER_USE_BOTH,
        );
        foreach ($lookup->packages as $name => $package) {
            if (null !== $package && Registry::TYPE === $package->type) {
                $rows[$name] = ['package' => $package, 'repository' => $lookup->sources[$name]];
            }
        }
        foreach ($zipped as $name => $package) {
            if (Registry::TYPE === $package->type) {
                $rows[$name] = ['package' => $package, 'repository' => Artifacts::REPOSITORY];
            }
        }
        ksort($rows);
        $down = array_values(array_unique([...$down, ...$lookup->down]));
        $marked = null === $marks ? $kept['marks'] : array_intersect_key($marks, $rows + array_flip(InstalledVersions::getInstalledPackagesByType(Registry::TYPE)));
        $this->snapshot->write(self::SNAPSHOT, ['packages' => $rows, 'down' => $down, 'sources' => $fingerprint, 'marks' => $marked], self::LIFETIME);

        return null === $marks ? [...$down, Marks::REGISTRY] : $down;
    }

    /** @return array{at: ?\DateTimeImmutable, rows: list<CatalogRow>, down: list<string>} */
    public function rows(): array
    {
        $held = $this->held();
        $registry = Registry::installed();
        $kinds = [];
        foreach ($registry->kinds() as $kind) {
            $kinds[$kind->key()] = $kind;
        }

        $marks = $held['marks'];
        $rows = [];
        foreach ($held['packages'] as $name => $row) {
            $rows[$name] = new CatalogRow($name, $row['package'], self::kind($row['package']->kind, $kinds), $row['package']->version, null, $row['repository'], false, self::marked($marks, $name, $row['package']->reference), false, false, null);
        }
        foreach (array_unique(InstalledVersions::getInstalledPackagesByType(Registry::TYPE)) as $name) {
            $installed = (string) InstalledVersions::getPrettyVersion($name);
            $offered = $held['packages'][$name] ?? null;
            $manifest = null === $offered ? Registry::manifest($name) : [];
            $module = $registry->package($name);
            $reference = InstalledVersions::getReference($name);
            $rows[$name] = new CatalogRow(
                $name,
                $offered['package'] ?? Package::manifest(\is_array($manifest) ? $manifest : [], $installed),
                $module->kind ?? CoreKind::Feature,
                $offered['package']->version ?? null,
                $installed,
                $offered['repository'] ?? null,
                null !== $module && \in_array($module->bundle, $this->bundles, true),
                self::marked($marks, $name, $offered['package']->reference ?? null),
                self::marked($marks, $name, $reference),
                null !== $offered && self::newer($offered['package'], $installed, $reference),
                $module?->key,
            );
        }

        return ['at' => $held['at'], 'rows' => array_values($rows), 'down' => $held['down']];
    }

    private static function newer(Package $offered, string $installed, ?string $reference): bool
    {
        $branch = self::DEV === VersionParser::parseStability($offered->version);

        if ($branch !== (self::DEV === VersionParser::parseStability($installed))) {
            return false;
        }

        if ($branch) {
            return null !== $offered->reference && $offered->reference !== $reference;
        }
        $parser = new VersionParser();

        return Comparator::greaterThan($parser->normalize($offered->version), $parser->normalize($installed));
    }

    /** @param array<string, list<string>> $marks */
    private static function marked(array $marks, string $name, ?string $reference): bool
    {
        return null !== $reference && \in_array($reference, $marks[$name] ?? [], true);
    }

    /** @return array{at: ?\DateTimeImmutable, packages: array<string, array{package: Package, repository: string}>, down: list<string>, sources: ?string, marks: array<string, list<string>>} */
    private function held(): array
    {
        $held = $this->snapshot->read(self::SNAPSHOT);
        $taken = $held['rows'] ?? [];
        $packages = [];
        foreach (\is_array($taken['packages'] ?? null) ? $taken['packages'] : [] as $name => $row) {
            if (\is_array($row) && ($row['package'] ?? null) instanceof Package && \is_string($row['repository'] ?? null)) {
                $packages[(string) $name] = ['package' => $row['package'], 'repository' => $row['repository']];
            }
        }
        $down = \is_array($taken['down'] ?? null) ? array_values(array_filter($taken['down'], \is_string(...))) : [];
        $marks = [];
        foreach (\is_array($taken['marks'] ?? null) ? $taken['marks'] : [] as $name => $references) {
            if (\is_array($references)) {
                $marks[(string) $name] = array_values(array_filter($references, \is_string(...)));
            }
        }

        return ['at' => $held['at'] ?? null, 'packages' => $packages, 'down' => $down, 'sources' => \is_string($taken['sources'] ?? null) ? $taken['sources'] : null, 'marks' => $marks];
    }

    /** @param array<string, Kind> $kinds */
    private static function kind(?string $key, array $kinds): Kind|string
    {
        return null === $key ? CoreKind::Feature : $kinds[$key] ?? $key;
    }
}
