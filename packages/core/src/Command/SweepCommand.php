<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use AdzerCMS\Core\Confirmation\Confirmations;
use AdzerCMS\Core\Notice\Journal;
use AdzerCMS\Core\Session\Logins;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'adzercms:sweep', description: 'Core cleanup: expired logins of the registry, notices past the journal term, expired confirmations')]
final class SweepCommand
{
    public function __construct(
        private readonly Logins $logins,
        private readonly Journal $journal,
        private readonly Confirmations $confirmations,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $output->writeln(\sprintf('logins removed: %d', $this->logins->sweep()));
        $output->writeln(\sprintf('notices removed: %d', $this->journal->sweep()));
        $output->writeln(\sprintf('confirmations removed: %d', $this->confirmations->sweep()));

        return Command::SUCCESS;
    }
}
