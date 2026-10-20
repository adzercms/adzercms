<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Core\Module\Installed;
use AdzerCMS\Core\Module\Registry;
use Composer\InstalledVersions;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

final class ModuleSuite implements Extension
{
    private const DOTENV_VARS = 'SYMFONY_DOTENV_VARS';
    private const LOCK = '/var/tests.lock';

    /** @var resource|null */
    private static $lock;

    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        self::environment('test');
        self::occupy();

        $registry = Registry::installed();
        $suites = $configuration->includeTestSuites();
        if ([] === $suites && $configuration->hasDefaultTestSuite()) {
            $suites = [$configuration->defaultTestSuite()];
        }

        self::select($registry, array_values(array_filter(array_map($registry->find(...), $suites))));
    }

    public static function root(): string
    {
        foreach (InstalledVersions::getAllRawData() as $installed) {
            $root = realpath($installed['root']['install_path']);
            if (false !== $root) {
                return $root;
            }
        }

        throw new \LogicException('root package of the instance has no install path');
    }

    public static function environment(string $env): void
    {
        $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $env;
        (new Dotenv())->bootEnv(self::root().'/.env');
    }

    /** @param list<Installed> $modules */
    public static function select(Registry $registry, array $modules): void
    {
        self::choose($registry, self::taken($registry, $modules));
    }

    /**
     * @param list<Installed> $modules
     *
     * @return list<Installed>
     */
    public static function taken(Registry $registry, array $modules): array
    {
        $selection = [];
        foreach ($registry->kinds() as $kind) {
            $selection[$kind->environment()] = [];
        }
        $taken = [];
        foreach ($modules as $module) {
            self::take($module, $registry, $selection, $taken);
            foreach ($module->develops as $package) {
                $developed = $registry->package($package);
                if (null !== $developed) {
                    self::take($developed, $registry, $selection, $taken);
                }
            }
        }
        for ($index = 0; $index < \count($taken); ++$index) {
            foreach ($registry->required($taken[$index]) as $kind) {
                if ([] === $selection[$kind->environment()]) {
                    self::take(self::provider($kind, $taken[$index], $registry), $registry, $selection, $taken);
                }
            }
        }

        return $taken;
    }

    /** @param list<Installed> $modules */
    public static function choose(Registry $registry, array $modules): void
    {
        $selection = [];
        foreach ($registry->kinds() as $kind) {
            $selection[$kind->environment()] = [];
        }
        foreach ($modules as $module) {
            $selection[$module->kind->environment()][] = $module->key;
        }

        foreach ($selection as $name => $keys) {
            $_ENV[$name] = $_SERVER[$name] = implode(',', $keys);
        }
        $loaded = array_diff(explode(',', (string) ($_SERVER[self::DOTENV_VARS] ?? '')), array_keys($selection));
        $_ENV[self::DOTENV_VARS] = $_SERVER[self::DOTENV_VARS] = implode(',', $loaded);
    }

    public static function occupy(): void
    {
        if (null !== self::$lock) {
            return;
        }
        $file = self::root().self::LOCK;
        (new Filesystem())->mkdir(\dirname($file));
        $lock = fopen($file, 'c');
        if (false === $lock || !flock($lock, \LOCK_EX)) {
            throw new \RuntimeException('test run did not take the database: it is shared by all suites');
        }
        self::$lock = $lock;
    }

    /**
     * @param array<string, array<string, string>> $selection
     * @param list<Installed>                      $taken
     */
    private static function take(Installed $module, Registry $registry, array &$selection, array &$taken): void
    {
        if (isset($selection[$module->kind->environment()][$module->key])) {
            return;
        }
        $selection[$module->kind->environment()][$module->key] = $module->key;
        $taken[] = $module;
        foreach ($module->requires as $package) {
            $required = $registry->package($package);
            if (null !== $required) {
                self::take($required, $registry, $selection, $taken);
            }
        }
    }

    private static function provider(Kind $kind, Installed $requiring, Registry $registry): Installed
    {
        $installed = $registry->ofKind($kind);
        $chosen = array_map(trim(...), explode(',', (string) ($_SERVER[$kind->environment()] ?? '')));

        return [
            ...array_filter($installed, static fn (Installed $module): bool => \in_array($module->key, $chosen, true)),
            ...array_filter($installed, static fn (Installed $module): bool => \in_array($module->package, $requiring->develops, true)),
            ...(1 === \count($installed) ? $installed : []),
        ][0]
            ?? throw new \LogicException(sprintf('suite needs a module of %s: select one of installed in the instance environment, installed: %s', $kind->environment(), implode(', ', array_map(static fn (Installed $module): string => $module->key, $installed)) ?: 'none'));
    }
}
