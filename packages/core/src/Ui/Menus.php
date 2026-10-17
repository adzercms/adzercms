<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use AdzerCMS\Contracts\Ui\Area;
use AdzerCMS\Contracts\Ui\Menu;
use AdzerCMS\Contracts\Ui\MenuItem;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Twig\Attribute\AsTwigFunction;

final class Menus implements ResetInterface
{
    /** @var array<string, list<array{group: ?TranslatableInterface, items: list<array{item: MenuItem, active: bool}>}>> */
    private array $built = [];

    /** @param iterable<Menu> $menus */
    public function __construct(
        #[AutowireIterator(Menu::class)]
        private readonly iterable $menus,
        private readonly RequestStack $requests,
    ) {
    }

    /** @return list<array{group: ?TranslatableInterface, items: list<array{item: MenuItem, active: bool}>}> */
    #[AsTwigFunction('menu')]
    public function of(string $area): array
    {
        return $this->built[$area] ??= $this->build(Area::from($area));
    }

    /** @return list<array{item: MenuItem, active: bool}> */
    #[AsTwigFunction('bar')]
    public function bar(string $area): array
    {
        $entries = array_values(array_filter(
            array_merge(...array_column($this->of($area), 'items')),
            static fn (array $entry): bool => null !== $entry['item']->icon,
        ));
        usort($entries, static fn (array $left, array $right): int => $left['item']->weight <=> $right['item']->weight);

        return $entries;
    }

    public function reset(): void
    {
        $this->built = [];
    }

    /** @return list<array{group: ?TranslatableInterface, items: list<array{item: MenuItem, active: bool}>}> */
    private function build(Area $area): array
    {
        $route = (string) ($this->requests->getMainRequest()?->attributes->get('_route') ?? '');
        $items = [];
        foreach ($this->menus as $menu) {
            array_push($items, ...$menu->items($area));
        }
        usort($items, static fn (MenuItem $left, MenuItem $right): int => $left->weight <=> $right->weight);

        $labels = [];
        $entries = [];
        foreach ($items as $item) {
            $entry = ['item' => $item, 'active' => $route === $item->route || [] !== array_filter(
                $item->routes,
                static fn (string $prefix): bool => str_starts_with($route, $prefix),
            )];
            $index = array_search($item->group, $labels, false);
            if (false === $index) {
                $labels[] = $item->group;
                $entries[] = [$entry];
            } else {
                $entries[$index][] = $entry;
            }
        }

        return array_map(static fn (?TranslatableInterface $group, array $items): array => ['group' => $group, 'items' => $items], $labels, $entries);
    }
}
