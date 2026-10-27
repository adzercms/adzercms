<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Ui;

use Symfony\Component\HttpFoundation\Request;

final readonly class Order
{
    public const FIELD = 'ids';

    /** @param list<string> $fields */
    private function __construct(public string $route, public array $fields)
    {
    }

    public static function of(string $route, string ...$fields): self
    {
        return new self($route, array_values($fields));
    }

    /** @return list<int> */
    public static function moved(Request $request): array
    {
        $ids = [];
        foreach ($request->request->all(self::FIELD) as $id) {
            if (\is_string($id) && ctype_digit($id)) {
                $ids[(int) $id] = (int) $id;
            }
        }

        return array_values($ids);
    }

    /**
     * @template T of Placed
     *
     * @param list<T>                   $ordered
     * @param list<int>                 $moved
     * @param (\Closure(T): mixed)|null $group
     */
    public static function place(array $ordered, array $moved, ?\Closure $group = null): void
    {
        $records = [];
        foreach ($ordered as $record) {
            $records[(int) $record->getId()] = $record;
        }
        if (null !== $group) {
            $first = $records[array_values(array_intersect($moved, array_keys($records)))[0] ?? 0] ?? null;
            if (null === $first) {
                return;
            }
            $records = array_filter($records, static fn (Placed $record): bool => $group($record) === $group($first));
        }
        foreach (self::arrange(array_keys($records), $moved) as $index => $id) {
            $records[$id]->place($index + 1);
        }
    }

    /**
     * @param list<int> $group
     * @param list<int> $moved
     *
     * @return list<int>
     */
    private static function arrange(array $group, array $moved): array
    {
        $moved = array_values(array_unique(array_intersect($moved, $group)));
        $taken = array_flip($moved);
        $next = 0;
        $arranged = [];
        foreach ($group as $id) {
            $arranged[] = isset($taken[$id]) ? $moved[$next++] : $id;
        }

        return $arranged;
    }
}
