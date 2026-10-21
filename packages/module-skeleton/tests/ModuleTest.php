<?php

declare(strict_types=1);

namespace Vendor\Module\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ModuleTest extends KernelTestCase
{
    public function testSuiteEnablesModuleWithItsCatalog(): void
    {
        $translator = self::getContainer()->get('translator');

        self::assertInstanceOf(TranslatorInterface::class, $translator);
        self::assertSame('Module', $translator->trans('module.description', locale: 'en'));
    }
}
