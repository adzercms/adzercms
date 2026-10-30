<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Tests;

use AdzerCMS\Contracts\Ui\AdminEntry;
use AdzerCMS\Core\Tests\Container;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

final class EntriesTest extends KernelTestCase
{
    use Container;

    #[Group('ACMS-UI-0068')]
    public function testEveryAdminEntryNamesRouteOfAdminModule(): void
    {
        $routes = self::service(RouterInterface::class, 'router')->getRouteCollection();

        foreach (AdminEntry::cases() as $entry) {
            self::assertStringStartsWith('AdzerCMS\\Admin\\', (string) $routes->get($entry->route())?->getDefault('_controller'), $entry->value);
        }
    }
}
