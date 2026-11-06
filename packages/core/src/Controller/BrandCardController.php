<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Controller;

use AdzerCMS\Core\Setting\Brand;
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
    private const BACKGROUND = 'ffffff';

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

        return $response->setContent((string) ImageManager::usingDriver(Driver::class)->decode(self::picture($logo))
            ->scaleDown(self::LOGO_WIDTH, self::LOGO_HEIGHT)
            ->fillTransparentAreas(self::BACKGROUND)
            ->containDown(self::WIDTH, self::HEIGHT, self::BACKGROUND)
            ->encode(new PngEncoder()));
    }

    private static function picture(string $path): \Imagick
    {
        $picture = new \Imagick();
        if ('svg' === pathinfo($path, \PATHINFO_EXTENSION)) {
            $picture->pingImage($path);
            $density = $picture->getImageResolution()['x'] * min(self::LOGO_WIDTH / $picture->getImageWidth(), self::LOGO_HEIGHT / $picture->getImageHeight());
            $picture = new \Imagick();
            $picture->setBackgroundColor('transparent');
            $picture->setResolution($density, $density);
        }
        $picture->readImage($path);

        return $picture;
    }
}
