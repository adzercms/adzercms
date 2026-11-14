<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Seo\Fixture;

use AdzerCMS\Core\Seo\Indexing;
use AdzerCMS\Core\Site\Hosts;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class GuideController
{
    public function __construct(private Indexing $indexing)
    {
    }

    #[Route('/guide', name: 'fixture_guide', defaults: [Hosts::ROLE => Hosts::SITE], methods: ['GET'])]
    #[Route('/guide/{part}', name: 'fixture_guide_part', defaults: [Hosts::ROLE => Hosts::SITE], methods: ['GET'])]
    #[Route('/guide.pdf', name: 'fixture_guide_file', defaults: [Hosts::ROLE => Hosts::SITE], methods: ['GET'])]
    #[Route('/shelf/{name}.pdf', name: 'fixture_shelf_file', defaults: [Hosts::ROLE => Hosts::SITE], methods: ['GET'])]
    #[Route('/shelf/{name}', name: 'fixture_shelf', defaults: [Hosts::ROLE => Hosts::SITE], methods: ['GET'])]
    #[Route('/vault/{id}', name: 'fixture_vault', methods: ['GET'])]
    public function show(): Response
    {
        $head = $this->indexing->head();

        return new Response($head->canonical.' '.($head->indexed ? 'index' : 'noindex'));
    }
}
