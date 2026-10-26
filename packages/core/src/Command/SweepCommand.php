<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use AdzerCMS\Core\Confirmation\Confirmations;
use AdzerCMS\Core\Confirmation\ConfirmationService;
use AdzerCMS\Core\Kernel;
use AdzerCMS\Core\Notice\Journal;
use AdzerCMS\Core\Session\Logins;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'adzercms:sweep', description: 'Core cleanup: expired logins of the registry, notices past the journal term, expired confirmations, cache builds of former module sets; applies mail changes past their delay')]
final class SweepCommand
{
    public function __construct(
        private readonly Logins $logins,
        private readonly Journal $journal,
        private readonly Confirmations $confirmations,
        private readonly ConfirmationService $changes,
        #[Autowire(service: 'kernel')]
        private readonly Kernel $kernel,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $output->writeln(\sprintf('logins removed: %d', $this->logins->sweep()));
        $output->writeln(\sprintf('notices removed: %d', $this->journal->sweep()));
        $output->writeln(\sprintf('confirmations removed: %d', $this->confirmations->sweep()));
        $output->writeln(\sprintf('mail changes applied: %d', $this->changes->applyDelayed()));

        $live = $this->kernel->builds();
        $builds = 0;
        foreach ($this->kernel->otherBuilds() as $former) {
            $builds += \count($former->retire($live));
        }
        $output->writeln(\sprintf('cache builds removed: %d', $builds));

        return Command::SUCCESS;
    }
}
