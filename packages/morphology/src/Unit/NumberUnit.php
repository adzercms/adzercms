<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology\Unit;

use AdzerCMS\Morphology\Tag;

final readonly class NumberUnit extends ShapeUnit
{
    private const int INTEGER_MAX_DIGITS = 4300;

    private Tag $integer;
    private Tag $real;

    public function __construct()
    {
        $this->integer = new Tag('NUMB,intg');
        $this->real = new Tag('NUMB,real');
    }

    protected function shape(string $word): ?Tag
    {
        if (1 === preg_match('/^\p{Nd}+$/Du', $word)) {
            return mb_strlen($word) <= self::INTEGER_MAX_DIGITS ? $this->integer : $this->real;
        }

        return 1 === preg_match('/^(?:\p{Nd}+[eE]\p{Nd}+|[nN][aA][nN]|[iI][nN][fF](?:[iI][nN][iI][tT][yY])?)$/Du', $word) ? $this->real : null;
    }
}
