<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Controller;

use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Setting\Catalog;
use AdzerCMS\Core\Setting\Images;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class SettingImageController
{
    private const SANDBOX = "default-src 'none'; style-src 'unsafe-inline'; sandbox";

    public function __construct(
        private readonly Catalog $catalog,
        private readonly Images $images,
    ) {
    }

    #[Route('/setting/{name}', name: 'setting_image', requirements: ['name' => '[a-z0-9_.]+'], methods: ['GET'])]
    public function __invoke(string $name): BinaryFileResponse
    {
        $field = $this->catalog->fields()[$name] ?? null;
        $path = null !== $field && Type::Image === $field->type ? $this->images->path($field) : null;
        if (null === $path) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($path, headers: ['Content-Security-Policy' => self::SANDBOX], autoEtag: true);
        $response->headers->addCacheControlDirective('no-cache');

        return $response;
    }
}
