<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\ExtensionType;
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
        private Store $store,
        #[Autowire('%kernel.bundles%')]
        private array $bundles,
    ) {
    }

    public function due(): bool
    {
        $held = $this->held();

        return null === $held['at']
            || $held['at'] <= now()->modify([] === $held['down'] ? self::AGE : self::DOWN_AGE)
            || $held['fingerprint'] !== $this->fingerprint();
    }

    public function row(string $name): ?CatalogRow
    {
        return array_values(array_filter($this->rows()['rows'], static fn (CatalogRow $row): bool => $name === $row->name))[0] ?? null;
    }

    /** @return list<string> */
    public function refresh(): array
    {
        $fingerprint = $this->fingerprint();
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
        $url = $this->store->url();
        $store = $this->store->read($url, $kept['store']);
        if (null === $store) {
            $down[] = $url;
            $store = $url === $kept['store']?->url ? $kept['store'] : null;
        }
        $marked = null === $marks ? $kept['marks'] : array_intersect_key($marks, $rows + array_flip(InstalledVersions::getInstalledPackagesByType(Registry::TYPE)));
        $this->snapshot->write(self::SNAPSHOT, ['packages' => $rows, 'down' => $down, 'fingerprint' => $fingerprint, 'marks' => $marked, 'store' => $store], self::LIFETIME);

        return null === $marks ? [...$down, Marks::REGISTRY] : $down;
    }

    /** @return array{rows: list<CatalogRow>, down: list<string>} */
    public function rows(): array
    {
        $held = $this->held();
        $registry = Registry::installed();
        $kinds = [];
        foreach ($registry->kinds() as $kind) {
            $kinds[$kind->key()] = $kind;
        }

        $marks = $held['marks'];
        $entries = $held['store']->entries ?? [];
        $rows = [];
        foreach ($held['packages'] as $name => $row) {
            $kind = self::kind($row['package']->kind, $kinds);
            $rows[$name] = new CatalogRow($name, $row['package'], $kind, $row['package']->version, null, $row['repository'], false, self::marked($marks, $name, $row['package']->reference), false, false, null, self::type($kind, $entries[$name] ?? null), $entries[$name]->demo ?? null);
        }
        foreach (array_unique(InstalledVersions::getInstalledPackagesByType(Registry::TYPE)) as $name) {
            $installed = (string) InstalledVersions::getPrettyVersion($name);
            $offered = $held['packages'][$name] ?? null;
            $manifest = null === $offered ? Registry::manifest($name) : [];
            $module = $registry->package($name);
            $kind = $module->kind ?? CoreKind::Feature;
            $reference = InstalledVersions::getReference($name);
            $rows[$name] = new CatalogRow(
                $name,
                $offered['package'] ?? Package::manifest(\is_array($manifest) ? $manifest : [], $installed),
                $kind,
                $offered['package']->version ?? null,
                $installed,
                $offered['repository'] ?? null,
                null !== $module && \in_array($module->bundle, $this->bundles, true),
                self::marked($marks, $name, $offered['package']->reference ?? null),
                self::marked($marks, $name, $reference),
                null !== $offered && self::newer($offered['package'], $installed, $reference),
                $module?->key,
                self::type($kind, $entries[$name] ?? null),
                $entries[$name]->demo ?? null,
            );
        }
        $positions = array_flip(array_keys($entries));
        $rows = array_values($rows);
        usort($rows, static fn (CatalogRow $left, CatalogRow $right): int => [$positions[$left->name] ?? \PHP_INT_MAX, $left->name] <=> [$positions[$right->name] ?? \PHP_INT_MAX, $right->name]);

        return ['rows' => $rows, 'down' => $held['down']];
    }

    private static function type(Kind|string $kind, ?StoreEntry $entry): ExtensionType
    {
        return $entry->type ?? match ($kind) {
            CoreKind::Theme => ExtensionType::Theme,
            CoreKind::Feature => ExtensionType::Module,
            default => ExtensionType::Integration,
        };
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

    private function fingerprint(): string
    {
        $installed = [];
        foreach (array_unique(InstalledVersions::getInstalledPackagesByType(Registry::TYPE)) as $name) {
            $installed[$name] = InstalledVersions::getReference($name) ?? InstalledVersions::getPrettyVersion($name);
        }
        ksort($installed);

        return hash('xxh128', serialize([$this->sources->fingerprint(), $installed, $this->store->url()]));
    }

    /** @return array{at: ?\DateTimeImmutable, packages: array<string, array{package: Package, repository: string}>, down: list<string>, fingerprint: ?string, marks: array<string, list<string>>, store: ?StoreDocument} */
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

        return [
            'at' => $held['at'] ?? null,
            'packages' => $packages,
            'down' => $down,
            'fingerprint' => \is_string($taken['fingerprint'] ?? null) ? $taken['fingerprint'] : null,
            'marks' => $marks,
            'store' => ($taken['store'] ?? null) instanceof StoreDocument ? $taken['store'] : null,
        ];
    }

    /** @param array<string, Kind> $kinds */
    private static function kind(?string $key, array $kinds): Kind|string
    {
        return null === $key ? CoreKind::Feature : $kinds[$key] ?? $key;
    }
}
