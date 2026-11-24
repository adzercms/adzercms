<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Core\Photo\Photos;
use AdzerCMS\Pages\Controller\PageController;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function Symfony\Component\Clock\now;

final readonly class Pictures implements Album
{
    private const PLACE = 'pages';
    private const SIDE = 1920;
    private const TERM = '-1 day';

    private PhotoAlbum $album;

    public function __construct(
        Photos $photos,
        UrlGeneratorInterface $urls,
        PageBlockRepository $blocks,
    ) {
        $this->album = new PhotoAlbum(
            $photos,
            self::PLACE,
            self::SIDE,
            static fn (string $name): string => $urls->generate(PageController::PICTURE, ['file' => $name]),
            static fn (): array => PhotoAlbum::blocks($blocks->held()),
        );
    }

    public function store(File $picture): string
    {
        return $this->album->store($picture);
    }

    public function path(string $name): ?string
    {
        return $this->album->path($name);
    }

    public function shown(string $name): ?array
    {
        return $this->album->shown($name);
    }

    public function listed(): array
    {
        return $this->album->listed();
    }

    public function entry(string $name): array
    {
        return $this->album->entry($name);
    }

    public function drop(array $values): void
    {
        $this->album->drop($values);
    }

    public function sweep(): int
    {
        return $this->album->sweep(now()->modify(self::TERM)->getTimestamp());
    }
}
