<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Ui;

final readonly class Cell
{
    public function __construct(
        public string $text,
        public int $sign = 0,
    ) {
    }
}
