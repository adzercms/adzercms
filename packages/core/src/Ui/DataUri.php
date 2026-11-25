<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Mime\MimeTypes;
use Twig\Attribute\AsTwigFunction;

final readonly class DataUri
{
    public function __construct(private AssetMapperInterface $assets)
    {
    }

    public static function of(string $type, string $bytes): string
    {
        return \sprintf('data:%s;base64,%s', $type, base64_encode($bytes));
    }

    public static function file(string $path): string
    {
        return self::of((string) MimeTypes::getDefault()->guessMimeType($path), (string) file_get_contents($path));
    }

    #[AsTwigFunction('asset_data')]
    public function asset(string $path): string
    {
        return self::file(($this->assets->getAsset($path) ?? throw new \InvalidArgumentException(\sprintf('No asset %s', $path)))->sourcePath);
    }
}
