<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;
use Composer\InstalledVersions;

final class Registry
{
    public const TYPE = 'adzercms-module';
    private const IMPLEMENTATION = '~^adzercms/([a-z0-9-]+)-implementation$~';

    /** @var array<string, Installed> */
    private array $byPackage = [];

    /** @param array<string, Installed> $modules */
    private function __construct(private readonly array $modules)
    {
        foreach ($modules as $module) {
            $this->byPackage[$module->package] = $module;
        }
    }

    public static function installed(): self
    {
        $manifests = [];
        foreach (array_unique(InstalledVersions::getInstalledPackagesByType(self::TYPE)) as $package) {
            $path = InstalledVersions::getInstallPath($package)
                ?? throw new \LogicException(sprintf('module package %s has no install path', $package));
            $manifests[$package] = json_decode((string) file_get_contents($path.'/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
        }

        return self::of($manifests);
    }

    /** @param array<string, mixed> $manifests */
    public static function of(array $manifests): self
    {
        $modules = [];
        foreach ($manifests as $package => $manifest) {
            $module = self::module($package, $manifest, array_keys($manifests));
            $taken = $modules[$module->key] ?? null;
            if (null !== $taken) {
                throw new \LogicException(sprintf('module key "%s" is taken by packages %s and %s', $module->key, $taken->package, $package));
            }
            $modules[$module->key] = $module;
        }

        return new self($modules);
    }

    public function find(string $key): ?Installed
    {
        return $this->modules[$key] ?? null;
    }

    public function package(string $package): ?Installed
    {
        return $this->byPackage[$package] ?? null;
    }

    /** @return list<Installed> */
    public function ofKind(Kind $kind): array
    {
        return array_values(array_filter($this->modules, static fn (Installed $module): bool => $module->kind === $kind));
    }

    /** @return list<Kind> */
    public function required(Installed $module): array
    {
        $kinds = [];
        foreach ($this->kinds() as $kind) {
            $kinds[self::implementation($kind)] = $kind;
        }

        return array_map(
            static fn (string $implementation): Kind => $kinds[$implementation] ?? throw new \LogicException(sprintf('module "%s" requires %s, no installed module declares its kind', $module->key, $implementation)),
            $module->implementations,
        );
    }

    public static function implementation(Kind $kind): string
    {
        return \sprintf('adzercms/%s-implementation', $kind->key());
    }

    /** @return list<Kind> */
    public function kinds(): array
    {
        $kinds = [];
        foreach ([CoreKind::cases(), ...array_column($this->modules, 'kinds')] as $declared) {
            foreach ($declared as $kind) {
                $taken = $kinds[$kind->environment()] ?? null;
                if (null !== $taken && $taken !== $kind) {
                    throw new \LogicException(sprintf('environment key %s is declared by two kinds', $kind->environment()));
                }
                $kinds[$kind->environment()] = $kind;
            }
        }

        return array_values($kinds);
    }

    /** @param list<string> $packages */
    private static function module(string $package, mixed $manifest, array $packages): Installed
    {
        $bundle = \is_array($manifest) ? ($manifest['extra']['adzercms']['bundle'] ?? null) : null;

        if (!\is_string($bundle) || !is_subclass_of($bundle, Module::class)) {
            throw new \LogicException(sprintf('module package %s does not name a bundle class extending %s in extra.adzercms.bundle', $package, Module::class));
        }

        $name = (new \ReflectionClass($bundle))->getShortName();
        if ('Bundle' === $name || !str_ends_with($name, 'Bundle')) {
            throw new \LogicException(sprintf('bundle name of module package %s is the module key ending with Bundle', $package));
        }

        $kind = $bundle::kind();
        $provided = \is_array($manifest['provide'] ?? null) ? $manifest['provide'] : [];
        if (CoreKind::Feature !== $kind && !\array_key_exists(self::implementation($kind), $provided)) {
            throw new \LogicException(sprintf('module package %s does not provide %s of its kind', $package, self::implementation($kind)));
        }

        $required = \is_array($manifest['require'] ?? null) ? array_map(strval(...), array_keys($manifest['require'])) : [];
        $developed = \is_array($manifest['require-dev'] ?? null) ? array_map(strval(...), array_keys($manifest['require-dev'])) : [];

        return new Installed(
            $package,
            self::key($bundle),
            $bundle,
            array_values(array_intersect($packages, $required)),
            array_values(array_filter($required, static fn (string $name): bool => 1 === preg_match(self::IMPLEMENTATION, $name))),
            array_values(array_intersect($packages, $developed)),
        );
    }

    /** @param class-string $bundle */
    public static function key(string $bundle): string
    {
        return substr((new \ReflectionClass($bundle))->getShortName(), 0, -\strlen('Bundle'));
    }

    /** @param class-string<Module> $bundle */
    public static function settings(string $bundle): string
    {
        return \sprintf('%s.%s.', $bundle::kind()->key(), strtolower(self::key($bundle)));
    }
}
