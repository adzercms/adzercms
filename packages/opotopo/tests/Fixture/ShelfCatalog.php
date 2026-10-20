<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo\Tests\Fixture;

use AdzerCMS\Opotopo\Catalog;
use AdzerCMS\Opotopo\Records;

final class ShelfCatalog implements Catalog
{
    public const NAME = 'shelf';

    /** @var array<string, array<string, mixed>> */
    public array $rows = [];

    public function __construct(private readonly Records $records)
    {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function mapping(): array
    {
        return [
            'fields' => ['sku' => 'sku', 'title' => 'title', 'price' => 'price'],
            'category' => 'category',
            'images' => ['cover' => 'cover'],
        ];
    }

    public function records(): iterable
    {
        foreach ($this->rows as $key => $row) {
            yield $this->records->record((string) $key, $row);
        }
    }
}
