<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Showcase;

use AdzerCMS\Core\Command\ShowcaseCommand;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Tests\Analytics\Fixture\Tally\TallyCounter;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;

final class ShowcaseCommandTest extends SchemaTestCase
{
    use Configuration;

    private const SITE = 'shop';

    /** @return iterable<string, array{array<string, string>, array<string, mixed>}> */
    public static function settings(): iterable
    {
        yield 'nothing to show' => [[], []];
        yield 'counter switched on' => [
            [TallyCounter::SETTINGS.Switches::ENABLED => '1', TallyCounter::SETTINGS.TallyCounter::SITE => self::SITE],
            [ShowcaseCommand::CORE => ['counters' => ['tally' => ['site' => self::SITE]]], 'tally' => [TallyCounter::SITE => self::SITE]],
        ];
    }

    /**
     * @param array<string, string> $settings
     * @param array<string, mixed>  $showcase
     */
    #[DataProvider('settings')]
    #[Group('ACMS-CORE-1012')]
    public function testSectionsGoUnderModuleKeysWithoutEmptyOnes(array $settings, array $showcase): void
    {
        $this->configure($settings);

        $command = self::command('adzercms:showcase');
        self::assertSame(Command::SUCCESS, $command->execute([]));

        $display = trim($command->getDisplay());
        self::assertStringStartsWith('{', $display, 'the showcase is an object even without sections');
        self::assertSame($showcase, json_decode($display, true, flags: \JSON_THROW_ON_ERROR));
    }
}
