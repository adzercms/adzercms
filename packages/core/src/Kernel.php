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

    public function shutdown(): void
    {
        parent::shutdown();
        $this->held?->release();
        $this->held = null;
        $this->cacheDir = null;
    }

    public function modules(): Selection
    {
        return $this->modules ??= Selection::of(
            Registry::installed(),
            static fn (string $name): string => (string) ($_SERVER[$name] ?? $_ENV[$name] ?? ''),
        );
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
        return parent::getContainerClass().$this->suffix();
    }

    private function buildsRoot(): string
    {
        return $this->configuredCacheDir().$this->suffix();
    }

    private function suffix(): string
    {
        $id = $this->modules()->id();

        return '' === $id ? '' : '_'.$id;
    }
}
