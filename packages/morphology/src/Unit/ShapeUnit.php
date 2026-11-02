<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology\Unit;

use AdzerCMS\Morphology\Analysis;
use AdzerCMS\Morphology\Seen;
use AdzerCMS\Morphology\Tag;

abstract readonly class ShapeUnit implements Unit
{
    private const float SCORE = 0.9;

    final public function parse(string $word, string $lower, Seen $seen): array
    {
        $tag = $this->shape($word);

        return null === $tag ? [] : [new Analysis($lower, $tag, $lower, self::SCORE, null)];
    }

    abstract protected function shape(string $word): ?Tag;
}
