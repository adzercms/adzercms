<?php

declare(strict_types=1);

namespace Vendor\Module\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ModuleTest extends KernelTestCase
{
    public function testSuiteEnablesModule(): void
    {
        self::assertArrayHasKey('ModuleBundle', self::bootKernel()->getBundles());
    }
}
