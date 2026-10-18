<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

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
}
