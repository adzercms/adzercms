<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Controller;

use AdzerCMS\Core\Showcase\Sections;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ShowcaseController
{
    public function __construct(private Sections $sections)
    {
    }

    #[Route('/showcase.json', name: Sections::ROUTE, methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return JsonResponse::fromJsonString($this->sections->json());
    }
}
