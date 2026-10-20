<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Seo\Fixture;

use AdzerCMS\Core\Seo\Indexing;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class GuideController
{
    public function __construct(private Indexing $indexing)
    {
    }

    #[Route('/guide', name: 'fixture_guide', methods: ['GET'])]
    #[Route('/guide/{part}', name: 'fixture_guide_part', methods: ['GET'])]
    #[Route('/guide.pdf', name: 'fixture_guide_file', methods: ['GET'])]
    #[Route('/shelf/{name}.pdf', name: 'fixture_shelf_file', methods: ['GET'])]
    #[Route('/shelf/{name}', name: 'fixture_shelf', methods: ['GET'])]
    #[Route('/vault/{id}', name: 'fixture_vault', methods: ['GET'])]
    public function show(): Response
    {
        $head = $this->indexing->head();

        return new Response($head->canonical.' '.($head->indexed ? 'index' : 'noindex'));
    }
}
