<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology\Unit;

use AdzerCMS\Morphology\Analysis;
use AdzerCMS\Morphology\Seen;

interface Unit
{
    /**
     * @return list<Analysis>
     */
    public function parse(string $word, string $lower, Seen $seen): array;
}
