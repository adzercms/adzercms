<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Tests\Module\Fixture;
use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class ModuleSuiteTest extends TestCase
{
    #[Group('ACMS-TEST-0074')]
    public function testRootSkipsComposerDataOfAnotherRuntimeLoadedFirst(): void
    {
        $root = ModuleSuite::root();
        $vendor = sys_get_temp_dir().'/'.uniqid('acms-phar-', true);
        $filesystem = new Filesystem();
        $filesystem->dumpFile($vendor.'/composer/installed.php', "<?php return ['root' => ['install_path' => 'phar:///missing.phar/'], 'versions' => []];");
        $loader = new ClassLoader($vendor);
        $loader->register(true);

        try {
            self::assertSame($root, ModuleSuite::root());
        } finally {
            $loader->unregister();
            $filesystem->remove($vendor);
        }
    }

    #[Group('ACMS-TEST-0009')]
    public function testSuiteModuleCoversKindOfItsRequirements(): void
    {
        $registry = Registry::of([
            'acme/shop' => Fixture\Manifest::of(Fixture\ShopBundle::class),
            'acme/card' => Fixture\Manifest::of(Fixture\CardBundle::class, 'acme/shop', 'acme/till'),
            'acme/cash' => Fixture\Manifest::of(Fixture\CashBundle::class, 'acme/shop'),
            'acme/till' => Fixture\Manifest::of(Fixture\TillBundle::class, 'adzercms/gateway-implementation'),
        ]);
        [$server, $env] = [$_SERVER, $_ENV];
        $_SERVER['TEST_GATEWAY'] = 'Cash';

        try {
            ModuleSuite::select($registry, array_filter([$registry->find('Card')]));
            self::assertSame(['Card', 'Shop,Till'], [$_SERVER['TEST_GATEWAY'], $_SERVER['MODULES']]);
        } finally {
            [$_SERVER, $_ENV] = [$server, $env];
        }
    }
}
