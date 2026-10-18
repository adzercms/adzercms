<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Account;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Account\Status;
use AdzerCMS\Core\Repository\UserRepository;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\SchemaTestCase;

final class AdminsTest extends SchemaTestCase
{
    use Accounts;

    public function testAdminsAreAdministratorsOutsideTheArchive(): void
    {
        $admin = $this->record('79990000001')->setRole(Role::Admin);
        $this->record('79990000002')->setRole(Role::Admin)->setStatus(Status::Archived);
        $this->record('79990000003');
        $this->em->flush();

        self::assertSame([$admin], self::service(UserRepository::class)->admins());
    }
}
