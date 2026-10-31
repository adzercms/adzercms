<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Account;

use AdzerCMS\Core\Account\Roles;
use AdzerCMS\Core\Module\Bundles;
use AdzerCMS\Core\Tests\Account\Fixture\Desk\DeskBundle;
use AdzerCMS\Core\Tests\Account\Fixture\Desk\DeskMenu;
use AdzerCMS\Core\Tests\Account\Fixture\Lobby\LobbyBundle;
use AdzerCMS\Core\Tests\Account\Fixture\Lobby\LobbyMenu;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class RolesTest extends TestCase
{
    /** @return iterable<string, array{bool}> */
    public static function sections(): iterable
    {
        yield 'a cabinet section of a feature module' => [true];
        yield 'admin sections of a feature module and the own items of the cabinet' => [false];
    }

    #[Group('ACMS-CORE-1029')]
    #[DataProvider('sections')]
    public function testRoleIsChosenOnlyWithCabinetSectionOfFeatureModule(bool $cabinet): void
    {
        $roles = new Roles([new LobbyMenu(), new DeskMenu($cabinet)], new Bundles(['LobbyBundle' => LobbyBundle::class, 'DeskBundle' => DeskBundle::class]));

        self::assertSame($cabinet, $roles->chosen());
    }
}
