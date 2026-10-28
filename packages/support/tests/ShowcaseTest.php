<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Tests;

use PHPUnit\Framework\Attributes\Group;

final class ShowcaseTest extends SupportTestCase
{
    #[Group('ACMS-SUP-0022')]
    public function testSectionLeadsToTicketsAndNewTicket(): void
    {
        $command = self::command('adzercms:showcase');
        $command->execute([]);

        self::assertSame(
            ['tickets' => 'http://localhost/support', 'new_ticket' => 'http://localhost/support/new'],
            json_decode($command->getDisplay(), true, flags: \JSON_THROW_ON_ERROR)['support'],
        );
    }
}
