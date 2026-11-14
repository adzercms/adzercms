<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Showcase;

use AdzerCMS\Contracts\Analytics\Counter;
use AdzerCMS\Contracts\Showcase\Showcase;
use AdzerCMS\Contracts\Ui\CabinetEntry;
use AdzerCMS\Core\Account\Consents;
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
        private Consents $consents,
    ) {
    }

    public function json(): string
    {
        $showcase = [];
        foreach ($this->keys() as $key) {
            $showcase[$key] = $this->section($key);
        }

        return json_encode((object) array_filter($showcase), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
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

    /** @return array<mixed> */
    public function section(string $key): array
    {
        if (self::CORE === $key) {
            return $this->core();
        }
        foreach ($this->sections as $section) {
            if ($this->key($section) === $key) {
                return $section->section();
            }
        }

        return [];
    }

    private function key(Showcase $section): string
    {
        return Registry::lowerKey($this->bundles->owner($section));
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

        return array_filter($core + ['edition' => $this->consents->edition(), 'counters' => $counters]);
    }
}
