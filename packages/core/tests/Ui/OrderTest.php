<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Ui;

use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Core\Ui\Placed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    /**
     * @param array<int, string> $groups
     * @param list<int>          $moved
     * @param array<int, int>    $expected
     */
    #[Group('ACMS-UI-0075')]
    #[DataProvider('moves')]
    public function testMovedIdsTakeTheirFormerPlacesOfTheirGroup(array $groups, array $moved, array $expected): void
    {
        $placed = new \ArrayObject();
        $records = array_map(static fn (int $id): Placed => new readonly class($id, $placed) implements Placed {
            /** @param \ArrayObject<int, int> $placed */
            public function __construct(private int $id, private \ArrayObject $placed)
            {
            }

            public function getId(): int
            {
                return $this->id;
            }

            public function place(int $position): void
            {
                $this->placed[$this->id] = $position;
            }
        }, array_keys($groups));

        Order::place($records, $moved, static fn (Placed $record): string => $groups[$record->getId()]);

        $positions = $placed->getArrayCopy();
        ksort($positions);
        self::assertSame($expected, $positions);
    }

    /** @return iterable<string, array{array<int, string>, list<int>, array<int, int>}> */
    public static function moves(): iterable
    {
        yield 'part of the group' => [[1 => '', 2 => '', 3 => '', 4 => '', 5 => ''], [5, 1, 3], [1 => 3, 2 => 2, 3 => 5, 4 => 4, 5 => 1]];
        yield 'id outside the records' => [[1 => '', 2 => '', 3 => ''], [3, 9, 1], [1 => 3, 2 => 2, 3 => 1]];
        yield 'group of the first moved id' => [[1 => 'a', 2 => 'b', 3 => 'a', 4 => 'b'], [9, 3, 4, 1], [1 => 2, 3 => 1]];
    }
}
