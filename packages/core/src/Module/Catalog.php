<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Core\Composer\Package;
use AdzerCMS\Core\Composer\Repositories;
use Composer\InstalledVersions;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function Symfony\Component\Clock\now;

final readonly class Catalog
{
    public const BASE = [Repositories::PACKAGIST];

    private const SNAPSHOT = 'core.modules.catalog';
    private const LIFETIME = 604800;
    private const AGE = '-1 day';

    /** @param array<string, class-string> $bundles */
    public function __construct(
        private Repositories $repositories,
        private Snapshot $snapshot,
        #[Autowire('%kernel.bundles%')]
        private array $bundles,
    ) {
    }

    public function due(): bool
    {
        $at = $this->held()['at'];

        return null === $at || $at <= now()->modify(self::AGE);
    }

    public function row(string $name): ?CatalogRow
    {
        return array_values(array_filter($this->rows()['rows'], static fn (CatalogRow $row): bool => $name === $row->name))[0] ?? null;
    }

    /** @return list<string> */
    public function refresh(): array
    {
        $held = $this->held()['rows'];
        $names = [];
        $down = [];
        foreach (self::BASE as $repository) {
            $listed = $this->repositories->names($repository, Registry::TYPE);
            if (null === $listed) {
                $down[] = $repository;
            }
            array_push($names, ...$listed ?? []);
        }

        $names = array_values(array_unique($names));
        $lookup = $this->repositories->lookup(self::BASE, $names);
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
        ksort($rows);
        $this->snapshot->write(self::SNAPSHOT, $rows, self::LIFETIME);

        return array_values(array_unique([...$down, ...$lookup->down]));
    }

    /** @return array{at: ?\DateTimeImmutable, rows: list<CatalogRow>} */
    public function rows(): array
    {
        $held = $this->held();
        $registry = Registry::installed();
        $kinds = [];
        foreach ($registry->kinds() as $kind) {
            $kinds[$kind->key()] = $kind;
        }

        $rows = [];
        foreach ($held['rows'] as $name => $row) {
            $rows[$name] = new CatalogRow($name, $row['package'], self::kind($row['package']->kind, $kinds), $row['package']->version, null, $row['repository'], false);
        }
        foreach (array_unique(InstalledVersions::getInstalledPackagesByType(Registry::TYPE)) as $name) {
            $installed = (string) InstalledVersions::getPrettyVersion($name);
            $offered = $held['rows'][$name] ?? null;
            $manifest = null === $offered ? Registry::manifest($name) : [];
            $module = $registry->package($name);
            $rows[$name] = new CatalogRow(
                $name,
                $offered['package'] ?? Package::manifest(\is_array($manifest) ? $manifest : [], $installed),
                $module->kind ?? CoreKind::Feature,
                $offered['package']->version ?? null,
                $installed,
                $offered['repository'] ?? null,
                null !== $module && \in_array($module->bundle, $this->bundles, true),
            );
        }

        return ['at' => $held['at'], 'rows' => array_values($rows)];
    }

    /** @return array{at: ?\DateTimeImmutable, rows: array<string, array{package: Package, repository: string}>} */
    private function held(): array
    {
        $held = $this->snapshot->read(self::SNAPSHOT);
        $rows = [];
        foreach ($held['rows'] ?? [] as $name => $row) {
            if (\is_array($row) && ($row['package'] ?? null) instanceof Package && \is_string($row['repository'] ?? null)) {
                $rows[$name] = ['package' => $row['package'], 'repository' => $row['repository']];
            }
        }

        return ['at' => $held['at'] ?? null, 'rows' => $rows];
    }

    /** @param array<string, Kind> $kinds */
    private static function kind(?string $key, array $kinds): Kind|string
    {
        return null === $key ? CoreKind::Feature : $kinds[$key] ?? $key;
    }
}
