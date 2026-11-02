<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology\Unit;

use AdzerCMS\Morphology\Analysis;
use AdzerCMS\Morphology\Dictionary;
use AdzerCMS\Morphology\Russian;
use AdzerCMS\Morphology\Seen;

final readonly class KnownSuffixUnit implements Unit
{
    private const float SCORE = 0.5;
    private const int MIN_WORD_LENGTH = 4;

    public function __construct(private Dictionary $dictionary)
    {
    }

    public function parse(string $word, string $lower, Seen $seen): array
    {
        if (mb_strlen($word) < self::MIN_WORD_LENGTH) {
            return [];
        }
        $totals = array_fill_keys(array_keys($this->dictionary->prefixes), 1);
        $found = [];
        foreach (array_reverse($this->dictionary->prefixes, true) as $id => $prefix) {
            if (!str_starts_with($lower, $prefix)) {
                continue;
            }
            for ($length = $this->dictionary->maxSuffixLength; $length >= 1; --$length) {
                $start = mb_substr($lower, 0, -$length);
                foreach ($this->dictionary->predictions[$id]->similarItems(mb_substr($lower, -$length), Russian::SUBSTITUTES) as [$suffix, $values]) {
                    $fixed = $start.$suffix;
                    foreach ($values as [$count, $paradigm, $form]) {
                        $tag = $this->dictionary->tag($paradigm, $form);
                        if (!$tag->productive) {
                            continue;
                        }
                        $totals[$id] += $count;
                        if ($seen->add($fixed, $tag, $paradigm)) {
                            $found[] = [$id, $count, new Analysis($fixed, $tag, $this->dictionary->normalForm($paradigm, $form, $fixed), 0.0, $paradigm)];
                        }
                    }
                }
                if ($totals[$id] > 1) {
                    break;
                }
            }
        }
        $analyses = array_map(static fn (array $item): Analysis => $item[2]->scored($item[1] / $totals[$item[0]] * self::SCORE), $found);
        usort($analyses, static fn (Analysis $a, Analysis $b): int => $b->score <=> $a->score);

        return $analyses;
    }
}
