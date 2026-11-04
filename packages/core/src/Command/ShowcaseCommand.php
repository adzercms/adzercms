<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use AdzerCMS\Core\Showcase\Sections;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'adzercms:showcase', description: 'Public values of the core and the modules for the project site as JSON')]
final readonly class ShowcaseCommand
{
    public function __construct(private Sections $sections)
    {
    }

    public function __invoke(OutputInterface $output): int
    {
        $output->writeln($this->sections->json());

        return Command::SUCCESS;
    }
}
