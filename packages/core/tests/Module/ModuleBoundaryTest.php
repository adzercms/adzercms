<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module;

use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Tests\ModuleBoundary;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;

final class ModuleBoundaryTest extends SchemaTestCase
{
    #[Group('ACMS-CORE-0954')]
    public function testModuleCrossingTheBoundaryIsNamedByEachCrossing(): void
    {
        $registry = Registry::installed();
        $trespass = $registry->package('adzercms/trespass') ?? self::fail('the development application does not install the fixture module');

        try {
            $crossed = (new ModuleBoundary($registry, $trespass))->crossed([(string) ($_SERVER['DATABASE_URL'] ?? '')]);
        } finally {
            self::remigrate();
        }

        self::assertSame([
            'service AdzerCMS\Core\Module\Marks',
            'route robots',
            'route trespass_sitemap',
            'command adzercms:sweep',
            'message en messages core.action.close',
            'table visit',
        ], array_map(static fn (string $crossing): string => strstr($crossing, ': ', true) ?: $crossing, $crossed), 'the own table of the module stays within the boundary');
    }

    #[Group('ACMS-CORE-0954')]
    public function testModuleRequiredAsKindMigratesOverTheTablesOfModulesRequiringTheKind(): void
    {
        $registry = Registry::installed();
        $underlay = $registry->package('adzercms/underlay') ?? self::fail('the development application does not install the fixture theme');

        try {
            $crossed = (new ModuleBoundary($registry, $underlay))->crossed([(string) ($_SERVER['DATABASE_URL'] ?? '')]);
        } finally {
            self::remigrate();
        }

        self::assertSame([], $crossed, 'the own table of the theme refers to a table of the module dropped without a theme');
    }
}
