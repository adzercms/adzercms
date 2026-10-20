<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module;

use AdzerCMS\Core\Composer\Repositories;
use AdzerCMS\Core\Composer\Source;
use AdzerCMS\Core\Module\Marks;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Tests\HttpAnswers;
use Composer\MetadataMinifier\MetadataMinifier;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

trait CatalogAnswers
{
    /** @var array<string, mixed>|null */
    private ?array $root = null;

    /** @var array<string, array<string, mixed>> */
    private array $pages = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $packages = [];

    /** @var array<string, array<string, list<array<string, mixed>>>> */
    private array $hosted = [];

    /** @var list<string> */
    private array $unavailable = [];

    /** @var array<string, ?string> */
    private array $authorized = [];

    /** @var array<string, array<string, array{version: string, outcome: string}>>|null */
    private ?array $registry = [];

    private function answerCatalog(): void
    {
        self::service(HttpAnswers::class)->respond(function (string $method, string $url, array $options): MockResponse {
            $this->authorized[Source::origin($url)] = $options['normalized_headers']['authorization'][0] ?? null;
            if (array_any($this->unavailable, static fn (string $repository): bool => str_starts_with($url, $repository.'/'))) {
                return new MockResponse('', ['http_code' => 503]);
            }
            foreach ($this->hosted as $repository => $packages) {
                if (str_starts_with($url, $repository.'/')) {
                    return $repository.'/packages.json' === $url
                        ? new JsonMockResponse(['metadata-url' => '/p2/%package%.json', 'available-packages' => array_keys($packages)])
                        : self::minified($packages, substr($url, \strlen($repository.'/p2/'), -5));
                }
            }

            return match (true) {
                Marks::REGISTRY === $url => null === $this->registry ? new MockResponse('', ['http_code' => 503]) : new JsonMockResponse($this->registry),
                Repositories::PACKAGIST.'/packages.json' === $url => null === $this->root ? new MockResponse('', ['http_code' => 503]) : new JsonMockResponse($this->root),
                isset($this->pages[$url]) => new JsonMockResponse($this->pages[$url]),
                str_starts_with($url, Repositories::PACKAGIST.'/p2/') => self::minified($this->packages, substr($url, \strlen(Repositories::PACKAGIST.'/p2/'), -5)),
                default => self::fail(\sprintf('unexpected request %s', $url)),
            };
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
        $this->packages[$name] = [self::manifest($name, $version, $type, $provide, $categories)];
    }

    private function branch(string $name, string $alias): void
    {
        $this->packages[$name.'~dev'] = [['default-branch' => true] + array_replace_recursive(self::manifest($name, 'dev-main'), ['extra' => ['branch-alias' => ['dev-main' => $alias]]])];
    }

    private function host(string $repository, string $name, string $version): void
    {
        $this->hosted[$repository][$name] = [self::manifest($name, $version)];
    }

    private function mark(string $name, string $reference, string $outcome = Marks::PASSED): void
    {
        $this->registry[$name][$reference] = ['version' => '1.0.0', 'outcome' => $outcome];
    }

    private static function commit(string $name, string $version): string
    {
        return sha1($name.'@'.$version);
    }

    /** @param array<string, string> $entries */
    private function archive(array $entries): UploadedFile
    {
        $path = \sprintf('%s/scratch/%s.zip', self::parameter('adzercms.files_dir'), bin2hex(random_bytes(4)));
        (new Filesystem())->mkdir(\dirname($path));
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        foreach ($entries as $entry => $content) {
            $zip->addFromString($entry, $content);
        }
        $zip->close();

        return new UploadedFile($path, basename($path), null, null, true);
    }

    /**
     * @param array<string, string> $provide
     * @param list<string>          $categories
     *
     * @return array<string, mixed>
     */
    private static function manifest(string $name, string $version, string $type = Registry::TYPE, array $provide = [], array $categories = []): array
    {
        return [
            'name' => $name,
            'description' => 'Package '.$name,
            'version' => $version,
            'type' => $type,
            'provide' => $provide,
            'homepage' => 'https://'.strtr($name, '/', '-').'.example.test',
            'source' => ['type' => 'git', 'url' => 'https://git.example.test/'.$name, 'reference' => self::commit($name, $version)],
            'extra' => ['adzercms' => ['categories' => $categories]],
        ];
    }

    /** @param array<string, list<array<string, mixed>>> $packages */
    private static function minified(array $packages, string $file): MockResponse
    {
        return isset($packages[$file])
            ? new JsonMockResponse(['minified' => 'composer/2.0', 'packages' => [explode('~', $file)[0] => MetadataMinifier::minify($packages[$file])]])
            : new MockResponse('', ['http_code' => 404]);
    }
}
