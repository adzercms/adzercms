<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Command;

use AdzerCMS\Support\Tickets;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

use function Symfony\Component\Clock\now;

#[AsCommand(name: 'adzercms:support', description: 'Closes tickets waiting for the user longer than the term of the module')]
final class SupportCommand
{
    public function __construct(private readonly Tickets $tickets)
    {
    }

    public function __invoke(OutputInterface $output): int
    {
        $closed = $this->tickets->autoClose(now());
        if ($closed > 0) {
            $output->writeln(\sprintf('closed without the user: %d', $closed));
        }

        return Command::SUCCESS;
    }
}
