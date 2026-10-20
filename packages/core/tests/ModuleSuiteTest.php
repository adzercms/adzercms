<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Tests\Module\Fixture;
use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\DataProvider;
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
    #[Group('ACMS-TEST-0011')]
    #[DataProvider('suites')]
    public function testSuiteModulesCoverKindOfTheirRequirements(string $suite, string $chosen, string $features): void
    {
        $registry = Registry::of([
            'acme/shop' => Fixture\Manifest::of(Fixture\ShopBundle::class),
            'acme/card' => Fixture\Manifest::of(Fixture\CardBundle::class, 'acme/shop', 'acme/till'),
            'acme/cash' => Fixture\Manifest::of(Fixture\CashBundle::class, 'acme/shop'),
            'acme/till' => ['require-dev' => ['acme/card' => '*']]
                + Fixture\Manifest::of(Fixture\TillBundle::class, 'adzercms/gateway-implementation'),
        ]);
        [$server, $env] = [$_SERVER, $_ENV];
        $_SERVER['TEST_GATEWAY'] = $chosen;

        try {
            ModuleSuite::select($registry, array_filter([$registry->find($suite)]));
            self::assertSame(['Card', $features], [$_SERVER['TEST_GATEWAY'], $_SERVER['MODULES']]);
        } finally {
            [$_SERVER, $_ENV] = [$server, $env];
        }
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function suites(): iterable
    {
        yield 'suite module of the kind against the instance choice' => ['Card', 'Cash', 'Shop,Till'];
        yield 'development module of the kind among several installed without a choice' => ['Till', '', 'Till,Shop'];
    }
}
