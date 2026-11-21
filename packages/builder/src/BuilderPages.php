<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Builder\Controller\CatalogController;
use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectVersion;
use AdzerCMS\Contracts\Seo\Page;
use AdzerCMS\Contracts\Seo\Pages;

use function Symfony\Component\Translation\t;

final readonly class BuilderPages implements Pages
{
    public function pages(): array
    {
        return [new Page(CatalogController::CATALOG, t('builder.catalog.title'), sources: [Project::class, ProjectVersion::class])];
    }
}
