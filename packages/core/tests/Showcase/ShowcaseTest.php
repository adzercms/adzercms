<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Showcase;

use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Showcase\Sections;
use AdzerCMS\Core\Tests\Analytics\Fixture\Tally\TallyCounter;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;

final class ShowcaseTest extends SchemaTestCase
{
    use Configuration;

    private const SITE = 'shop';

    /** @return iterable<string, array{array<string, string>, array<string, mixed>}> */
    public static function settings(): iterable
    {
        yield 'nothing to show' => [[], []];
        yield 'counter switched on' => [
            [TallyCounter::SETTINGS.Switches::ENABLED => '1', TallyCounter::SETTINGS.TallyCounter::SITE => self::SITE],
            [Sections::CORE => ['counters' => ['tally' => ['site' => self::SITE]]], 'tally' => [TallyCounter::SITE => self::SITE]],
        ];
    }

    /**
     * @param array<string, string> $settings
     * @param array<string, mixed>  $showcase
     */
    #[DataProvider('settings')]
    #[Group('ACMS-CORE-1012')]
    #[Group('ACMS-CORE-1060')]
    public function testConsoleAndAddressGiveSectionsUnderModuleKeysWithoutEmptyOnes(array $settings, array $showcase): void
    {
        $this->configure($settings);

        $command = self::command('adzercms:showcase');
        self::assertSame(Command::SUCCESS, $command->execute([]));
        $display = trim($command->getDisplay());

        $this->client->request('GET', '/showcase.json');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $served = (string) $this->client->getResponse()->getContent();

        foreach ([$display, $served] as $json) {
            self::assertStringStartsWith('{', $json, 'the showcase is an object even without sections');
            self::assertSame($showcase, json_decode($json, true, flags: \JSON_THROW_ON_ERROR));
        }
    }
}
