<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Controller;

use AdzerCMS\Shop\Photos;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;

final class PhotoController extends AbstractController
{
    public const ROUTE = 'shop_photo';
    private const AGE = 31536000;

    public function __construct(private readonly Photos $photos)
    {
    }

    #[Route('/shop/photo/{name}', name: self::ROUTE, requirements: ['name' => '[^/]+'], methods: ['GET'])]
    public function photo(string $name, Request $request): Response
    {
        $response = new BinaryFileResponse($this->photos->path($name) ?? throw $this->createNotFoundException(), autoEtag: true);
        $response->setPublic();
        $response->setMaxAge(self::AGE);
        $response->setImmutable();
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $response->isNotModified($request);

        return $response;
    }
}
