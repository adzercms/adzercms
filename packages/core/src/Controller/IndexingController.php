<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Controller;

use AdzerCMS\Core\Seo\Indexing;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class IndexingController
{
    public function __construct(private Indexing $indexing)
    {
    }

    #[Route('/robots.txt', name: Indexing::ROBOTS, methods: ['GET'])]
    public function robots(): Response
    {
        return new Response($this->indexing->robots(), headers: ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    #[Route('/sitemap.xml', name: Indexing::SITEMAP, methods: ['GET'])]
    public function sitemap(): Response
    {
        if (!$this->indexing->on()) {
            throw new NotFoundHttpException();
        }

        return new Response($this->indexing->sitemap(), headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
