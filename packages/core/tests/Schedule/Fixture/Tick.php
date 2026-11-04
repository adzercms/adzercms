<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Schedule\Fixture;

use AdzerCMS\Contracts\Schedule\Scheduled;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: 'Scheduled every minute')]
#[Scheduled(self::CRON)]
final class Tick
{
    public const NAME = 'adzercms-test:tick';
    public const CRON = '* * * * *';
    public const LINE = 'tick';

    public function __invoke(OutputInterface $output): int
    {
        $output->writeln(self::LINE);

        return Command::SUCCESS;
    }
}
