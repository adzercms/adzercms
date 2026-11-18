<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Contracts\Ui\SiteLink;
use AdzerCMS\Contracts\Ui\SiteMenu;
use AdzerCMS\Pages\Entity\MenuEntry;
use AdzerCMS\Pages\Repository\MenuEntryRepository;
use AdzerCMS\Pages\Type\Button;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PageMenu implements SiteMenu
{
    public function __construct(
        private MenuEntryRepository $entries,
        private Site $site,
        private RequestStack $requests,
    ) {
    }

    public function arrange(array $sections): array
    {
        return array_values(array_filter(array_map(fn (MenuEntry $entry): ?SiteLink => $this->link($entry, $sections), $this->entries->ordered())));
    }

    /** @param array<string, SiteLink> $sections */
    public function link(MenuEntry $entry, array $sections): ?SiteLink
    {
        $page = $entry->getPage();
        $section = $entry->getSection();
        if (null !== $page) {
            $url = $this->site->url($page, UrlGeneratorInterface::ABSOLUTE_PATH);

            return $page->isPublished() ? new SiteLink($this->site->label($page), $url, rawurldecode($url) === $this->requests->getMainRequest()?->getPathInfo()) : null;
        }
        if (null !== $section) {
            return $sections[$section] ?? null;
        }
        $address = (string) $entry->getAddress();

        return new SiteLink((string) $entry->getLabel(), $address, external: Button::external($address));
    }
}
