<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Command;

use AdzerCMS\Builder\ProjectAlbums;
use AdzerCMS\Builder\Transfers;
use AdzerCMS\Contracts\Schedule\Scheduled;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'adzercms:builder', description: 'Removes the pictures of the projects no block of their project holds after the term of the module and the transfers of projects past their term')]
#[Scheduled('50 4 * * *')]
final class BuilderCommand
{
    public function __construct(private readonly ProjectAlbums $albums, private readonly Transfers $transfers)
    {
    }

    public function __invoke(OutputInterface $output): int
    {
        $output->writeln(\sprintf('pictures removed: %d', $this->albums->sweep()));
        $output->writeln(\sprintf('transfers expired: %d', $this->transfers->sweep()));

        return Command::SUCCESS;
    }
}
