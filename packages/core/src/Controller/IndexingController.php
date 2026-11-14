<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Controller;

use AdzerCMS\Core\Http\TextResponse;
use AdzerCMS\Core\Seo\Indexing;
use AdzerCMS\Core\Seo\IndexNow;
use AdzerCMS\Core\Site\Hosts;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class IndexingController
{
    public function __construct(private Indexing $indexing, private IndexNow $indexNow)
    {
    }

    #[Route('/{key}.txt', name: IndexNow::KEY, requirements: ['key' => IndexNow::KEY_PATTERN], defaults: [Hosts::ROLE => Hosts::SITE], methods: ['GET'])]
    public function indexNowKey(string $key): Response
    {
        if (!$this->indexNow->owns($key)) {
            throw new NotFoundHttpException();
        }

        return new TextResponse($key);
    }

    #[Route('/robots.txt', name: Indexing::ROBOTS, defaults: [Hosts::ROLE => Hosts::SHARED], methods: ['GET'])]
    public function robots(): Response
    {
        return new TextResponse($this->indexing->robots());
    }

    #[Route('/sitemap.xml', name: Indexing::SITEMAP, defaults: [Hosts::ROLE => Hosts::SHARED], methods: ['GET'])]
    public function sitemap(): Response
    {
        if (!$this->indexing->on()) {
            throw new NotFoundHttpException();
        }

        return new Response($this->indexing->sitemap(), headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
