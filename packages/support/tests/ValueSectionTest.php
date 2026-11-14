<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Tests;

use AdzerCMS\Core\Text\ValueSections;
use PHPUnit\Framework\Attributes\Group;

final class ValueSectionTest extends SupportTestCase
{
    #[Group('ACMS-SUP-0022')]
    public function testSectionLeadsToTicketsAndNewTicket(): void
    {
        self::assertSame(
            ['tickets' => 'http://localhost/support', 'new_ticket' => 'http://localhost/support/new'],
            self::service(ValueSections::class)->section('support'),
        );
    }
}
