<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Contracts\Ui\SiteLink;
use AdzerCMS\Contracts\Ui\SiteMenu;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Repository\PageRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PageMenu implements SiteMenu
{
    public function __construct(
        private PageRepository $pages,
        private Site $site,
        private RequestStack $requests,
    ) {
    }

    public function arrange(array $sections): array
    {
        $current = $this->requests->getMainRequest()?->getPathInfo();
        $pages = array_map(fn (Page $page): SiteLink => new SiteLink(
            $page->menuLabel(),
            $url = $this->site->url($page, UrlGeneratorInterface::ABSOLUTE_PATH),
            rawurldecode($url) === $current,
        ), $this->pages->site(menu: true));

        return [...$pages, ...array_values($sections)];
    }
}
