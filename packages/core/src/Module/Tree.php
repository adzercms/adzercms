<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final readonly class Tree
{
    private const REFRESH = __DIR__.'/../../bin/adzercms-refresh';
    private const ENVIRONMENT = '.env.local';
    private const LOADED = 'SYMFONY_DOTENV_VARS';
    private const TIMEOUT = 1800;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $root,
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

    /** @param array<string, string> $environment */
    public function refresh(array $environment): ?string
    {
        $loaded = array_filter(explode(',', Selection::environment(self::LOADED)));
        $process = new Process(
            [$this->refresh],
            $this->root,
            [...array_fill_keys([...$loaded, self::LOADED], false), 'PHP_BINARY' => \PHP_BINARY, ...$environment],
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
