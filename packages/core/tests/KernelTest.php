<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use AdzerCMS\Core\Build\Builds;
use AdzerCMS\Core\Kernel;
use AdzerCMS\Core\Module\Installed;
use AdzerCMS\Core\Module\Registry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\AssetMapper\Path\PublicAssetsPathResolverInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouterInterface;

final class KernelTest extends KernelTestCase
{
    use Container;

    #[Group('ACMS-CORE-0018')]
    #[DataProvider('moduleSets')]
    public function testCacheContainerAndStaticCarryModuleSet(?string $module, string $cache, string $container, string $static): void
    {
        [$server, $env] = [$_SERVER, $_ENV];
        $registry = Registry::installed();
        ModuleSuite::select($registry, array_filter([null === $module ? null : $registry->find($module)]));

        try {
            $kernel = self::bootKernel();
            $id = $kernel instanceof Kernel ? $kernel->modules()->id() : self::fail('the kernel of the development application is not the engine kernel');

            self::assertSame(\sprintf($cache, $id), basename($kernel->getCacheDir()));
            self::assertSame(\sprintf($container, $id), (new \ReflectionClass($kernel->getContainer()))->getShortName());
            self::assertSame(\sprintf($static, $id), self::service(PublicAssetsPathResolverInterface::class, 'asset_mapper.public_assets_path_resolver')->resolvePublicPath(''));
        } finally {
            self::ensureKernelShutdown();
            [$_SERVER, $_ENV] = [$server, $env];
        }
    }

    /** @return iterable<string, array{?string, string, string, string}> */
    public static function moduleSets(): iterable
    {
        yield 'without modules' => [null, 'test', 'App_KernelTestDebugContainer', '/assets/'];
        yield 'with modules' => ['Theme', 'test_%s', 'App_KernelTestDebugContainer_%s', '/assets/%s/'];
    }

    #[Group('ACMS-CORE-0045')]
    public function testKernelWithoutDebugRunsOnBuildOfPointerAndCommandSwitchesInstance(): void
    {
        $cache = sys_get_temp_dir().'/adzercms-kernel-'.bin2hex(random_bytes(4));
        $_SERVER['APP_CACHE_DIR'] = $cache;
        $builds = new Builds($cache.'/test');

        try {
            $builds->switchTo('b1');
            $running = self::bootKernel(['debug' => false]);
            self::assertSame($cache.'/test/b1', $running->getCacheDir());

            $_SERVER[Builds::ENV] = 'b2';
            $refresh = new CommandTester((new Application(self::createKernel(['debug' => false])))->find('adzercms:cache'));
            $refresh->execute([]);

            self::assertSame(Command::SUCCESS, $refresh->getStatusCode());
            self::assertSame('b2', $builds->current());
            self::assertDirectoryExists($cache.'/test/b1');

            self::ensureKernelShutdown();

            self::assertSame(['b1'], $builds->prune());
        } finally {
            unset($_SERVER['APP_CACHE_DIR'], $_SERVER[Builds::ENV]);
            (new Filesystem())->remove($cache);
        }
    }

    #[Group('ACMS-CORE-0011')]
    #[Group('ACMS-CORE-0012')]
    public function testUnselectedModuleLeavesNoTraceInInstance(): void
    {
        $kernel = self::bootKernel();
        $selected = $kernel instanceof Kernel
            ? array_map(static fn (Installed $module): string => $module->key, $kernel->modules()->modules)
            : self::fail('the kernel of the development application is not the engine kernel');
        $registry = Registry::installed();
        $foreign = [];
        foreach ($registry->kinds() as $kind) {
            foreach ($registry->ofKind($kind) as $module) {
                if (!\in_array($module->key, $selected, true)) {
                    $foreign[] = (new \ReflectionClass($module->bundle))->getNamespaceName().'\\';
                }
            }
        }
        self::assertNotSame([], $foreign, 'the development application installs modules outside the selection');

        $of = static fn (mixed $name): bool => \is_string($name) && [] !== array_filter($foreign, static fn (string $namespace): bool => str_starts_with($name, $namespace));
        $traces = array_map(static fn (string $id): string => 'service '.$id, array_filter(self::getContainer()->getServiceIds(), $of));
        foreach ((new Application($kernel))->all() as $name => $command) {
            if ($of(($command instanceof LazyCommand ? $command->getCommand() : $command)::class)) {
                $traces[] = 'command '.$name;
            }
        }
        foreach (self::service(RouterInterface::class, 'router')->getRouteCollection() as $name => $route) {
            if ($of($route->getDefault('_controller'))) {
                $traces[] = 'route '.$name;
            }
        }
        foreach (self::service(EntityManagerInterface::class)->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($of($metadata->getName())) {
                $traces[] = 'entity '.$metadata->getName();
            }
        }

        self::assertSame([], array_values($traces), 'an unselected module is built into the instance');
    }

    #[Group('ACMS-CORE-0003')]
    public function testUnknownModuleOfInstanceStopsKernelBoot(): void
    {
        $_SERVER['MODULES'] = $_ENV['MODULES'] = 'Nope';

        try {
            self::bootKernel();
            self::fail('kernel booted with an unknown module');
        } catch (\LogicException $refusal) {
            self::assertStringStartsWith('module "Nope" in MODULES is not installed, available:', $refusal->getMessage());
        } finally {
            unset($_SERVER['MODULES'], $_ENV['MODULES']);
        }
    }
}
