<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Controller;

use AdzerCMS\Core\Http\ImmutableFileResponse;
use AdzerCMS\Core\Setting\Catalog;
use AdzerCMS\Core\Setting\Images;
use AdzerCMS\Core\Site\Hosts;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
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

    #[Route('/setting/{name}', name: 'setting_image', requirements: ['name' => '[a-z0-9_.]+'], defaults: [Hosts::ROLE => Hosts::SHARED], methods: ['GET'])]
    public function __invoke(string $name, Request $request): BinaryFileResponse
    {
        $field = $this->catalog->fields()[$name] ?? null;
        $path = null !== $field && $field->type->picture() ? $this->images->path($field) : null;
        if (null === $field || null === $path) {
            throw new NotFoundHttpException();
        }

        if ($request->query->getString(Images::VERSION) === $this->images->version($field)) {
            $response = new ImmutableFileResponse($path, $request);
        } else {
            $response = new BinaryFileResponse($path, autoEtag: true);
            $response->headers->addCacheControlDirective('no-cache');
            $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        }
        $response->headers->set('Content-Security-Policy', self::SANDBOX);

        return $response;
    }
}
