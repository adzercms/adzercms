<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Refusal;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

use function Symfony\Component\Translation\t;

final readonly class Tree
{
    public const TIMEOUT = 1800;

    private const REFRESH = __DIR__.'/../../bin/adzercms-refresh';
    private const SELECTOR = __DIR__.'/../../bin/adzercms-selection';
    private const BUILD = __DIR__.'/../../bin/adzercms-build';
    private const VERIFY = __DIR__.'/../../bin/adzercms-verify';
    private const PREVIEW = '--preview';
    private const SHARED = '.env';
    private const LOCAL = '.env.local';
    private const LOADED = 'SYMFONY_DOTENV_VARS';
    private const LOCKS = '/var/lock/';
    private const LOCKED = ['refresh', 'entry', 'serve', 'served'];
    private const ENTERED = 'adzercms.entry';
    private const COVERED = 'ADZERCMS_ENTRY';
    private const REFRESHED = 'ADZERCMS_REFRESHED';
    private const LITERAL = ':(literal)';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        public string $root,
        private string $refresh = self::REFRESH,
        private string $selector = self::SELECTOR,
        private string $build = self::BUILD,
        private string $verify = self::VERIFY,
    ) {
    }

    /** @return array<string, string>|string */
    public function enabling(string $directory, string $package): array|string
    {
        $process = new Process([\PHP_BINARY, $this->selector, $package], $directory, null, null, self::TIMEOUT);
        if (0 !== $process->run()) {
            return trim($process->getErrorOutput()."\n".$process->getOutput());
        }

        $selection = [];
        foreach (array_filter(explode("\n", $process->getOutput())) as $line) {
            [$name, $value] = explode('=', $line, 2) + [1 => ''];
            $selection[$name] = $value;
        }

        return $selection;
    }

    public function refusal(string ...$selected): ?Refusal
    {
        $changed = $this->changed();
        if ([] !== $changed) {
            return new Refusal(t('core.module.refusal.uncommitted', ['paths' => implode(', ', $changed)]));
        }
        $local = null === $this->top() ? [] : array_filter($selected, fn (string $name): bool => [] !== self::defining($this->lines(self::LOCAL), $name));

        return [] === $local ? null : new Refusal(t('core.module.refusal.local', ['names' => implode(', ', $local)]));
    }

    /**
     * @param array<string, string>   $environment
     * @param array<string, string>   $selection
     * @param (\Closure(): void)|null $change
     */
    public function refresh(string $subject, array $environment, array $selection = [], ?\Closure $change = null): Refusal|string|null
    {
        $entered = $GLOBALS[self::ENTERED] ?? null;
        if (\is_resource($entered)) {
            flock($entered, \LOCK_UN);
        }
        $locks = array_map($this->lock(...), self::LOCKED);

        try {
            $refusal = $this->refusal(...array_keys($selection));
            if (null !== $refusal) {
                return $refusal;
            }
            if (null !== $change) {
                $change();
            }
            $changed = $this->changed();
            $failure = $this->run([$this->refresh], [...$environment, ...$selection]);
            if (null !== $failure) {
                return $failure;
            }
            foreach ($selection as $name => $value) {
                $this->choose($name, $value);
            }

            return null === $this->top() ? null : $this->commit($subject, [...$changed, ...([] === $selection ? [] : [self::SHARED])]);
        } finally {
            array_map(fclose(...), array_reverse($locks));
        }
    }

    /** @param array<string, string> $environment */
    public function verify(string $copy, array $environment): ?string
    {
        return $this->run([$this->verify, $copy], $environment);
    }

    /** @param array<string, string> $selection */
    public function preview(array $selection): ?string
    {
        return $this->run([$this->build, self::PREVIEW], $selection);
    }

    /**
     * @param list<string>          $command
     * @param array<string, string> $environment
     */
    private function run(array $command, array $environment): ?string
    {
        $loaded = array_filter(explode(',', Selection::environment(self::LOADED)));
        $process = new Process(
            $command,
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

    private function choose(string $name, string $value): void
    {
        $file = null === $this->top() ? self::LOCAL : self::SHARED;
        $lines = $this->lines($file);
        $defining = self::defining($lines, $name);

        foreach (\array_slice($defining, 1) as $repeated) {
            unset($lines[$repeated]);
        }
        $lines[$defining[0] ?? \count($lines)] = $name.'='.$value;

        (new Filesystem())->dumpFile($this->root.'/'.$file, implode("\n", $lines)."\n");
    }

    /** @return array<int, string> */
    private function lines(string $file): array
    {
        $path = $this->root.'/'.$file;

        return is_file($path) ? file($path, \FILE_IGNORE_NEW_LINES) ?: [] : [];
    }

    /**
     * @param array<int, string> $lines
     *
     * @return list<int>
     */
    private static function defining(array $lines, string $name): array
    {
        return array_keys(preg_grep('/^\s*(?:export\s+)?'.preg_quote($name, '/').'=/', $lines) ?: []);
    }

    /** @return list<string> */
    private function changed(): array
    {
        $top = $this->top();
        if (null === $top) {
            return [];
        }
        $real = $this->real();
        $prefix = $real === $top ? 0 : \strlen($real) - \strlen(rtrim($top, '/'));
        $status = $this->git('status', '--porcelain', '-z', '--no-renames', '--untracked-files=all', '--', '.');
        $status->mustRun();

        return array_map(
            static fn (string $entry): string => substr($entry, 3 + $prefix),
            array_values(array_filter(explode("\0", $status->getOutput()), static fn (string $entry): bool => '' !== $entry)),
        );
    }

    /** @param list<string> $paths */
    private function commit(string $subject, array $paths): ?string
    {
        $specs = array_map(static fn (string $path): string => self::LITERAL.$path, array_values(array_unique($paths)));
        if ([] === $specs) {
            return null;
        }
        $added = $this->git('add', '--all', '--', ...$specs);
        if (0 !== $added->run()) {
            return trim($added->getErrorOutput());
        }
        if (0 === $this->git('diff', '--cached', '--quiet', '--', ...$specs)->run()) {
            return null;
        }
        $commit = $this->git('commit', '--quiet', '--message', $subject, '--', ...$specs)->setEnv([self::REFRESHED => $this->real()]);

        return 0 === $commit->run() ? null : trim($commit->getOutput()."\n".$commit->getErrorOutput());
    }

    private function git(string ...$arguments): Process
    {
        return new Process(['git', ...$arguments], $this->root, null, null, self::TIMEOUT);
    }

    private function top(): ?string
    {
        for ($directory = $this->real();; $directory = \dirname($directory)) {
            if (file_exists($directory.'/.git')) {
                return $directory;
            }
            if (\dirname($directory) === $directory) {
                return null;
            }
        }
    }

    private function real(): string
    {
        return realpath($this->root) ?: $this->root;
    }
}
