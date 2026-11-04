<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

final readonly class Cell
{
    /** @param list<self> $more */
    public function __construct(
        public string $text,
        public int $sign = 0,
        public bool $strong = false,
        public ?string $note = null,
        public ?string $warning = null,
        public array $more = [],
    ) {
    }
}
