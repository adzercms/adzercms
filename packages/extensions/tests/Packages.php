<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Tests;

use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\HttpAnswers;
use AdzerCMS\Extensions\ExtensionsSettings;
use Composer\MetadataMinifier\MetadataMinifier;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

trait Packages
{
    use Configuration;

    private const PAID = 'https://paid.example.test';
    private const OPEN = 'https://open.example.test/composer';

    /** @var array<string, list<array<string, mixed>>> */
    private array $paid = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $open = [];

    private bool $down = false;

    private function serve(): void
    {
        $this->configure([ExtensionsSettings::REPOSITORIES => self::PAID.', '.self::OPEN]);

        self::service(HttpAnswers::class)->respond(fn (string $method, string $url): MockResponse => match (true) {
            self::PAID.'/packages.json' === $url => $this->down
                ? new MockResponse('', ['http_code' => 503])
                : new JsonMockResponse(['packages' => [], 'metadata-url' => '/p2/%package%.json']),
            self::OPEN.'/packages.json' === $url => new JsonMockResponse(['packages' => [], 'metadata-url' => self::OPEN.'/p2/%package%.json']),
            str_starts_with($url, self::PAID.'/p2/') => self::answer($this->paid, substr($url, \strlen(self::PAID.'/p2/'), -5)),
            str_starts_with($url, self::OPEN.'/p2/') => self::answer($this->open, substr($url, \strlen(self::OPEN.'/p2/'), -5)),
            default => self::fail(\sprintf('unexpected request %s', $url)),
        });
    }

    /** @param array<string, list<array<string, mixed>>> $packages */
    private static function answer(array $packages, string $name): MockResponse
    {
        return isset($packages[$name])
            ? new JsonMockResponse(['minified' => 'composer/2.0', 'packages' => [strtok($name, '~') => MetadataMinifier::minify($packages[$name])]])
            : new MockResponse('', ['http_code' => 404]);
    }

    /**
     * @param array<string, string> $translations
     *
     * @return array<string, mixed>
     */
    private static function version(string $name, string $version, string $description, mixed $categories = [], array $translations = [], ?string $author = null, ?string $price = null): array
    {
        return [
            'name' => $name,
            'description' => $description,
            'version' => $version,
            'type' => 'adzercms-module',
            'homepage' => 'https://shop.example.test',
            'source' => ['url' => 'https://github.com/acme/shop.git', 'type' => 'git', 'reference' => 'a6b26c2'],
            'extra' => [
                'adzercms' => ['bundle' => 'Acme\\Shop\\ShopBundle', 'categories' => $categories, 'description' => $translations, 'author' => $author],
                'opotopo' => ['url' => 'https://opotopo.example.test/ad/1', 'price' => $price],
            ],
        ];
    }
}
