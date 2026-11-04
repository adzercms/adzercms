<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Command;

use AdzerCMS\Core\Schedule\Schedule;
use Cron\CronExpression;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Process\Process;

use function Symfony\Component\Clock\now;

#[AsCommand(name: 'adzercms:schedule', description: 'Starts the module consoles due in the minute the run started, each in its own process, skipping a console still running from an earlier run')]
final class ScheduleCommand
{
    public const LOCK = 'schedule.';
    private const POLL_MICROSECONDS = 100_000;

    /** @param array<string, string> $schedule */
    public function __construct(
        #[Autowire(param: Schedule::PARAMETER)] private readonly array $schedule,
        #[Autowire(param: 'kernel.project_dir')] private readonly string $projectDir,
        #[Autowire(param: 'kernel.environment')] private readonly string $environment,
        private readonly LockFactory $locks,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $minute = (new \DateTimeImmutable('@'.self::started()))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        /** @var array<string, array{Process, SharedLockInterface}> $running */
        $running = [];
        foreach ($this->schedule as $name => $cron) {
            if (!(new CronExpression($cron))->isDue($minute)) {
                continue;
            }
            $lock = $this->locks->createLock(self::LOCK.$name);
            if (!$lock->acquire()) {
                continue;
            }
            $process = new Process([$this->projectDir.'/bin/php', $this->projectDir.'/bin/console', $name], $this->projectDir, ['APP_ENV' => $this->environment], null, null);
            $process->start();
            $running[$name] = [$process, $lock];
        }

        $code = Command::SUCCESS;
        while ([] !== $running) {
            usleep(self::POLL_MICROSECONDS);
            foreach ($running as $name => [$process, $lock]) {
                if ($process->isRunning()) {
                    continue;
                }
                $lock->release();
                unset($running[$name]);
                foreach (preg_split('/\R/', rtrim($process->getOutput().$process->getErrorOutput())) ?: [] as $line) {
                    if ('' !== $line) {
                        $output->writeln($name.': '.$line);
                    }
                }
                if (!$process->isSuccessful()) {
                    $output->writeln(\sprintf('%s: exit %d', $name, $process->getExitCode() ?? -1));
                    $code = Command::FAILURE;
                }
            }
        }

        return $code;
    }

    private static function started(): int
    {
        $started = $_SERVER['REQUEST_TIME'] ?? null;

        return \is_int($started) ? $started : now()->getTimestamp();
    }
}
