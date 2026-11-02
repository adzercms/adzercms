<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology\Unit;

use AdzerCMS\Morphology\Tag;

final readonly class LatinUnit extends ShapeUnit
{
    private Tag $tag;

    public function __construct()
    {
        $this->tag = new Tag('LATN');
    }

    protected function shape(string $word): ?Tag
    {
        if (!preg_match_all('/\p{L}/u', $word, $letters)) {
            return null;
        }
        foreach ($letters[0] as $letter) {
            if (!str_contains((string) \IntlChar::charName($letter), 'LATIN')) {
                return null;
            }
        }

        return $this->tag;
    }
}
