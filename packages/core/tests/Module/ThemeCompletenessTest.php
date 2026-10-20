<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module;

use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Tests\ThemeCompleteness;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class ThemeCompletenessTest extends TestCase
{
    #[Group('ACMS-CORE-0955')]
    public function testThemeShortOfTheEngineThemeIsNamedByEachMissingPiece(): void
    {
        $registry = Registry::installed();
        $threadbare = $registry->package('adzercms/threadbare') ?? self::fail('the development application does not install the fixture theme');

        $missing = (new ThemeCompleteness($registry, $threadbare))->missing();

        self::assertContains('template _table.html.twig', $missing);
        self::assertNotContains('template base.html.twig', $missing, 'the theme has the template');
        self::assertContains('function amount', $missing);
        self::assertContains('filter links', $missing);
        self::assertContains('class fields', $missing);
        self::assertNotContains('class modal-body', $missing, 'the theme styles the class');
    }
}
