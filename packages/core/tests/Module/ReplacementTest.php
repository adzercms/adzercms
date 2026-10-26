<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Core\Composer\Description;
use AdzerCMS\Core\Composer\Package;
use AdzerCMS\Core\Module\CatalogRow;
use AdzerCMS\Core\Module\Operations;
use AdzerCMS\Core\Module\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class ReplacementTest extends TestCase
{
    #[Group('ACMS-CORE-0936')]
    #[DataProvider('selections')]
    public function testEnablingSingleKindModuleNamesSelectedOneOfItsKind(CoreKind $kind, bool $selected, ?string $replaced): void
    {
        $rows = [self::row('acme/old', $kind, $selected), self::row('acme/other', CoreKind::Admin, true), self::row('acme/new', $kind, false)];

        self::assertSame($replaced, Operations::replaced($rows[2], $rows));
    }

    /** @return iterable<string, array{CoreKind, bool, ?string}> */
    public static function selections(): iterable
    {
        yield 'single kind with a selected module' => [CoreKind::Theme, true, 'acme/old'];
        yield 'single kind without a selected module' => [CoreKind::Theme, false, null];
        yield 'kind of a list' => [CoreKind::Sms, true, null];
    }

    private static function row(string $name, CoreKind $kind, bool $selected): CatalogRow
    {
        return new CatalogRow($name, new Package(new Description('', []), '1.0.0', [], null, null, Registry::TYPE, null, null), $kind, '1.0.0', '1.0.0', null, $selected, false, false, false, null);
    }
}
