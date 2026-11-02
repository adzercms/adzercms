<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology;

final readonly class Parse
{
    public function __construct(
        public string $tag,
        public string $normalForm,
        public float $score,
    ) {
    }
}
