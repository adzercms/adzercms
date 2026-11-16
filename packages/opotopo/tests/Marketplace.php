<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo\Tests;

use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\HttpAnswers;
use AdzerCMS\Opotopo\OpotopoSettings;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

trait Marketplace
{
    use Configuration;

    private const STORAGE = 'https://storage.opotopo.example.test/';
    private const KEY = 'seller-key';
    private const ORGANIZATION = '0199a000-0000-7000-8000-00000000a001';
    private const SYNC = OpotopoSettings::API.'/rest/v1/advertisement/seller/ads/sync';
    private const CATEGORIES = self::SYNC.'/categories';
    private const AD = OpotopoSettings::API.'/rest/v1/advertisement/seller/ads/';
    private const INITIATE = OpotopoSettings::API.'/rest/v1/file/seller/upload/initiate';
    private const COMPLETE = OpotopoSettings::API.'/rest/v1/file/seller/upload/complete';

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

    private function connect(): void
    {
        $this->configure([
            OpotopoSettings::KEY => self::KEY,
            OpotopoSettings::ORGANIZATION => self::ORGANIZATION,
            OpotopoSettings::PUBLISH => '1',
        ]);
        self::service(HttpAnswers::class)->respond($this->platform(...));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<mixed>
     */
    private static function items(array $body): array
    {
        $items = $body['items'] ?? null;

        return \is_array($items) && array_is_list($items) ? $items : self::fail('the sync carries no items');
    }

    /** @param array<string, mixed> $options */
    private function platform(string $method, string $url, array $options): MockResponse
    {
        $body = \is_string($options['body'] ?? null) ? $options['body'] : '';

        return match (true) {
            $this->down => new JsonMockResponse(['title' => 'SERVICE_UNAVAILABLE', 'status' => 503], ['http_code' => 503]),
            self::SYNC === $url => $this->sync($options, (array) json_decode($body, true)),
            self::CATEGORIES === $url => self::categorize((array) json_decode($body, true)),
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

    /** @param array<string, mixed> $body */
    private static function categorize(array $body): MockResponse
    {
        return new JsonMockResponse(['results' => array_map(static fn (mixed $item): array => ['categoryId' => \is_array($item) ? $item['categoryId'] ?? null : null], self::items($body))]);
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
        $this->uploads[$last] = ['request' => $this->uploads[$last]['request'], 'type' => self::header($options, 'content-type'), 'content' => $content];

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
