<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Ui;

use AdzerCMS\Core\Tests\Container;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class EntriesTest extends KernelTestCase
{
    use Container;

    #[Group('ACMS-UI-0068')]
    public function testAdminEntryWithoutAdminModuleGivesNoAddress(): void
    {
        $page = self::service(Environment::class, 'twig')->createTemplate("{{ admin('user', {id: 1}) is null ? 'none' : 'link' }}")->render();

        self::assertSame('none', $page);
    }
}
