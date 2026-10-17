<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Schema;

use AdzerCMS\Core\Tests\Container;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ConnectionTest extends KernelTestCase
{
    use Container;

    #[Group('ACMS-CORE-0032')]
    public function testConnectionBrokenByIdleComesBackOutsideTransaction(): void
    {
        self::bootKernel();
        $connection = self::service(EntityManagerInterface::class)->getConnection();
        if (!$connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            self::markTestSkipped('reconnect after idle is a mechanism of MySQL and MariaDB connections');
        }

        $connection->executeStatement('SET SESSION wait_timeout = 1');
        sleep(2);

        self::assertEquals(1, $connection->fetchOne('SELECT 1'), 'a connection broken by the idle timeout of the server is reopened');
    }
}
