<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use AdzerCMS\Core\Module\Catalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'adzercms:modules', description: 'Module catalog: snapshot of the module packages of the base Composer repositories')]
final class ModulesCommand
{
    public function __construct(private readonly Catalog $catalog)
    {
    }

    public function __invoke(OutputInterface $output): int
    {
        $down = $this->catalog->refresh();
        foreach ($down as $repository) {
            $output->writeln(\sprintf('repository unavailable: %s', $repository));
        }

        return [] === $down ? Command::SUCCESS : Command::FAILURE;
    }
}
