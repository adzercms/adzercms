<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use AdzerCMS\Core\CoreBundle;
use AdzerCMS\Core\Schedule\Schedule;
use Cron\CronExpression;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\Clock\now;

#[AsCommand(name: 'adzercms:schedule', description: 'Starts the module consoles due in the minute the run started, each in its own process that outlives the run, skipping a console still running from an earlier run')]
final class ScheduleCommand
{
    public const LOCK = 'schedule.';
    private const LOCK_DESCRIPTOR = 3;
    private const ENTRY = 'ADZERCMS_ENTRY';

    /** @param array<string, string> $schedule */
    public function __construct(
        #[Autowire(param: Schedule::PARAMETER)] private readonly array $schedule,
        #[Autowire(param: 'kernel.project_dir')] private readonly string $projectDir,
        #[Autowire(param: 'kernel.environment')] private readonly string $environment,
        #[Autowire(param: CoreBundle::LOCK_DIRECTORY)] private readonly string $locks,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $minute = (new \DateTimeImmutable('@'.self::started()))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $environment = [...array_diff_key(getenv(), [self::ENTRY => true]), 'APP_ENV' => $this->environment];
        $streams = [['file', '/dev/null', 'r'], self::stream($output), self::stream($output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output)];
        (new Filesystem())->mkdir($this->locks);
        foreach ($this->schedule as $name => $cron) {
            if (!(new CronExpression($cron))->isDue($minute)) {
                continue;
            }
            $lock = fopen($this->locks.'/'.self::LOCK.$name, 'ce') ?: throw new \RuntimeException(\sprintf('lock of %s is not opened', $name));
            if (flock($lock, \LOCK_EX | \LOCK_NB)) {
                proc_open([$this->projectDir.'/bin/php', $this->projectDir.'/bin/console', $name], [...$streams, self::LOCK_DESCRIPTOR => $lock], $pipes, $this->projectDir, $environment);
            }
            fclose($lock);
        }

        return Command::SUCCESS;
    }

    /** @return resource */
    private static function stream(OutputInterface $output)
    {
        return $output instanceof StreamOutput ? $output->getStream() : \STDOUT;
    }

    private static function started(): int
    {
        $started = $_SERVER['REQUEST_TIME'] ?? null;

        return \is_int($started) ? $started : now()->getTimestamp();
    }
}
