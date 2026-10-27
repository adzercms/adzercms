<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module;

use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Tests\KindRoutes;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class KindRoutesTest extends TestCase
{
    #[Group('ACMS-TEST-0105')]
    public function testRouteOfKindModuleOutsideItAndEntriesIsRefused(): void
    {
        $registry = Registry::installed();
        $files = new Filesystem();
        $package = $files->tempnam(sys_get_temp_dir(), 'adzercms-routes-');
        $files->remove($package);
        $files->dumpFile($package.'/composer.json', '{"name": "adzercms/probe"}');
        $files->dumpFile($package.'/src/Probe.php', "<?php\n\nreturn ['admin_user', 'probe_own'];\n");
        $files->dumpFile($package.'/templates/probe.html.twig', "<a href=\"{{ path('cabinet_register') }}\">admin_user</a>\n");

        try {
            $crossed = (new KindRoutes($registry))->crossed($package);
        } finally {
            $files->remove($package);
        }

        self::assertSame([
            $package.'/src/Probe.php:3: route admin_user of kind module adzercms/admin goes through an entry of the interface contract',
            $package.'/templates/probe.html.twig:1: route cabinet_register of kind module adzercms/cabinet goes through an entry of the interface contract',
        ], $crossed);
        self::assertSame([], (new KindRoutes($registry))->crossed(Registry::path('adzercms/admin')), 'routes of the kind module in its own package');
        self::assertSame([], (new KindRoutes($registry))->crossed(Registry::path('adzercms/contracts')), 'routes of the entries declaration');
    }
}
