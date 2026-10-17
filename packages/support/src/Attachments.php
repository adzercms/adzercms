<?php

declare(strict_types=1);

namespace AdzerCMS\Support;

use AdzerCMS\Contracts\Refusal;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function Symfony\Component\Translation\t;

final class Attachments
{
    public const NAME_MAX = 190;

    private const NAME_BYTES = 16;

    /**
     * @param array<mixed> $files
     *
     * @return list<UploadedFile>
     */
    public static function uploads(array $files): array
    {
        return array_values(array_filter($files, static fn (mixed $file): bool => $file instanceof UploadedFile));
    }

    /**
     * @param array<mixed> $files
     * @param list<string> $types
     */
    public static function fault(array $files, int $limit, array $types): ?Refusal
    {
        foreach (self::uploads($files) as $file) {
            if (!$file->isValid()) {
                return \in_array($file->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)
                    ? self::oversize($limit)
                    : new Refusal(t('support.refusal.upload'));
            }

            $extension = strtolower($file->getClientOriginalExtension());
            if (!\in_array($extension, $types, true)) {
                return new Refusal(t('support.refusal.type', ['extension' => $extension]));
            }
            if ($file->getSize() > $limit) {
                return self::oversize($limit);
            }
        }

        return null;
    }

    /** @return array{name: string, stored: string, mime: string, size: int} */
    public static function keep(UploadedFile $file, string $directory): array
    {
        $kept = [
            'name' => mb_substr($file->getClientOriginalName(), 0, self::NAME_MAX),
            'stored' => bin2hex(random_bytes(self::NAME_BYTES)).'.'.strtolower($file->getClientOriginalExtension()),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => (int) $file->getSize(),
        ];

        $file->move($directory, $kept['stored']);

        return $kept;
    }

    /** @param list<string> $paths */
    public static function erase(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public static function oversize(int $limit): Refusal
    {
        return new Refusal(t('support.refusal.oversize', ['megabytes' => max(1, (int) round($limit / 1024 / 1024))]));
    }
}
