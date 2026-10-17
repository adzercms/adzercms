<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use AdzerCMS\Contracts\Ui\Tab;
use AdzerCMS\Contracts\Ui\Tabs;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;

final readonly class PageTabs
{
    /** @param iterable<Tabs> $owners */
    public function __construct(
        #[AutowireIterator(Tabs::class)]
        private iterable $owners,
    ) {
    }

    /** @return array<string, array{tab: Tab, owner: Tabs}> */
    public function of(string $page, ?int $subject): array
    {
        $found = [];
        foreach ($this->owners as $owner) {
            if ($owner->page() !== $page) {
                continue;
            }
            foreach ($owner->tabs($subject) as $tab) {
                if (isset($found[$tab->key])) {
                    throw new \LogicException(\sprintf('tab %s of page %s is declared twice', $tab->key, $page));
                }
                $found[$tab->key] = ['tab' => $tab, 'owner' => $owner];
            }
        }
        uasort($found, static fn (array $left, array $right): int => $left['tab']->weight <=> $right['tab']->weight);

        return $found;
    }

    /**
     * @param array<string, array{tab: Tab, owner: Tabs}> $tabs
     * @param array<string, mixed>                        $sticky
     *
     * @return array{template: string, context: array<string, mixed>}|null
     */
    public function render(array $tabs, string $key, ?int $subject, Request $request, array $sticky): ?array
    {
        return isset($tabs[$key]) ? $tabs[$key]['owner']->tab($key, $subject, $request, $sticky) : null;
    }
}
