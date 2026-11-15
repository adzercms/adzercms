<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Core\Photo\Photos;
use AdzerCMS\Pages\Controller\PageController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class Pictures
{
    private const PLACE = 'pages';
    private const SIDE = 1920;

    public function __construct(
        private Photos $photos,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function store(UploadedFile $picture): string
    {
        return $this->photos->store($picture, self::PLACE, self::SIDE);
    }

    public function path(string $name): ?string
    {
        return $this->photos->path(self::PLACE, $name);
    }

    /** @return array{src: string, width: int, height: int}|null */
    public function shown(string $name): ?array
    {
        $path = $this->path($name);
        $size = null === $path ? false : getimagesize($path);

        return false === $size ? null : ['src' => $this->urls->generate(PageController::PICTURE, ['file' => $name]), 'width' => $size[0], 'height' => $size[1]];
    }

    /** @param array<array-key, string> $values */
    public function drop(array $values): void
    {
        $this->photos->drop(self::PLACE, self::names($values));
    }

    /**
     * @param array<array-key, string> $values
     *
     * @return list<string>
     */
    public static function names(array $values): array
    {
        $names = [];
        foreach ($values as $value) {
            $rows = json_validate($value) ? json_decode($value, true) : null;
            if (\is_array($rows)) {
                array_walk_recursive($rows, static function (mixed $cell) use (&$names): void {
                    if (\is_string($cell)) {
                        $names[] = $cell;
                    }
                });
            } else {
                $names[] = $value;
            }
        }

        return $names;
    }
}
