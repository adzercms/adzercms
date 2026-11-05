<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Photo\Photos;
use AdzerCMS\Extensions\Entity\Extension;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function Symfony\Component\Translation\t;

final readonly class ExtensionImages
{
    public const KEPT = 'images';
    public const ADDED = 'added';
    public const MAX = 8;

    private const PLACE = 'extensions';
    private const SIDE = 1920;

    public function __construct(private Photos $photos)
    {
    }

    /**
     * @param array<mixed> $kept
     * @param array<mixed> $added
     *
     * @return array{list<string>, list<UploadedFile>}|Refusal
     */
    public static function read(Extension $extension, array $kept, array $added): array|Refusal
    {
        $names = array_values(array_intersect(array_unique(array_filter($kept, \is_string(...))), $extension->getImages()));
        $uploads = array_values(array_filter($added, static fn (mixed $file): bool => $file instanceof UploadedFile));
        $refusal = Photos::refusal($uploads);
        if (null !== $refusal) {
            return $refusal;
        }

        return \count($names) + \count($uploads) > self::MAX ? new Refusal(t('extensions.refusal.images', ['max' => self::MAX])) : [$names, $uploads];
    }

    /**
     * @param list<string>       $kept
     * @param list<UploadedFile> $uploads
     */
    public function attach(Extension $extension, array $kept, array $uploads): void
    {
        $extension->picture([...$kept, ...array_map(fn (UploadedFile $upload): string => $this->photos->store($upload, self::PLACE, self::SIDE), $uploads)]);
    }

    public function path(string $name): ?string
    {
        return $this->photos->path(self::PLACE, $name);
    }

    /** @param list<string> $names */
    public function drop(array $names): void
    {
        $this->photos->drop(self::PLACE, $names);
    }
}
