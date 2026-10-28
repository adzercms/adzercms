<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use AdzerCMS\Contracts\Analytics\Counter;
use AdzerCMS\Contracts\Showcase\Showcase;
use AdzerCMS\Contracts\Ui\CabinetEntry;
use AdzerCMS\Core\Module\Bundles;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Ui\Entries;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand(name: 'adzercms:showcase', description: 'Public values of the core and the modules for the project site as JSON')]
final readonly class ShowcaseCommand
{
    public const CORE = 'core';

    /** @param iterable<Showcase> $sections */
    public function __construct(
        #[AutowireIterator(Showcase::class)]
        private iterable $sections,
        private Bundles $bundles,
        private Entries $entries,
        private Switches $switches,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $showcase = [self::CORE => $this->core()];
        foreach ($this->sections as $section) {
            $showcase[Registry::lowerKey($this->bundles->owner($section))] = $section->section();
        }

        $output->writeln(json_encode((object) array_filter($showcase), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function core(): array
    {
        $counters = [];
        foreach ($this->switches->enabled(Counter::class) as $key => [$counter, $credentials]) {
            $counters[$key] = $counter->settings($credentials);
        }

        return array_filter([
            'home' => $this->entries->url(CabinetEntry::Home, absolute: true),
            'register' => $this->entries->url(CabinetEntry::Register, absolute: true),
            'counters' => $counters,
        ]);
    }
}
