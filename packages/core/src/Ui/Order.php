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
     * @param list<Placed> $group
     * @param list<int>    $moved
     */
    public static function place(array $group, array $moved): void
    {
        $records = [];
        foreach ($group as $record) {
            $records[(int) $record->getId()] = $record;
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
