<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Schedule;

use AdzerCMS\Core\Command\ScheduleCommand;
use AdzerCMS\Core\CoreBundle;
use AdzerCMS\Core\Schedule\Schedule;
use AdzerCMS\Core\Tests\Container;
use AdzerCMS\Core\Tests\Schedule\Fixture\Dormant;
use AdzerCMS\Core\Tests\Schedule\Fixture\Tick;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Filesystem\Filesystem;

final class ScheduleCommandTest extends KernelTestCase
{
    use Container;

    /** @return iterable<string, array{bool, list<string>}> */
    public static function runs(): iterable
    {
        yield 'due console runs entered by itself, one not due waits' => [false, [Tick::LINE, Tick::ENTERED]];
        yield 'due console still running from an earlier run is skipped' => [true, []];
    }

    /** @param list<string> $lines */
    #[DataProvider('runs')]
    #[Group('ACMS-CORE-1061')]
    public function testRunStartsDueConsolesNotRunningYetAndLeavesThem(bool $running, array $lines): void
    {
        $schedule = self::getContainer()->getParameter(Schedule::PARAMETER);
        self::assertIsArray($schedule);
        $fixtures = [Dormant::NAME => Dormant::CRON, Tick::NAME => Tick::CRON];
        self::assertSame($fixtures, array_intersect_key($schedule, $fixtures), 'the schedule takes the cron of each scheduled console');

        $locks = self::parameter(CoreBundle::LOCK_DIRECTORY);
        $root = self::parameter('kernel.project_dir');
        $files = new Filesystem();
        $files->mkdir($locks);
        $log = $files->tempnam(sys_get_temp_dir(), 'adzercms-schedule-');
        $earlier = fopen($locks.'/'.ScheduleCommand::LOCK.Tick::NAME, 'ce') ?: self::fail('the lock of the console is not opened');
        self::assertTrue(!$running || flock($earlier, \LOCK_EX));
        $output = fopen($log, 'w') ?: self::fail('the output of the run is not opened');
        putenv('ADZERCMS_ENTRY='.$root);
        try {
            $code = (new ScheduleCommand($fixtures, $root, self::parameter('kernel.environment'), $locks))(new StreamOutput($output));
            self::assertTrue(flock($earlier, \LOCK_EX), 'the console holds its lock until it ends');
        } finally {
            putenv('ADZERCMS_ENTRY');
            fclose($earlier);
            fclose($output);
        }

        self::assertSame(Command::SUCCESS, $code);
        self::assertSame($lines, file($log, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES));
        $files->remove($log);
    }
}
