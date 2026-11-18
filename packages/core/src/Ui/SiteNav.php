<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use AdzerCMS\Contracts\Ui\MenuItem;
use AdzerCMS\Contracts\Ui\SiteLink;
use AdzerCMS\Contracts\Ui\SiteMenu;
use AdzerCMS\Contracts\Ui\SiteSections;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

final readonly class SiteNav
{
    private ?SiteMenu $menu;

    /**
     * @param iterable<SiteSections> $sections
     * @param iterable<SiteMenu>     $menus
     */
    public function __construct(
        #[AutowireIterator(SiteSections::class)]
        private iterable $sections,
        #[AutowireIterator(SiteMenu::class)]
        iterable $menus,
        private UrlGeneratorInterface $urls,
        private RequestStack $requests,
        private TranslatorInterface $translator,
    ) {
        $this->menu = [...$menus][0] ?? null;
    }

    /** @return list<SiteLink> */
    #[AsTwigFunction('site_menu')]
    public function items(): array
    {
        $sections = $this->sections();

        return $this->menu?->arrange($sections) ?? array_values($sections);
    }

    /** @return array<string, SiteLink> */
    public function sections(): array
    {
        $items = [];
        foreach ($this->sections as $declared) {
            array_push($items, ...$declared->sections());
        }
        usort($items, static fn (MenuItem $left, MenuItem $right): int => $left->weight <=> $right->weight);
        $current = Menus::current($items, (string) ($this->requests->getMainRequest()?->attributes->get('_route') ?? ''));

        $links = [];
        foreach ($items as $item) {
            $links[$item->route] = new SiteLink($item->label->trans($this->translator), $this->urls->generate($item->route), $item === $current);
        }

        return $links;
    }
}
