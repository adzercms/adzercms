<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Core\Module\Installed;
use AdzerCMS\Core\Module\Registry;
use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final readonly class ModuleCheck
{
    public const BUILD = 'build';
    public const AUDIT = 'audit';
    public const PHPSTAN = 'phpstan';
    public const PSALM = 'psalm';
    public const BOUNDARY = 'boundary';
    public const THEME = 'theme';
    public const DATABASES = 'MODULE_CHECK_DATABASES';

    private const PSALM_CONFIG = '/var/module-check/psalm.xml';
    private const PSALM_PLUGIN = 'Psalm\SymfonyPsalmPlugin\Plugin';

    public function __construct(private string $root, private string $package)
    {
    }

    public function failed(): ?string
    {
        foreach ([
            self::BUILD => fn (): bool => $this->passes([\PHP_BINARY, 'bin/console', 'lint:container']),
            self::AUDIT => $this->audited(...),
            self::PHPSTAN => fn (): bool => $this->passes([\PHP_BINARY, '-d', 'memory_limit=-1', 'vendor/bin/phpstan', 'analyse', '--no-progress', '--configuration='.__DIR__.'/module.neon', $this->source()]),
            self::PSALM => fn (): bool => $this->passes([\PHP_BINARY, '-d', 'memory_limit=-1', 'vendor/bin/psalm', '--taint-analysis', '--no-cache', '--no-progress', '--config='.$this->psalm(), '--root='.$this->root]),
            self::BOUNDARY => $this->bounded(...),
        ] + (CoreKind::Theme === $this->module()->kind ? [self::THEME => $this->complete(...)] : []) as $check => $passes) {
            fwrite(\STDOUT, \sprintf("== %s\n", $check));
            if (!$passes()) {
                return $check;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function added(): array
    {
        $installed = self::json(\dirname((string) (new \ReflectionClass(ClassLoader::class))->getFileName()).'/installed.json');
        $root = self::json($this->root.'/composer.json');
        $requires = [];
        $providers = [];
        foreach (\is_array($installed['packages'] ?? null) ? $installed['packages'] : [] as $package) {
            if (!\is_array($package) || !\is_string($package['name'] ?? null)) {
                continue;
            }
            $name = strtolower($package['name']);
            $requires[$name] = self::names($package['require'] ?? null);
            foreach ([...self::names($package['provide'] ?? null), ...self::names($package['replace'] ?? null)] as $virtual) {
                $providers[$virtual][] = $name;
            }
        }
        $others = array_values(array_diff([...self::names($root['require'] ?? null), ...self::names($root['require-dev'] ?? null)], [$this->package]));

        return array_values(array_diff(self::closure([$this->package], $requires, $providers), self::closure($others, $requires, $providers)));
    }

    private function audited(): bool
    {
        $composer = (new ExecutableFinder())->find('composer') ?? throw new \RuntimeException('composer is not in PATH');
        $process = new Process([\PHP_BINARY, $composer, 'audit', '--locked', '--format=json', '--abandoned=ignore', '--no-interaction'], $this->root, timeout: null);
        $process->run();
        $answer = json_decode($process->getOutput(), true);
        if (!\is_array($answer) || !\is_array($answer['advisories'] ?? null)) {
            fwrite(\STDERR, $process->getErrorOutput());

            return false;
        }

        $found = array_intersect_key($answer['advisories'], array_flip($this->added()));
        foreach ($found as $package => $advisories) {
            foreach (\is_array($advisories) ? $advisories : [] as $advisory) {
                fwrite(\STDOUT, \sprintf("%s: %s %s\n", $package, \is_array($advisory) ? $advisory['advisoryId'] ?? '' : '', \is_array($advisory) ? $advisory['title'] ?? '' : ''));
            }
        }

        return [] === $found;
    }

    private function bounded(): bool
    {
        $registry = Registry::installed();
        $listed = $_SERVER[self::DATABASES] ?? $_ENV[self::DATABASES] ?? null;
        $databases = null === $listed
            ? [(string) ($_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? '')]
            : (preg_split('~\s+~', (string) $listed, flags: \PREG_SPLIT_NO_EMPTY) ?: []);
        $crossed = (new ModuleBoundary($registry, $this->module()))->crossed($databases);
        foreach ($crossed as $crossing) {
            fwrite(\STDOUT, $crossing."\n");
        }

        return [] === $crossed;
    }

    private function complete(): bool
    {
        $missing = (new ThemeCompleteness(Registry::installed(), $this->module()))->missing();
        foreach ($missing as $absent) {
            fwrite(\STDOUT, $absent."\n");
        }

        return [] === $missing;
    }

    private function module(): Installed
    {
        return Registry::installed()->package($this->package) ?? throw new \LogicException(\sprintf('%s has no module bundle', $this->package));
    }

    /** @param list<string> $command */
    private function passes(array $command): bool
    {
        $process = new Process($command, $this->root, timeout: null);
        $process->run(static function (string $type, string $buffer): void {
            fwrite(Process::ERR === $type ? \STDERR : \STDOUT, $buffer);
        });

        return $process->isSuccessful();
    }

    private function source(): string
    {
        return (InstalledVersions::getInstallPath($this->package) ?? throw new \RuntimeException(\sprintf('%s is not installed', $this->package))).'/src';
    }

    private function psalm(): string
    {
        $config = new \DOMDocument('1.0');
        $psalm = $config->appendChild($config->createElement('psalm'));
        $psalm->setAttribute('errorLevel', '1');
        $psalm->setAttribute('resolveFromConfigFile', 'false');
        $psalm->setAttribute('findUnusedCode', 'false');
        $psalm->setAttribute('findUnusedBaselineEntry', 'false');
        $psalm->appendChild($config->createElement('projectFiles'))->appendChild($config->createElement('directory'))->setAttribute('name', $this->source());
        $psalm->appendChild($config->createElement('plugins'))->appendChild($config->createElement('pluginClass'))->setAttribute('class', self::PSALM_PLUGIN);
        (new Filesystem())->dumpFile($this->root.self::PSALM_CONFIG, (string) $config->saveXML());

        return $this->root.self::PSALM_CONFIG;
    }

    /**
     * @param list<string>                $names
     * @param array<string, list<string>> $requires
     * @param array<string, list<string>> $providers
     *
     * @return list<string>
     */
    private static function closure(array $names, array $requires, array $providers): array
    {
        $seen = [];
        while ([] !== $names) {
            $name = array_pop($names);
            foreach (isset($requires[$name]) ? [$name] : $providers[$name] ?? [] as $package) {
                if (!isset($seen[$package])) {
                    $seen[$package] = true;
                    array_push($names, ...$requires[$package] ?? []);
                }
            }
        }

        return array_keys($seen);
    }

    /** @return list<string> */
    private static function names(mixed $links): array
    {
        return \is_array($links) ? array_map(static fn (int|string $name): string => strtolower((string) $name), array_keys($links)) : [];
    }

    /** @return array<mixed> */
    private static function json(string $path): array
    {
        $json = json_decode((string) file_get_contents($path), true, flags: \JSON_THROW_ON_ERROR);

        return \is_array($json) ? $json : [];
    }
}
