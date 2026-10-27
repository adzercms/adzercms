<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Build;

use AdzerCMS\Core\Setting\Secrets;
use Symfony\Component\Filesystem\Filesystem;

final readonly class Preview
{
    public const COOKIE = 'adzercms_preview';
    public const TERM = 86400;

    public function __construct(private string $file)
    {
    }

    /** @param array<string, string> $values */
    public function keep(string $theme, array $values): void
    {
        (new Filesystem())->dumpFile($this->file, json_encode(['theme' => $theme, 'values' => $values], \JSON_THROW_ON_ERROR));
    }

    public function drop(): void
    {
        (new Filesystem())->remove($this->file);
    }

    /** @return array{theme: string, values: array<string, string>}|null */
    public function read(): ?array
    {
        $kept = is_file($this->file) ? json_decode((string) file_get_contents($this->file), true) : null;
        if (!\is_array($kept) || !\is_string($kept['theme'] ?? null) || !\is_array($kept['values'] ?? null)) {
            return null;
        }

        return ['theme' => $kept['theme'], 'values' => array_filter($kept['values'], \is_string(...))];
    }

    public static function mark(string $theme, int $until, string $secret): string
    {
        return $theme.'.'.$until.'.'.Secrets::signature(self::COOKIE.'.'.$theme.'.'.$until, $secret);
    }

    public static function marked(string $mark, int $now, string $secret): ?string
    {
        [$theme, $until] = explode('.', $mark, 3) + ['', ''];

        return '' !== $secret && ctype_digit($until) && (int) $until > $now && hash_equals(self::mark($theme, (int) $until, $secret), $mark) ? $theme : null;
    }
}
