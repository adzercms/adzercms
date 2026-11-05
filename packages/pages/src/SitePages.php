<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Contracts\Seo\Page as SeoPage;
use AdzerCMS\Contracts\Seo\Pages;
use AdzerCMS\Pages\Controller\PageController;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PageBlock;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use AdzerCMS\Pages\Repository\PageRepository;

use function Symfony\Component\Translation\t;

final readonly class SitePages implements Pages
{
    public function __construct(
        private PageRepository $pages,
        private PageBlockRepository $blocks,
        private Blocks $types,
    ) {
    }

    public function pages(): array
    {
        $types = $this->blocks->types();

        return array_map(function (Page $page) use ($types): SeoPage {
            $sources = [Page::class, PageBlock::class];
            foreach ($types[(int) $page->getId()] ?? [] as $key) {
                $sources = [...$sources, ...($this->types->find($key)?->sources() ?? [])];
            }
            $root = Page::ROOT === $page->getPath();

            return new SeoPage(
                $root ? PageController::ROOT : PageController::PAGE,
                t('pages.page.title', ['title' => $page->getTitle()]),
                sources: array_values(array_unique($sources)),
                parameters: $root ? [] : ['path' => substr($page->getPath(), 1)],
            );
        }, $this->pages->site());
    }
}
