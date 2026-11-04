<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Schedule\Fixture;

use AdzerCMS\Contracts\Schedule\Scheduled;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: 'Scheduled on a day that never comes')]
#[Scheduled(self::CRON)]
final class Dormant
{
    public const NAME = 'adzercms-test:dormant';
    public const CRON = '0 0 30 2 *';

    public function __invoke(OutputInterface $output): int
    {
        $output->writeln(self::NAME);

        return Command::SUCCESS;
    }
}
