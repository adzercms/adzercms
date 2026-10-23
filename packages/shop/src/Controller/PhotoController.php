<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Controller;

use AdzerCMS\Core\Http\ImmutableFileResponse;
use AdzerCMS\Shop\Photos;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PhotoController extends AbstractController
{
    public const ROUTE = 'shop_photo';

    public function __construct(private readonly Photos $photos)
    {
    }

    #[Route('/shop/photo/{name}', name: self::ROUTE, requirements: ['name' => '[^/]+'], methods: ['GET'])]
    public function photo(string $name, Request $request): Response
    {
        return new ImmutableFileResponse($this->photos->path($name) ?? throw $this->createNotFoundException(), $request);
    }
}
