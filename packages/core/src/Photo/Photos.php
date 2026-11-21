<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Photo;

use AdzerCMS\Contracts\Refusal;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Exceptions\ImageException;
use Intervention\Image\ImageManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\Translation\t;

final readonly class Photos
{
    public const TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const NAME = '/^[0-9a-f]{32}\.webp$/';
    private const FETCH_BYTES = 10485760;
    private const FETCH_TIMEOUT = 10.0;

    private HttpClientInterface $client;

    public function __construct(
        #[Autowire('%adzercms.files_dir%')]
        private string $directory,
        HttpClientInterface $httpClient,
    ) {
        $this->client = new NoPrivateNetworkHttpClient($httpClient);
    }

    /** @param list<File> $photos */
    public static function refusal(array $photos): ?Refusal
    {
        foreach ($photos as $photo) {
            if (($photo instanceof UploadedFile && !$photo->isValid()) || !\in_array($photo->getMimeType(), self::TYPES, true)) {
                return new Refusal(t('core.photo.type', ['name' => $photo instanceof UploadedFile ? $photo->getClientOriginalName() : $photo->getFilename()]));
            }
        }

        return null;
    }

    public function store(File $photo, string $place, int $side): string
    {
        $name = bin2hex(random_bytes(16)).'.webp';
        (new Filesystem())->mkdir($this->directory.'/'.$place);
        ImageManager::usingDriver(Driver::class)->decodePath($photo->getPathname())
            ->scaleDown($side, $side)
            ->encode(new WebpEncoder(strip: true))
            ->save($this->directory.'/'.$place.'/'.$name);

        return $name;
    }

    public function fetched(string $url, string $place, int $side): ?string
    {
        if (!\extension_loaded('imagick')) {
            return null;
        }

        $filesystem = new Filesystem();
        $path = $filesystem->tempnam(sys_get_temp_dir(), 'photo');
        try {
            $filesystem->dumpFile($path, $this->client->request('GET', $url, [
                'timeout' => self::FETCH_TIMEOUT,
                'on_progress' => static function (int $received): void {
                    if ($received > self::FETCH_BYTES) {
                        throw new TransportException('photo over the limit');
                    }
                },
            ])->getContent());
            $file = new File($path);

            return null === self::refusal([$file]) ? $this->store($file, $place, $side) : null;
        } catch (ExceptionInterface|ImageException) {
            return null;
        } finally {
            $filesystem->remove($path);
        }
    }

    public function path(string $place, string $name): ?string
    {
        $path = $this->directory.'/'.$place.'/'.$name;

        return 1 === preg_match(self::NAME, $name) && is_file($path) ? $path : null;
    }

    public function dataUri(string $place, string $name): ?string
    {
        $path = $this->path($place, $name);

        return null === $path ? null : 'data:image/webp;base64,'.base64_encode((string) file_get_contents($path));
    }

    /** @return list<string> */
    public function names(string $place): array
    {
        return array_values(array_filter(
            array_map(basename(...), glob($this->directory.'/'.$place.'/*') ?: []),
            static fn (string $name): bool => 1 === preg_match(self::NAME, $name),
        ));
    }

    /** @param list<string> $names */
    public function drop(string $place, array $names): void
    {
        (new Filesystem())->remove(array_filter(array_map(fn (string $name): ?string => $this->path($place, $name), $names)));
    }

    public function clear(string $place): void
    {
        (new Filesystem())->remove($this->directory.'/'.$place);
    }
}
