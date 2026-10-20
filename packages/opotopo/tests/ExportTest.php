<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo\Tests;

use AdzerCMS\Core\Setting\Catalog;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\HttpAnswers;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Opotopo\OpotopoSettings;
use AdzerCMS\Opotopo\Tests\Fixture\ShelfCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ExportTest extends SchemaTestCase
{
    use Configuration;

    private const PLATFORM = 'https://api.opotopo.example.test';
    private const STORAGE = 'https://storage.opotopo.example.test/';
    private const KEY = 'seller-key';
    private const ORGANIZATION = '0199a000-0000-7000-8000-00000000a001';
    private const CATEGORY = '0199a000-0000-7000-8000-00000000c001';
    private const SYNC = self::PLATFORM.'/rest/v1/advertisement/seller/ads/sync';
    private const AD = self::PLATFORM.'/rest/v1/advertisement/seller/ads/';
    private const INITIATE = self::PLATFORM.'/rest/v1/file/seller/upload/initiate';
    private const COMPLETE = self::PLATFORM.'/rest/v1/file/seller/upload/complete';
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /** @var list<array{key: string, body: array<string, mixed>}> */
    private array $synced = [];

    /** @var list<string> */
    private array $archived = [];

    /** @var list<array{request: array<string, mixed>, type: string, content: string}> */
    private array $uploads = [];

    /** @var array<string, string> */
    private array $ads = [];

    /** @var array<string, array{error: string, errorDetails: array<string, string>}> */
    private array $refusals = [];

    private bool $down = false;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure([
            OpotopoSettings::URL => self::PLATFORM,
            OpotopoSettings::KEY => self::KEY,
            OpotopoSettings::ORGANIZATION => self::ORGANIZATION,
            OpotopoSettings::PUBLISH => '1',
        ]);
        self::shelf()->rows = [];
        self::service(HttpAnswers::class)->respond($this->platform(...));
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

    #[Group('ACMS-OTO-0002')]
    public function testMarketplaceAddressOutsideWebIsRefused(): void
    {
        $field = self::service(Catalog::class)->fields()[OpotopoSettings::URL];

        $refusal = self::service(Settings::class)->save($field, 'ftp://opotopo.example.test');

        self::assertSame(self::phrase('opotopo.refusal.url'), self::phrase($refusal->reason ?? self::fail('the address is accepted')));
    }

    private static function shelf(): ShelfCatalog
    {
        return self::service(ShelfCatalog::class);
    }

    /** @return list<string> */
    private static function digest(): array
    {
        $digest = self::command('adzercms:digest');
        $digest->execute([]);
        $collected = json_decode($digest->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);

        return \is_array($collected) && \is_array($collected['lines'] ?? null) ? $collected['lines'] : self::fail('the digest has no lines');
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<array<string, mixed>>
     */
    private static function items(array $body): array
    {
        return \is_array($body['items'] ?? null) ? $body['items'] : self::fail('the sync carries no items');
    }

    /** @param array<string, mixed> $options */
    private function platform(string $method, string $url, array $options): MockResponse
    {
        $body = \is_string($options['body'] ?? null) ? $options['body'] : '';

        return match (true) {
            $this->down => new JsonMockResponse(['title' => 'SERVICE_UNAVAILABLE', 'status' => 503], ['http_code' => 503]),
            self::SYNC === $url => $this->sync($options, (array) json_decode($body, true)),
            self::INITIATE === $url => $this->initiate((array) json_decode($body, true)),
            self::COMPLETE === $url => new JsonMockResponse(['uuid' => ((array) json_decode($body, true))['fileId'] ?? null]),
            'DELETE' === $method && str_starts_with($url, self::AD) => $this->archive(substr($url, \strlen(self::AD))),
            'PUT' === $method && str_starts_with($url, self::STORAGE) => $this->store($options, $body),
            default => self::fail(\sprintf('unexpected request %s %s', $method, $url)),
        };
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $body
     */
    private function sync(array $options, array $body): MockResponse
    {
        $this->synced[] = ['key' => self::header($options, 'x-api-key'), 'body' => $body];
        $results = [];
        foreach (self::items($body) as $item) {
            $sku = \is_string($item['sku'] ?? null) ? $item['sku'] : self::fail('an item without sku');
            $created = !isset($this->ads[$sku]);
            $this->ads[$sku] ??= $this->id();
            $results[] = ['sku' => $sku, 'advertisementId' => $this->ads[$sku], 'created' => $created] + ($this->refusals[$sku] ?? []);
        }

        return new JsonMockResponse(['accepted' => \count($results), 'created' => 0, 'updated' => 0, 'failed' => 0, 'results' => $results]);
    }

    /** @param array<string, mixed> $request */
    private function initiate(array $request): MockResponse
    {
        $fileId = $this->id();
        $this->uploads[] = ['request' => $request + ['fileId' => $fileId], 'type' => '', 'content' => ''];

        return new JsonMockResponse(['fileId' => $fileId, 'objectKey' => 'uploads/'.$fileId, 'requiredHeaders' => [], 'singleUrl' => self::STORAGE.'single/'.$fileId, 'partSize' => 0]);
    }

    /** @param array<string, mixed> $options */
    private function store(array $options, string $content): MockResponse
    {
        $last = array_key_last($this->uploads) ?? self::fail('content before the upload is initiated');
        $this->uploads[$last]['type'] = self::header($options, 'content-type');
        $this->uploads[$last]['content'] = $content;

        return new MockResponse('');
    }

    private function archive(string $advertisementId): MockResponse
    {
        $this->archived[] = $advertisementId;

        return new MockResponse('', ['http_code' => 204]);
    }

    /** @param array<string, mixed> $options */
    private static function header(array $options, string $name): string
    {
        $lines = $options['normalized_headers'][$name] ?? [];
        $line = \is_array($lines) && \is_string($lines[0] ?? null) ? $lines[0] : '';

        return trim(substr($line, (int) strpos($line, ':') + 1));
    }

    private function id(): string
    {
        return \sprintf('0199a000-0000-7000-8000-%012d', ++$this->sequence);
    }
}
