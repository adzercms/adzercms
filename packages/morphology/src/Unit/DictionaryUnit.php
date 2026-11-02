<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology\Unit;

use AdzerCMS\Morphology\Analysis;
use AdzerCMS\Morphology\Dictionary;
use AdzerCMS\Morphology\Russian;
use AdzerCMS\Morphology\Seen;

final readonly class DictionaryUnit implements Unit
{
    public function __construct(private Dictionary $dictionary)
    {
    }

    public function parse(string $word, string $lower, Seen $seen): array
    {
        $analyses = [];
        foreach ($this->dictionary->words->similarItems($lower, Russian::SUBSTITUTES) as [$fixed, $values]) {
            foreach ($values as [$paradigm, $form]) {
                $tag = $this->dictionary->tag($paradigm, $form);
                $analyses[] = new Analysis($fixed, $tag, $this->dictionary->normalForm($paradigm, $form, $fixed), 1.0, $paradigm);
            }
        }

        return $analyses;
    }
}
