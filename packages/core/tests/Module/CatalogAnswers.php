<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module;

use AdzerCMS\Core\Composer\Repositories;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Tests\HttpAnswers;
use Composer\MetadataMinifier\MetadataMinifier;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

trait CatalogAnswers
{
    /** @var array<string, mixed>|null */
    private ?array $root = null;

    /** @var array<string, array<string, mixed>> */
    private array $pages = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $packages = [];

    private function answerCatalog(): void
    {
        self::service(HttpAnswers::class)->respond(fn (string $method, string $url): MockResponse => match (true) {
            Repositories::PACKAGIST.'/packages.json' === $url => null === $this->root ? new MockResponse('', ['http_code' => 503]) : new JsonMockResponse($this->root),
            isset($this->pages[$url]) => new JsonMockResponse($this->pages[$url]),
            str_starts_with($url, Repositories::PACKAGIST.'/p2/') => $this->metadata(substr($url, \strlen(Repositories::PACKAGIST.'/p2/'), -5)),
            default => self::fail(\sprintf('unexpected request %s', $url)),
        });
    }

    /** @param list<string> $names */
    private function listed(array $names): void
    {
        $this->root = ['metadata-url' => '/p2/%package%.json', 'available-packages' => $names];
    }

    /**
     * @param array<string, string> $provide
     * @param list<string>          $categories
     */
    private function offer(string $name, string $version, string $type = Registry::TYPE, array $provide = [], array $categories = []): void
    {
        $this->packages[$name] = [[
            'name' => $name,
            'description' => 'Package '.$name,
            'version' => $version,
            'type' => $type,
            'provide' => $provide,
            'homepage' => 'https://'.strtr($name, '/', '-').'.example.test',
            'extra' => ['adzercms' => ['categories' => $categories]],
        ]];
    }

    private function metadata(string $name): MockResponse
    {
        return isset($this->packages[$name])
            ? new JsonMockResponse(['minified' => 'composer/2.0', 'packages' => [$name => MetadataMinifier::minify($this->packages[$name])]])
            : new MockResponse('', ['http_code' => 404]);
    }
}
