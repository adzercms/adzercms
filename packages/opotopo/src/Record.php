<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo;

use Opotopo\SellerApi\Catalog\ItemRecord;
use Opotopo\SellerApi\Files\FileSource;

final readonly class Record implements ItemRecord
{
    /** @param array<string, mixed> $values */
    public function __construct(
        private string $key,
        private array $values,
        private string $root,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function value(string $path): mixed
    {
        return $this->values[$path] ?? null;
    }

    public function file(mixed $entry): FileSource
    {
        return new LocalFile($this->root, \is_scalar($entry) ? (string) $entry : '');
    }
}
