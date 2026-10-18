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
        $selection = [];
        foreach ($registry->kinds() as $kind) {
            $selection[$kind->environment()] = [];
        }
        foreach ($modules as $module) {
            self::add($module, $registry, $selection);
            foreach ($module->develops as $package) {
                $developed = $registry->package($package);
                if (null !== $developed) {
                    self::add($developed, $registry, $selection);
                }
            }
        }

        foreach ($selection as $name => $keys) {
            $_ENV[$name] = $_SERVER[$name] = implode(',', $keys);
        }
        $loaded = array_diff(explode(',', (string) ($_SERVER[self::DOTENV_VARS] ?? '')), array_keys($selection));
        $_ENV[self::DOTENV_VARS] = $_SERVER[self::DOTENV_VARS] = implode(',', $loaded);
    }

    private static function occupy(): void
    {
        $file = self::root().self::LOCK;
        (new Filesystem())->mkdir(\dirname($file));
        self::$lock = fopen($file, 'c') ?: null;
        if (null === self::$lock || !flock(self::$lock, \LOCK_EX)) {
            throw new \RuntimeException('test run did not take the database: it is shared by all suites');
        }
    }

    /** @param array<string, array<string, string>> $selection */
    private static function add(Installed $module, Registry $registry, array &$selection): void
    {
        if (isset($selection[$module->kind->environment()][$module->key])) {
            return;
        }
        $selection[$module->kind->environment()][$module->key] = $module->key;
        foreach ($module->requires as $package) {
            $required = $registry->package($package);
            if (null !== $required) {
                self::add($required, $registry, $selection);
            }
        }
        foreach ($registry->required($module) as $kind) {
            if ([] === $selection[$kind->environment()]) {
                self::add(self::provider($kind, $registry), $registry, $selection);
            }
        }
    }

    private static function provider(Kind $kind, Registry $registry): Installed
    {
        $installed = $registry->ofKind($kind);
        $selected = array_filter($installed, static fn (Installed $module): bool => \in_array(
            $module->key,
            array_map(trim(...), explode(',', (string) ($_SERVER[$kind->environment()] ?? ''))),
            true,
        ));

        return [...$selected, ...(1 === \count($installed) ? $installed : [])][0]
            ?? throw new \LogicException(sprintf('suite needs a module of %s: select one of installed in the instance environment, installed: %s', $kind->environment(), implode(', ', array_map(static fn (Installed $module): string => $module->key, $installed)) ?: 'none'));
    }
}
