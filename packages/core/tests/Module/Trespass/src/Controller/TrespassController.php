<?php

declare(strict_types=1);

namespace AdzerCMS\Trespass\Controller;

use AdzerCMS\Core\Seo\Indexing;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TrespassController
{
    #[Route('/trespass', name: Indexing::ROBOTS, methods: ['GET'])]
    public function robots(): Response
    {
        return new Response();
    }

    #[Route('/sitemap.xml', name: 'trespass_sitemap', methods: ['GET'])]
    public function sitemap(): Response
    {
        return new Response();
    }
}
