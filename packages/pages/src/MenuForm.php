<?php

declare(strict_types=1);

namespace AdzerCMS\Pages;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Core\Ui\SiteNav;
use AdzerCMS\Pages\Entity\MenuEntry;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Repository\MenuEntryRepository;
use AdzerCMS\Pages\Repository\PageRepository;
use AdzerCMS\Pages\Type\Button;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;

use function Symfony\Component\Translation\t;

final readonly class MenuForm
{
    public const TARGET = 'target';
    public const LABEL = 'label';
    public const ADDRESS = 'address';
    public const LINK = 'link';

    private const PAGE = 'page:';
    private const SECTION = 'section:';

    public function __construct(
        private MenuEntryRepository $entries,
        private PageRepository $pages,
        private SiteNav $nav,
        private EntityManagerInterface $em,
    ) {
    }

    public static function target(?MenuEntry $entry): string
    {
        $page = $entry?->getPage();
        $section = $entry?->getSection();

        return match (true) {
            null === $entry => '',
            null !== $page => self::PAGE.$page->getId(),
            null !== $section => self::SECTION.$section,
            default => self::LINK,
        };
    }

    /** @return array{pages: array<string, string>, sections: array<string, string>} */
    public function choices(?MenuEntry $entry): array
    {
        $others = array_filter($this->entries->ordered(), static fn (MenuEntry $other): bool => $other !== $entry);
        $taken = array_map(self::target(...), $others);
        $choices = ['pages' => [], 'sections' => []];
        foreach ($this->pages->ordered() as $page) {
            $choices['pages'][self::PAGE.$page->getId()] = $page->getTitle();
        }
        foreach ($this->nav->sections() as $route => $link) {
            $choices['sections'][self::SECTION.$route] = $link->label;
        }

        return array_map(static fn (array $group): array => array_diff_key($group, array_flip($taken)), $choices);
    }

    /**
     * @param InputBag<string> $input
     *
     * @return MenuEntry|array<string, Refusal>
     */
    public function save(?MenuEntry $entry, InputBag $input): MenuEntry|array
    {
        $target = $input->getString(self::TARGET);
        $choices = $this->choices($entry);
        if (self::LINK === $target) {
            $label = trim($input->getString(self::LABEL));
            $address = trim($input->getString(self::ADDRESS));
            $refusals = array_filter([
                self::LABEL => '' === $label || mb_strlen($label) > Page::MENU ? new Refusal(t('pages.refusal.menu_link_label', ['limit' => Page::MENU])) : null,
                self::ADDRESS => !Button::valid($address) || mb_strlen($address) > MenuEntry::ADDRESS ? new Refusal(t('pages.refusal.link', ['field' => t('pages.menu.address')])) : null,
            ]);
            if ([] !== $refusals) {
                return $refusals;
            }
            $kept = $entry?->toLink($label, $address) ?? MenuEntry::ofLink($label, $address);
        } elseif (isset($choices['pages'][$target]) && ($page = $this->pages->find((int) substr($target, \strlen(self::PAGE)))) instanceof Page) {
            $kept = $entry?->toPage($page) ?? MenuEntry::ofPage($page);
        } elseif (isset($choices['sections'][$target])) {
            $route = substr($target, \strlen(self::SECTION));
            $kept = $entry?->toSection($route) ?? MenuEntry::ofSection($route);
        } else {
            return [self::TARGET => new Refusal(t('pages.refusal.menu_target'))];
        }

        if (null === $entry) {
            $kept->place($this->entries->count() + 1);
            $this->em->persist($kept);
        }
        $this->em->flush();

        return $kept;
    }

    /** @param list<int> $moved */
    public function arrange(array $moved): void
    {
        Order::place($this->entries->ordered(), $moved);
        $this->em->flush();
    }

    public function drop(MenuEntry $entry): void
    {
        $this->em->remove($entry);
        $this->em->flush();
    }
}
