<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology\Unit;

use AdzerCMS\Morphology\Analysis;
use AdzerCMS\Morphology\Seen;
use AdzerCMS\Morphology\Tag;

final readonly class UnknownUnit implements Unit
{
    private Tag $tag;

    public function __construct()
    {
        $this->tag = new Tag('UNKN');
    }

    public function parse(string $word, string $lower, Seen $seen): array
    {
        return $seen->empty() ? [new Analysis($lower, $this->tag, $lower, 1.0, null)] : [];
    }
}
