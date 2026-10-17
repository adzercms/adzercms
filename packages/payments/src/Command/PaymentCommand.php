<?php

declare(strict_types=1);

namespace AdzerCMS\Payments\Command;

use AdzerCMS\Payments\PaymentService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'adzercms:payments', description: 'Settles started payments by asking the provider and cancels stale ones')]
final class PaymentCommand
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    public function __invoke(OutputInterface $output): int
    {
        $round = $this->payments->settleStarted();
        if ([] !== $round['settled']) {
            $output->writeln(\sprintf('settled by asking: %s', implode(', ', $round['settled'])));
        }

        $stale = $this->payments->cancelStale();
        if ($stale > 0) {
            $output->writeln(\sprintf('cancelled unpaid: %d', $stale));
        }

        if ([] !== $round['failures']) {
            $output->writeln(\sprintf('provider did not answer: %s', implode('; ', $round['failures'])));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
