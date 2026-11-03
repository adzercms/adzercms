<?php

declare(strict_types=1);

namespace AdzerCMS\Cabinet\Tests;

use AdzerCMS\Core\Command\ShowcaseCommand;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;

final class ShowcaseTest extends SchemaTestCase
{
    #[Group('ACMS-CORE-1060')]
    public function testCoreSectionLeadsToCabinetEntries(): void
    {
        $command = self::command('adzercms:showcase');
        $command->execute([]);

        self::assertSame(
            ['home' => 'http://localhost/cabinet', 'register' => 'http://localhost/register', 'forgot' => 'http://localhost/forgot', 'profile' => 'http://localhost/profile'],
            json_decode($command->getDisplay(), true, flags: \JSON_THROW_ON_ERROR)[ShowcaseCommand::CORE],
        );
    }
}
