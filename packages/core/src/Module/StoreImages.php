<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Module;

use AdzerCMS\Core\Photo\Photos;
use Intervention\Image\Exceptions\ImageException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class StoreImages
{
    private const PLACE = 'store-images';
    private const SIDE = 1920;
    private const MAX_BYTES = 10485760;
    private const TIMEOUT = 10.0;

    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $httpClient, private Photos $photos)
    {
        $this->client = new NoPrivateNetworkHttpClient($httpClient);
    }

    public function path(string $name): ?string
    {
        return $this->photos->path(self::PLACE, $name);
    }

    public function copied(?StoreDocument $document, ?StoreDocument $held): ?StoreDocument
    {
        $copies = [];
        foreach ($held->entries ?? [] as $entry) {
            $copies += array_filter($entry->images);
        }

        $entries = [];
        foreach ($document->entries ?? [] as $name => $entry) {
            $images = [];
            foreach (array_keys($entry->images) as $url) {
                $images[$url] = $copies[$url] ??= $this->copy($url);
            }
            $entries[$name] = new StoreEntry($entry->type, $entry->demo, $images);
        }

        $names = array_merge(...array_values(array_map(static fn (StoreEntry $entry): array => array_values(array_filter($entry->images)), $entries)), ...[[]]);
        $this->photos->drop(self::PLACE, array_values(array_diff($this->photos->names(self::PLACE), $names)));

        if (null === $document) {
            return null;
        }
        $whole = !array_any($entries, static fn (StoreEntry $entry): bool => \in_array(null, $entry->images, true));

        return new StoreDocument($document->url, $whole ? $document->etag : null, $entries);
    }

    private function copy(string $url): ?string
    {
        if (!\extension_loaded('imagick')) {
            return null;
        }

        $filesystem = new Filesystem();
        $path = $filesystem->tempnam(sys_get_temp_dir(), 'store-image');
        try {
            $filesystem->dumpFile($path, $this->client->request('GET', $url, [
                'timeout' => self::TIMEOUT,
                'on_progress' => static function (int $received): void {
                    if ($received > self::MAX_BYTES) {
                        throw new TransportException('image over the limit');
                    }
                },
            ])->getContent());
            $file = new File($path);

            return null === Photos::refusal([$file]) ? $this->photos->store($file, self::PLACE, self::SIDE) : null;
        } catch (ExceptionInterface|ImageException) {
            return null;
        } finally {
            $filesystem->remove($path);
        }
    }
}
