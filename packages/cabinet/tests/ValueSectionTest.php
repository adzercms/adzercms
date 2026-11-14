<?php

declare(strict_types=1);

namespace AdzerCMS\Cabinet\Tests;

use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Text\ValueSections;
use PHPUnit\Framework\Attributes\Group;

final class ValueSectionTest extends SchemaTestCase
{
    #[Group('ACMS-CORE-1060')]
    public function testCoreSectionLeadsToCabinetEntries(): void
    {
        self::assertSame(
            ['home' => 'http://localhost/cabinet', 'register' => 'http://localhost/register', 'forgot' => 'http://localhost/forgot', 'profile' => 'http://localhost/profile'],
            self::service(ValueSections::class)->section(ValueSections::CORE),
        );
    }
}
