<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions;

use AdzerCMS\Contracts\Seo\Page;
use AdzerCMS\Contracts\Seo\Pages;
use AdzerCMS\Extensions\Controller\CatalogController;
use AdzerCMS\Extensions\Entity\Extension;
use AdzerCMS\Extensions\Entity\ExtensionCategory;

use function Symfony\Component\Translation\t;

final readonly class ExtensionsPages implements Pages
{
    public function pages(): array
    {
        return [new Page(CatalogController::PAGE, t('extensions.catalog.title'), sources: [Extension::class, ExtensionCategory::class])];
    }
}
