<?php

declare(strict_types=1);

namespace AdzerCMS\Shop;

use AdzerCMS\Contracts\Refusal;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function Symfony\Component\Translation\t;

final readonly class Photos
{
    public const SIDE = 1600;
    private const TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const NAME = '/^[0-9a-f]{32}\.webp$/';

    public function __construct(
        private ImageManagerInterface $images,
        #[Autowire('%adzercms.files_dir%/shop')]
        private string $directory,
    ) {
    }

    /** @param list<UploadedFile> $uploads */
    public static function refusal(array $uploads): ?Refusal
    {
        foreach ($uploads as $upload) {
            if (!$upload->isValid() || !\in_array($upload->getMimeType(), self::TYPES, true)) {
                return new Refusal(t('shop.refusal.photo', ['name' => $upload->getClientOriginalName()]));
            }
        }

        return null;
    }

    public function store(UploadedFile $upload): string
    {
        $name = bin2hex(random_bytes(16)).'.webp';
        (new Filesystem())->mkdir($this->directory);
        $this->images->decodePath($upload->getPathname())
            ->scaleDown(self::SIDE, self::SIDE)
            ->encode(new WebpEncoder(strip: true))
            ->save($this->directory.'/'.$name);

        return $name;
    }

    public function path(string $name): ?string
    {
        $path = $this->directory.'/'.$name;

        return 1 === preg_match(self::NAME, $name) && is_file($path) ? $path : null;
    }

    /** @param list<string> $names */
    public function drop(array $names): void
    {
        (new Filesystem())->remove(array_filter(array_map($this->path(...), $names)));
    }
}
