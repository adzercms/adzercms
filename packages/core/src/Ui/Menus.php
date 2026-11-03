<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use AdzerCMS\Contracts\Ui\Area;
use AdzerCMS\Contracts\Ui\Menu;
use AdzerCMS\Contracts\Ui\MenuItem;
use AdzerCMS\Core\Module\Bundles;
use AdzerCMS\Core\Module\Registry;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Twig\Attribute\AsTwigFunction;

final class Menus implements ResetInterface
{
    /** @var array<string, list<array{group: ?TranslatableInterface, items: list<array{item: MenuItem, active: bool, module: ?string, nested: bool}>}>> */
    private array $built = [];

    /** @param iterable<Menu> $menus */
    public function __construct(
        #[AutowireIterator(Menu::class)]
        private readonly iterable $menus,
        private readonly RequestStack $requests,
        private readonly Bundles $bundles,
    ) {
    }

    /** @return list<array{group: ?TranslatableInterface, items: list<array{item: MenuItem, active: bool, module: ?string, nested: bool}>}> */
    #[AsTwigFunction('menu')]
    public function of(string $area): array
    {
        return $this->built[$area] ??= $this->build(Area::from($area));
    }

    /** @return list<array{item: MenuItem, active: bool, module: ?string, nested: bool}> */
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

    /** @return list<array{group: ?TranslatableInterface, items: list<array{item: MenuItem, active: bool, module: ?string, nested: bool}>}> */
    private function build(Area $area): array
    {
        $route = (string) ($this->requests->getMainRequest()?->attributes->get('_route') ?? '');
        $items = [];
        $modules = [];
        foreach ($this->menus as $menu) {
            $bundle = $this->bundles->find($menu);
            foreach ($menu->items($area) as $item) {
                $items[] = $item;
                $modules[spl_object_id($item)] = null === $bundle ? null : Registry::lowerKey($bundle);
            }
        }
        usort($items, static fn (MenuItem $left, MenuItem $right): int => $left->weight <=> $right->weight);

        $parents = array_fill_keys(array_map(static fn (MenuItem $item): string => $item->route, array_filter($items, static fn (MenuItem $item): bool => null === $item->parent)), true);
        $nested = [];
        foreach ($items as $item) {
            if (null !== $item->parent && isset($parents[$item->parent])) {
                $nested[$item->parent][] = $item;
            }
        }

        $current = self::current($items, $route);
        $entry = static fn (MenuItem $item, bool $nested): array => ['item' => $item, 'active' => $item === $current, 'module' => $modules[spl_object_id($item)], 'nested' => $nested];
        $labels = [];
        $entries = [];
        foreach ($items as $item) {
            if (isset($parents[$item->parent ?? ''])) {
                continue;
            }
            $index = array_search($item->group, $labels, false);
            if (false === $index) {
                $labels[] = $item->group;
                $index = array_key_last($labels);
                $entries[$index] = [];
            }
            $entries[$index][] = $entry($item, false);
            foreach ($nested[$item->route] ?? [] as $child) {
                $entries[$index][] = $entry($child, true);
            }
        }

        return array_map(static fn (?TranslatableInterface $group, array $items): array => ['group' => $group, 'items' => $items], $labels, $entries);
    }

    /** @param list<MenuItem> $items */
    private static function current(array $items, string $route): ?MenuItem
    {
        $current = null;
        $longest = 0;
        foreach ($items as $item) {
            if ($route === $item->route) {
                return $item;
            }
            foreach ($item->routes as $prefix) {
                if (\strlen($prefix) > $longest && str_starts_with($route, $prefix)) {
                    [$current, $longest] = [$item, \strlen($prefix)];
                }
            }
        }

        return $current;
    }
}
