<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Text;

use AdzerCMS\Contracts\Text\ValueSection;
use AdzerCMS\Contracts\Ui\CabinetEntry;
use AdzerCMS\Core\Account\Consents;
use AdzerCMS\Core\Module\Bundles;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Ui\Entries;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class ValueSections
{
    public const CORE = 'core';
    private const ENTRIES = [CabinetEntry::Home, CabinetEntry::Register, CabinetEntry::Forgot, CabinetEntry::Profile];

    /** @param iterable<ValueSection> $sections */
    public function __construct(
        #[AutowireIterator(ValueSection::class)]
        private iterable $sections,
        private Bundles $bundles,
        private Entries $entries,
        private Consents $consents,
    ) {
    }

    /** @return list<string> */
    public function keys(): array
    {
        $keys = [self::CORE];
        foreach ($this->sections as $section) {
            $keys[] = $this->key($section);
        }

        return $keys;
    }

    /** @return array<string, string|int|float|list<string|int|float>> */
    public function section(string $key): array
    {
        if (self::CORE === $key) {
            return $this->core();
        }
        foreach ($this->sections as $section) {
            if ($this->key($section) === $key) {
                return $section->values();
            }
        }

        return [];
    }

    private function key(ValueSection $section): string
    {
        return Registry::lowerKey($this->bundles->owner($section));
    }

    /** @return array<string, string> */
    private function core(): array
    {
        $core = [];
        foreach (self::ENTRIES as $entry) {
            $core[$entry->value] = $this->entries->url($entry, absolute: true);
        }

        return array_filter($core + ['edition' => $this->consents->edition()]);
    }
}
