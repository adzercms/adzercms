<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Contracts\Ui\Fragment;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Repository\PageRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SiteNav implements Fragment
{
    public function __construct(
        private PageRepository $pages,
        private Site $site,
        private RequestStack $requests,
    ) {
    }

    public function place(): string
    {
        return self::SITE_NAV;
    }

    public function fragment(?int $subject): ?array
    {
        $current = $this->requests->getMainRequest()?->getPathInfo();
        $items = array_map(fn (Page $page): array => [
            'label' => $page->menuLabel(),
            'url' => $url = $this->site->url($page, UrlGeneratorInterface::ABSOLUTE_PATH),
            'current' => rawurldecode($url) === $current,
        ], $this->pages->site(menu: true));

        return [] === $items ? null : ['template' => '@Pages/_nav.html.twig', 'context' => ['items' => $items]];
    }
}
