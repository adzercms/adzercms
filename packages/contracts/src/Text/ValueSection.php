<?php

declare(strict_types=1);

namespace AdzerCMS\Contracts\Text;

interface ValueSection
{
    /** @return array<string, string|int|float|list<string|int|float>> */
    public function values(): array;
}
