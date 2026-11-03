<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Controller;

use AdzerCMS\Core\Upload\Uploads;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
final readonly class UploadController
{
    public function __construct(private Uploads $uploads)
    {
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function __invoke(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return new JsonResponse(null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $key = $this->uploads->keep($file, $request->request->getString('field'), $request->getSession()->getId());

        return null === $key ? new JsonResponse(null, Response::HTTP_TOO_MANY_REQUESTS) : new JsonResponse(['key' => $key]);
    }
}
