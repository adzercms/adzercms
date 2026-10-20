<?php

declare(strict_types=1);

namespace AdzerCMS\Core;

use AdzerCMS\Core\Build\Builds;
use AdzerCMS\Core\Build\HeldBuild;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Module\Selection;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

abstract class Kernel extends BaseKernel
{
    use MicroKernelTrait {
        getCacheDir as private configuredCacheDir;
        registerBundles as private configuredBundles;
        configureRoutes as private configuredRoutes;
    }

    public const ASSETS = 'adzercms.assets';

    private const ASSETS_ROOT = '/assets/';

    private ?Selection $modules = null;
    private ?HeldBuild $held = null;
    private ?string $cacheDir = null;

    public function registerBundles(): iterable
    {
        yield from $this->configuredBundles();

        foreach ($this->modules()->modules as $module) {
            yield new ($module->bundle)();
        }
    }

    public function getCacheDir(): string
    {
        if (null === $this->cacheDir) {
            $this->held = $this->debug ? null : $this->builds()->hold(Builds::fixed());
            $this->cacheDir = null === $this->held ? $this->buildsRoot() : $this->buildsRoot().'/'.$this->held->name;
        }

        return $this->cacheDir;
    }

    public function cacheBuild(): ?string
    {
        $this->getCacheDir();

        return $this->held?->name;
    }

    public function builds(): Builds
    {
        return new Builds($this->buildsRoot());
    }

    /** @return list<Builds> */
    public function otherBuilds(): array
    {
        $roots = array_filter(
            [$this->configuredCacheDir(), ...preg_grep('~_[0-9a-f]{16}$~', glob($this->configuredCacheDir().'_*', \GLOB_ONLYDIR) ?: []) ?: []],
            fn (string $root): bool => $root !== $this->buildsRoot() && is_dir($root),
        );

        return array_values(array_map(static fn (string $root): Builds => new Builds($root), $roots));
    }

    public function shutdown(): void
    {
        parent::shutdown();
        $this->held?->release();
        $this->held = null;
        $this->cacheDir = null;
    }

    public function modules(): Selection
    {
        return $this->modules ??= Selection::of(Registry::installed(), Selection::environment(...));
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $this->configuredRoutes($routes);
        $routes->import('security.route_loader.logout', 'service');

        foreach ($this->getBundles() as $bundle) {
            if (CoreBundle::engine($bundle::class) && is_dir($bundle->getPath().'/src/Controller')) {
                $routes->import($bundle->getPath().'/src/Controller', 'attribute');
            }
        }
    }

    protected function getContainerClass(): string
    {
        return parent::getContainerClass().$this->mark('_');
    }

    /** @return array<string, array<mixed>|bool|string|int|float|\UnitEnum|null> */
    protected function getKernelParameters(): array
    {
        return parent::getKernelParameters() + [self::ASSETS => self::ASSETS_ROOT.$this->mark('', '/')];
    }

    private function buildsRoot(): string
    {
        return $this->configuredCacheDir().$this->mark('_');
    }

    private function mark(string $before, string $after = ''): string
    {
        $id = $this->modules()->id();

        return '' === $id ? '' : $before.$id.$after;
    }
}
