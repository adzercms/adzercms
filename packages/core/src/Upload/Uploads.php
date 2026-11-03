<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Upload;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function Symfony\Component\Clock\now;

final readonly class Uploads
{
    public const FIELD = '_uploads';
    public const PENDING = 32;
    private const KEY = '/^[0-9a-f]{32}$/';
    private const KEY_BYTES = 16;
    private const META = '.json';
    private const TERM = '-1 day';

    public function __construct(
        #[Autowire('%adzercms.files_dir%/uploads')]
        private string $directory,
    ) {
    }

    public function keep(UploadedFile $file, string $field, string $owner): ?string
    {
        $place = $this->place($owner);
        if (\count(glob($place.'/*'.self::META) ?: []) >= self::PENDING) {
            return null;
        }

        $key = bin2hex(random_bytes(self::KEY_BYTES));
        $file->move($place, $key);
        (new Filesystem())->dumpFile($place.'/'.$key.self::META, json_encode([
            'field' => $field,
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
        ], \JSON_THROW_ON_ERROR));

        return $key;
    }

    /** @return array{string, UploadedFile}|null */
    public function take(string $key, string $owner): ?array
    {
        $path = $this->place($owner).'/'.$key;
        if (1 !== preg_match(self::KEY, $key) || !is_file($path.self::META) || !is_file($path)) {
            return null;
        }

        $meta = json_decode((string) file_get_contents($path.self::META), true, flags: \JSON_THROW_ON_ERROR);
        unlink($path.self::META);
        if (!\is_array($meta)) {
            return null;
        }

        return [(string) $meta['field'], new UploadedFile($path, (string) $meta['name'], (string) $meta['mime'], \UPLOAD_ERR_OK, true)];
    }

    public function sweep(): int
    {
        $limit = now()->modify(self::TERM)->getTimestamp();
        $stale = array_filter(glob($this->directory.'/*/*') ?: [], static fn (string $path): bool => filemtime($path) < $limit);
        (new Filesystem())->remove($stale);
        foreach (glob($this->directory.'/*', \GLOB_ONLYDIR) ?: [] as $place) {
            if (filemtime($place) < $limit) {
                @rmdir($place);
            }
        }

        return \count(array_filter($stale, static fn (string $path): bool => !str_ends_with($path, self::META)));
    }

    private function place(string $owner): string
    {
        return $this->directory.'/'.hash('sha256', $owner);
    }
}
