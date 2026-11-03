<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Schedule;

use AdzerCMS\Core\Command\ScheduleCommand;
use AdzerCMS\Core\Schedule\Schedule;
use AdzerCMS\Core\Tests\Container;
use AdzerCMS\Core\Tests\Schedule\Fixture\Dormant;
use AdzerCMS\Core\Tests\Schedule\Fixture\Tick;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Lock\LockFactory;

final class ScheduleCommandTest extends KernelTestCase
{
    use Container;

    /** @return iterable<string, array{bool, list<string>}> */
    public static function runs(): iterable
    {
        yield 'due console runs, one not due waits' => [false, [Tick::NAME.': '.Tick::LINE]];
        yield 'due console still running from an earlier run is skipped' => [true, []];
    }

    /** @param list<string> $lines */
    #[DataProvider('runs')]
    #[Group('ACMS-CORE-1061')]
    public function testRunStartsDueConsolesNotRunningYet(bool $running, array $lines): void
    {
        $schedule = self::getContainer()->getParameter(Schedule::PARAMETER);
        self::assertIsArray($schedule);
        $fixtures = [Dormant::NAME => Dormant::CRON, Tick::NAME => Tick::CRON];
        self::assertSame($fixtures, array_intersect_key($schedule, $fixtures), 'the schedule takes the cron of each scheduled console');

        $locks = self::service(LockFactory::class, 'lock.factory');
        $earlier = $locks->createLock(ScheduleCommand::LOCK.Tick::NAME);
        self::assertTrue(!$running || $earlier->acquire());
        $output = new BufferedOutput();
        try {
            $code = (new ScheduleCommand($fixtures, self::parameter('kernel.project_dir'), self::parameter('kernel.environment'), $locks))($output);
        } finally {
            $earlier->release();
        }

        self::assertSame(Command::SUCCESS, $code);
        self::assertSame($lines, array_values(array_filter(explode("\n", trim($output->fetch())))));
    }
}
