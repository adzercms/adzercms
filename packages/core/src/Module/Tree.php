<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final readonly class Tree
{
    public const TIMEOUT = 1800;

    private const REFRESH = __DIR__.'/../../bin/adzercms-refresh';
    private const ENVIRONMENT = '.env.local';
    private const LOADED = 'SYMFONY_DOTENV_VARS';
    private const LOCKS = '/var/lock/';
    private const LOCKED = ['refresh', 'entry', 'serve', 'served'];
    private const ENTERED = 'adzercms.entry';
    private const COVERED = 'ADZERCMS_ENTRY';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        public string $root,
        private string $refresh = self::REFRESH,
    ) {
    }

    public function versioned(): bool
    {
        for ($directory = $this->root;; $directory = \dirname($directory)) {
            if (file_exists($directory.'/.git')) {
                return true;
            }
            if (\dirname($directory) === $directory) {
                return false;
            }
        }
    }

    /**
     * @param array<string, string>   $environment
     * @param (\Closure(): void)|null $change
     */
    public function refresh(array $environment, ?\Closure $change = null): ?string
    {
        $entered = $GLOBALS[self::ENTERED] ?? null;
        if (\is_resource($entered)) {
            flock($entered, \LOCK_UN);
        }
        $locks = array_map($this->lock(...), self::LOCKED);

        try {
            if (null !== $change) {
                $change();
            }

            return $this->run($environment);
        } finally {
            array_map(fclose(...), array_reverse($locks));
        }
    }

    /** @param array<string, string> $environment */
    private function run(array $environment): ?string
    {
        $loaded = array_filter(explode(',', Selection::environment(self::LOADED)));
        $process = new Process(
            [$this->refresh],
            $this->root,
            [...array_fill_keys([...$loaded, self::LOADED], false), 'PHP_BINARY' => \PHP_BINARY, self::COVERED => $this->root, ...$environment],
            null,
            self::TIMEOUT,
        );

        $output = '';
        try {
            $process->run(static function (string $type, string $buffer) use (&$output): void {
                $output .= $buffer;
            });
        } catch (ProcessTimedOutException $timeout) {
            return trim($output."\n".$timeout->getMessage());
        }

        return $process->isSuccessful() ? null : trim($output);
    }

    /** @return resource */
    private function lock(string $name)
    {
        (new Filesystem())->mkdir($this->root.self::LOCKS);
        $lock = fopen($this->root.self::LOCKS.$name, 'c');

        return false !== $lock && flock($lock, \LOCK_EX) ? $lock : throw new \RuntimeException(\sprintf('lock %s of the instance is not taken', $name));
    }

    public function choose(string $name, string $value): void
    {
        $file = $this->root.'/'.self::ENVIRONMENT;
        $lines = is_file($file) ? file($file, \FILE_IGNORE_NEW_LINES) ?: [] : [];
        $defining = array_keys(preg_grep('/^\s*(?:export\s+)?'.preg_quote($name, '/').'=/', $lines) ?: []);

        foreach (\array_slice($defining, 1) as $repeated) {
            unset($lines[$repeated]);
        }
        $lines[$defining[0] ?? \count($lines)] = $name.'='.$value;

        (new Filesystem())->dumpFile($file, implode("\n", $lines)."\n");
    }
}
