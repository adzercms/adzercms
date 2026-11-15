<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Controller;

use AdzerCMS\Core\Account\Avatars;
use AdzerCMS\Core\Http\ImmutableFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class AvatarController
{
    public const ROUTE = 'avatar';

    public function __construct(private Avatars $avatars)
    {
    }

    #[Route('/avatars/{file}', name: self::ROUTE, requirements: ['file' => '[^/]+'], methods: ['GET'])]
    public function __invoke(string $file, Request $request): ImmutableFileResponse
    {
        return new ImmutableFileResponse($this->avatars->path($file) ?? throw new NotFoundHttpException(), $request);
    }
}
