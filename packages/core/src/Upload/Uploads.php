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
    private const KEY = '/^[0-9a-f]{32}$/';
    private const KEY_BYTES = 16;
    private const META = '.json';
    private const TERM = '-1 day';

    public function __construct(
        #[Autowire('%adzercms.files_dir%/uploads')]
        private string $directory,
    ) {
    }

    public function keep(UploadedFile $file, string $field, string $owner): string
    {
        $key = bin2hex(random_bytes(self::KEY_BYTES));
        $file->move($this->directory, $key);
        (new Filesystem())->dumpFile($this->directory.'/'.$key.self::META, json_encode([
            'owner' => hash('sha256', $owner),
            'field' => $field,
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
        ], \JSON_THROW_ON_ERROR));

        return $key;
    }

    /** @return array{string, UploadedFile}|null */
    public function take(string $key, string $owner): ?array
    {
        $path = $this->directory.'/'.$key;
        if (1 !== preg_match(self::KEY, $key) || !is_file($path.self::META) || !is_file($path)) {
            return null;
        }

        $meta = json_decode((string) file_get_contents($path.self::META), true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($meta) || !hash_equals((string) $meta['owner'], hash('sha256', $owner))) {
            return null;
        }
        unlink($path.self::META);

        return [(string) $meta['field'], new UploadedFile($path, (string) $meta['name'], (string) $meta['mime'], \UPLOAD_ERR_OK, true)];
    }

    public function sweep(): int
    {
        $limit = now()->modify(self::TERM)->getTimestamp();
        $stale = array_filter(glob($this->directory.'/*') ?: [], static fn (string $path): bool => filemtime($path) < $limit);
        (new Filesystem())->remove($stale);

        return \count(array_filter($stale, static fn (string $path): bool => !str_ends_with($path, self::META)));
    }
}
