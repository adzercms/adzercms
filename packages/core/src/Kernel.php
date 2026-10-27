<?php

declare(strict_types=1);

namespace AdzerCMS\Core;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Core\Build\Builds;
use AdzerCMS\Core\Build\HeldBuild;
use AdzerCMS\Core\Build\Preview;
use AdzerCMS\Core\Module\Installed;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Module\Selection;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function Symfony\Component\Clock\now;

abstract class Kernel extends BaseKernel
{
    use MicroKernelTrait {
        getCacheDir as private configuredCacheDir;
        registerBundles as private configuredBundles;
        configureRoutes as private configuredRoutes;
    }

    public const ASSETS = 'adzercms.assets';

    private const ASSETS_ROOT = '/assets/';
    private const PUBLIC = '/public';
    private const SELECTION = '.selection';
    private const PREVIEW = '.preview';
    private const SECRET = 'APP_SECRET';
    private const DOTENV = 'SYMFONY_DOTENV_VARS';

    private ?Selection $modules = null;
    private ?HeldBuild $held = null;
    private ?string $cacheDir = null;
    private ?string $previewed = null;

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
    public function currentBuilds(): array
    {
        return array_map(static fn (string $root): Builds => new Builds($root), $this->currentRoots());
    }

    /** @return list<Builds> */
    public function otherBuilds(): array
    {
        return array_map(
            static fn (string $root): Builds => new Builds($root),
            array_values(array_diff(Builds::roots($this->configuredCacheDir()), $this->currentRoots())),
        );
    }

    /** @return list<string> */
    public function strayAssets(): array
    {
        return Builds::strays($this->configuredCacheDir(), $this->getProjectDir().self::PUBLIC.rtrim(self::ASSETS_ROOT, '/'));
    }

    public function preview(): Preview
    {
        return new Preview($this->configuredCacheDir().self::PREVIEW);
    }

    public function previewed(): ?string
    {
        $this->modules();

        return $this->previewed;
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
        return $this->modules ??= Selection::of(Registry::installed(), $this->selection());
    }

    public function keepSelection(): void
    {
        (new Filesystem())->dumpFile($this->selectionFile(), json_encode($this->selected(), \JSON_THROW_ON_ERROR));
    }

    public function keepPreview(): void
    {
        $theme = array_find($this->modules()->modules, static fn (Installed $module): bool => CoreKind::Theme === $module->kind)
            ?? throw new \LogicException('a preview takes a selected theme');

        $this->preview()->keep($theme->key, $this->selected());
    }

    /** @return array<string, string> */
    private function selected(): array
    {
        $values = [];
        foreach (Registry::installed()->kinds() as $kind) {
            $values[$kind->environment()] = Selection::environment($kind->environment());
        }

        return $values;
    }

    /** @return \Closure(string): string */
    private function selection(): \Closure
    {
        $file = $this->selectionFile();

        if ($this->debug || null !== Builds::fixed()) {
            return Selection::environment(...);
        }

        $values = $this->previewing() ?? (is_file($file) ? json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR) : null);
        $dotenv = explode(',', (string) ($_SERVER[self::DOTENV] ?? $_ENV[self::DOTENV] ?? ''));

        return null === $values
            ? Selection::environment(...)
            : static fn (string $name): string => match (true) {
                !\in_array($name, $dotenv, true) && null !== ($_SERVER[$name] ?? $_ENV[$name] ?? null) => Selection::environment($name),
                \is_array($values) && \is_string($values[$name] ?? null) => $values[$name],
                default => '',
            };
    }

    /** @return array<string, string>|null */
    private function previewing(): ?array
    {
        $mark = $_COOKIE[Preview::COOKIE] ?? null;
        $theme = \is_string($mark) ? Preview::marked($mark, now()->getTimestamp(), Selection::environment(self::SECRET)) : null;
        $kept = null === $theme ? null : $this->preview()->read();
        if (null === $kept || $theme !== $kept['theme']) {
            return null;
        }
        $this->previewed = $theme;

        return $kept['values'];
    }

    /** @return list<string> */
    private function currentRoots(): array
    {
        $kept = $this->preview()->read();
        try {
            $preview = null === $kept ? null : Selection::of(Registry::installed(), static fn (string $name): string => $kept['values'][$name] ?? '')->id();
        } catch (\LogicException) {
            $preview = null;
        }

        return [$this->buildsRoot(), ...null === $preview ? [] : [Builds::root($this->configuredCacheDir(), $preview)]];
    }

    private function selectionFile(): string
    {
        return $this->configuredCacheDir().self::SELECTION;
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
        return Builds::root($this->configuredCacheDir(), $this->modules()->id());
    }

    private function mark(string $before, string $after = ''): string
    {
        $id = $this->modules()->id();

        return '' === $id ? '' : $before.$id.$after;
    }
}
