<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Controller;

use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Setting\Images;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class BrandCardController
{
    public const WIDTH = 1200;
    public const HEIGHT = 630;
    public const LOGO_WIDTH = 960;
    public const LOGO_HEIGHT = 420;
    private const LIGHT = 'ffffff';
    private const SAMPLE = 200;
    private const NEAR = 0.15;

    public function __construct(
        private Brand $brand,
    ) {
    }

    #[Route('/brand.png', name: Brand::CARD, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $logo = $this->brand->cardLogo() ?? throw new NotFoundHttpException();
        $response = new Response(headers: ['Content-Type' => 'image/png']);
        $response->setLastModified(new \DateTimeImmutable('@'.(int) filemtime($logo)));
        $response->headers->addCacheControlDirective('no-cache');
        if ($response->isNotModified($request)) {
            return $response;
        }

        $picture = self::picture($logo);
        $background = self::background($picture);

        return $response->setContent((string) ImageManager::usingDriver(Driver::class)->decode($picture)
            ->scaleDown(self::LOGO_WIDTH, self::LOGO_HEIGHT)
            ->fillTransparentAreas($background)
            ->containDown(self::WIDTH, self::HEIGHT, $background)
            ->encode(new PngEncoder()));
    }

    private static function background(\Imagick $picture): string
    {
        $sample = clone $picture;
        $sample->scaleImage(self::SAMPLE, 0);
        $pixels = $sample->exportImagePixels(0, 0, $sample->getImageWidth(), $sample->getImageHeight(), 'RGBA', \Imagick::PIXEL_CHAR);
        $light = $dark = 0;
        for ($i = 0, $count = \count($pixels); $i < $count; $i += 4) {
            if ($pixels[$i + 3] >= 128) {
                $luminance = (0.2126 * $pixels[$i] + 0.7152 * $pixels[$i + 1] + 0.0722 * $pixels[$i + 2]) / 255;
                $light += (int) ($luminance > 1 - self::NEAR);
                $dark += (int) ($luminance < self::NEAR);
            }
        }

        return $light > $dark ? Images::INK : self::LIGHT;
    }

    private static function picture(string $path): \Imagick
    {
        $picture = new \Imagick();
        if ('svg' !== pathinfo($path, \PATHINFO_EXTENSION)) {
            $picture->readImage($path);

            return $picture;
        }

        $source = Images::fixed((string) file_get_contents($path));
        $picture->setFormat('svg');
        $picture->pingImageBlob($source);
        $density = $picture->getImageResolution()['x'] * min(self::LOGO_WIDTH / $picture->getImageWidth(), self::LOGO_HEIGHT / $picture->getImageHeight());
        $picture = new \Imagick();
        $picture->setBackgroundColor('transparent');
        $picture->setResolution($density, $density);
        $picture->setFormat('svg');
        $picture->readImageBlob($source);

        return $picture;
    }
}
