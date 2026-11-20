<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Command;

use AdzerCMS\Contracts\Schedule\Scheduled;
use AdzerCMS\Pages\Pictures;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'adzercms:pages', description: 'Removes the pictures of the pages module no block holds after the term of the module')]
#[Scheduled('40 4 * * *')]
final class PagesCommand
{
    public function __construct(private readonly Pictures $pictures)
    {
    }

    public function __invoke(OutputInterface $output): int
    {
        $output->writeln(\sprintf('pictures removed: %d', $this->pictures->sweep()));

        return Command::SUCCESS;
    }
}
