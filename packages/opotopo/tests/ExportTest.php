<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo\Tests;

use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Opotopo\ItemDigest;
use AdzerCMS\Opotopo\OpotopoSettings;
use AdzerCMS\Opotopo\Tests\Fixture\ShelfCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;

final class ExportTest extends SchemaTestCase
{
    use Marketplace;

    private const CATEGORY = '0199a000-0000-7000-8000-00000000c001';
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        self::shelf()->rows = [];
        $this->connect();
    }

    #[Group('ACMS-US-0038')]
    #[Group('ACMS-OTO-0004')]
    #[Group('ACMS-OTO-0006')]
    public function testNewChangedAndDroppedItemsReachMarketplaceByScheduledJob(): void
    {
        (new Filesystem())->dumpFile(self::parameter('adzercms.files_dir').'/shelf/a.png', (string) base64_decode(self::PNG, true));
        self::shelf()->rows = [
            'a' => ['sku' => 'A-1', 'title' => 'Shop module', 'price' => '1500.00', 'category' => self::CATEGORY, 'cover' => 'shelf/a.png'],
            'b' => ['sku' => 'B-2', 'title' => 'Blog module', 'price' => '900.00', 'category' => self::CATEGORY],
            'c' => ['sku' => 'C-3', 'title' => 'Forum module', 'price' => '0', 'category' => self::CATEGORY],
        ];

        $job = self::command('adzercms:opotopo');
        self::assertSame(Command::SUCCESS, $job->execute([]), $job->getDisplay());

        self::assertSame('created 3, updated 0, unchanged 0, archived 0', trim($job->getDisplay()));
        self::assertSame([self::KEY], array_unique(array_column($this->synced, 'key')));
        $sent = $this->synced[0]['body'];
        self::assertSame([self::ORGANIZATION, true], [$sent['organizationId'] ?? null, $sent['publish'] ?? null]);
        self::assertSame(['A-1', 'B-2', 'C-3'], array_column(self::items($sent), 'sku'));
        self::assertSame([1500, 'image/png', base64_decode(self::PNG, true)], [self::items($sent)[0]['price'], $this->uploads[0]['type'], $this->uploads[0]['content']]);
        self::assertSame([$this->uploads[0]['request']['fileId']], self::items($sent)[0]['imageFileIds']);

        unset(self::shelf()->rows['a']);
        self::shelf()->rows['b']['title'] = 'Blog module 2';
        $job = self::command('adzercms:opotopo');

        self::assertSame(Command::SUCCESS, $job->execute([]), $job->getDisplay());
        self::assertSame('created 0, updated 1, unchanged 1, archived 1', trim($job->getDisplay()));
        self::assertSame(['B-2'], array_column(self::items($this->synced[1]['body']), 'sku'));
        self::assertSame([$this->ads['A-1']], $this->archived);
    }

    #[Group('ACMS-US-0038')]
    #[Group('ACMS-OTO-0008')]
    public function testRefusedItemStaysInStaffDigestUntilSentSuccessfully(): void
    {
        self::shelf()->rows = ['b' => ['sku' => 'B-2', 'title' => 'Blog module', 'price' => '900.00', 'category' => self::CATEGORY]];
        $this->refusals['B-2'] = ['error' => 'VALIDATION_ERROR', 'errorDetails' => ['title' => 'REQUIRED']];

        $job = self::command('adzercms:opotopo');
        self::assertSame(Command::SUCCESS, $job->execute([]), $job->getDisplay());

        self::assertStringContainsString('refused shelf B-2: VALIDATION_ERROR {"title":"REQUIRED"}', $job->getDisplay());
        $line = self::phrase('opotopo.digest.line', ['catalog' => ShelfCatalog::NAME, 'sku' => 'B-2', 'error' => 'VALIDATION_ERROR', 'details' => '{"title":"REQUIRED"}']);
        self::assertContains($line, self::digest());

        $this->refusals = [];
        self::assertSame(Command::SUCCESS, self::command('adzercms:opotopo')->execute([]));

        self::assertCount(2, $this->synced);
        self::assertNotContains($line, self::digest());
    }

    #[Group('ACMS-OTO-0002')]
    #[Group('ACMS-OTO-0006')]
    #[DataProvider('refusals')]
    public function testJobRefusesWithoutConnectionUnknownCatalogOrUnavailableMarketplace(string $case, string $said): void
    {
        self::shelf()->rows = ['b' => ['sku' => 'B-2', 'title' => 'Blog module', 'price' => '900.00', 'category' => self::CATEGORY]];
        self::mockTime();
        match ($case) {
            'key' => $this->unconfigure(OpotopoSettings::KEY),
            'down' => $this->down = true,
            default => null,
        };

        $job = self::command('adzercms:opotopo');

        self::assertSame(Command::FAILURE, $job->execute('catalog' === $case ? ['--catalog' => 'missing'] : []));
        self::assertStringContainsString($said, $job->getDisplay());
    }

    /** @return iterable<string, array{string, string}> */
    public static function refusals(): iterable
    {
        yield 'without key' => ['key', 'not configured'];
        yield 'unknown catalog' => ['catalog', 'unknown catalog missing, catalogs: shelf'];
        yield 'marketplace unavailable after retries' => ['down', 'OPOTOPO unavailable: 503'];
    }

    private static function shelf(): ShelfCatalog
    {
        return self::service(ShelfCatalog::class);
    }

    /** @return list<string> */
    private static function digest(): array
    {
        return self::service(ItemDigest::class)->lines();
    }
}
