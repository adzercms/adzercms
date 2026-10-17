<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Asset;

use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntries;
use Symfony\Component\AssetMapper\ImportMap\RemotePackageStorage;

final class ModuleImportMap extends ImportMapConfigReader
{
    private ImportMapEntries $merged;

    /** @param array<string, string> $modules */
    public function __construct(
        string $importMapConfigPath,
        private readonly RemotePackageStorage $storage,
        private readonly array $modules,
    ) {
        parent::__construct($importMapConfigPath, $storage);
    }

    public function getEntries(): ImportMapEntries
    {
        if (isset($this->merged)) {
            return $this->merged;
        }

        $merged = new ImportMapEntries();
        $owners = [];
        foreach (['' => parent::getEntries()] + $this->declared() as $owner => $entries) {
            foreach ($entries as $entry) {
                if ($merged->has($entry->importName) && $merged->get($entry->importName) != $entry) {
                    throw new \LogicException(\sprintf('Import "%s" is declared differently by %s and %s.', $entry->importName, $owners[$entry->importName] ?: 'the instance', $owner ?: 'the instance'));
                }
                $merged->add($entry);
                $owners[$entry->importName] ??= $owner;
            }
        }

        return $this->merged = $merged;
    }

    public function writeEntries(ImportMapEntries $entries): void
    {
        $declared = array_merge(...array_map(static fn (ImportMapEntries $module): array => array_map(static fn ($entry): string => $entry->importName, iterator_to_array($module, false)), array_values($this->declared())));
        $own = new ImportMapEntries();
        foreach ($entries as $entry) {
            if (!\in_array($entry->importName, $declared, true)) {
                $own->add($entry);
            }
        }

        unset($this->merged);
        parent::writeEntries($own);
    }

    /** @return array<string, ImportMapEntries> */
    private function declared(): array
    {
        return array_map(fn (string $file): ImportMapEntries => new ImportMapConfigReader($file, $this->storage)->getEntries(), $this->modules);
    }
}
