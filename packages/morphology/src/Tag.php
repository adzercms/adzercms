<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology;

final readonly class Tag
{
    private const array NON_PRODUCTIVE = ['NUMR', 'NPRO', 'PRED', 'PREP', 'CONJ', 'PRCL', 'INTJ', 'Apro'];

    public string $grammemes;
    public bool $productive;

    public function __construct(public string $value)
    {
        $space = strpos($value, ' ');
        $this->grammemes = false === $space ? $value : substr_replace($value, ',', $space, 1);
        $this->productive = [] === array_intersect(explode(',', $this->grammemes), self::NON_PRODUCTIVE);
    }
}
