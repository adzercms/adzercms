<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology\Unit;

use AdzerCMS\Morphology\Seen;

final readonly class UnknownPrefixUnit implements Unit
{
    private const float SCORE = 0.5;
    private const int MIN_REMAINDER = 3;
    private const int MAX_PREFIX_LENGTH = 5;

    public function __construct(private DictionaryUnit $dictionary)
    {
    }

    public function parse(string $word, string $lower, Seen $seen): array
    {
        $analyses = [];
        for ($length = 1, $max = min(self::MAX_PREFIX_LENGTH, mb_strlen($lower) - self::MIN_REMAINDER); $length <= $max; ++$length) {
            $prefix = mb_substr($lower, 0, $length);
            $remainder = mb_substr($lower, $length);
            foreach ($this->dictionary->parse($remainder, $remainder, $seen) as $analysis) {
                if ($analysis->tag->productive && $seen->add($prefix.$analysis->word, $analysis->tag, $analysis->paradigm)) {
                    $analyses[] = $analysis->prefixed($prefix, self::SCORE);
                }
            }
        }

        return $analyses;
    }
}
