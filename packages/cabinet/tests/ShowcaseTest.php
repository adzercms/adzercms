<?php

declare(strict_types=1);

namespace AdzerCMS\Cabinet\Tests;

use AdzerCMS\Core\Command\ShowcaseCommand;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;

final class ShowcaseTest extends SchemaTestCase
{
    #[Group('ACMS-CORE-1012')]
    public function testCoreSectionLeadsToCabinetHomeAndRegistration(): void
    {
        $command = self::command('adzercms:showcase');
        $command->execute([]);

        $core = json_decode($command->getDisplay(), true, flags: \JSON_THROW_ON_ERROR)[ShowcaseCommand::CORE];
        self::assertSame('http://localhost/cabinet', $core['home']);
        self::assertSame('http://localhost/register', $core['register']);
    }
}
