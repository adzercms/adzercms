<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Showcase;

use AdzerCMS\Contracts\Analytics\Counter;
use AdzerCMS\Contracts\Showcase\Showcase;
use AdzerCMS\Contracts\Ui\CabinetEntry;
use AdzerCMS\Core\Module\Bundles;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Ui\Entries;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class Sections
{
    public const CORE = 'core';
    public const ROUTE = 'showcase';
    private const ENTRIES = [CabinetEntry::Home, CabinetEntry::Register, CabinetEntry::Forgot, CabinetEntry::Profile];

    /** @param iterable<Showcase> $sections */
    public function __construct(
        #[AutowireIterator(Showcase::class)]
        private iterable $sections,
        private Bundles $bundles,
        private Entries $entries,
        private Switches $switches,
    ) {
    }

    public function json(): string
    {
        $showcase = [self::CORE => $this->core()];
        foreach ($this->sections as $section) {
            $showcase[Registry::lowerKey($this->bundles->owner($section))] = $section->section();
        }

        return json_encode((object) array_filter($showcase), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    /** @return array<string, mixed> */
    private function core(): array
    {
        $counters = [];
        foreach ($this->switches->enabled(Counter::class) as $key => [$counter, $credentials]) {
            $counters[$key] = $counter->settings($credentials);
        }

        $core = [];
        foreach (self::ENTRIES as $entry) {
            $core[$entry->value] = $this->entries->url($entry, absolute: true);
        }

        return array_filter($core + ['counters' => $counters]);
    }
}
