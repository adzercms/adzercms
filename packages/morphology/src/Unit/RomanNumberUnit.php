<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology\Unit;

use AdzerCMS\Morphology\Tag;

final readonly class RomanNumberUnit extends ShapeUnit
{
    private const string PATTERN = '/^[Mm]{0,4}(?:[Cc][Mm]|[Cc][Dd]|[Dd]?[Cc]{0,3})(?:[Xx][Cc]|[Xx][Ll]|[Ll]?[Xx]{0,3})(?:[Iiİı][Xx]|[Iiİı][Vv]|[Vv]?[Iiİı]{0,3})$/Du';

    private Tag $tag;

    public function __construct()
    {
        $this->tag = new Tag('ROMN');
    }

    protected function shape(string $word): ?Tag
    {
        return 1 === preg_match(self::PATTERN, $word) ? $this->tag : null;
    }
}
