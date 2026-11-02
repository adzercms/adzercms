<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology\Unit;

use AdzerCMS\Morphology\Analysis;
use AdzerCMS\Morphology\Seen;

final readonly class KnownPrefixUnit implements Unit
{
    private const float SCORE = 0.75;
    private const int MIN_REMAINDER = 3;

    /**
     * @param \Closure(string): list<Analysis> $analyze
     * @param list<string>                     $prefixes
     */
    public function __construct(private \Closure $analyze, private array $prefixes)
    {
    }

    public function parse(string $word, string $lower, Seen $seen): array
    {
        $prefixes = array_values(array_filter($this->prefixes, static fn (string $prefix): bool => str_starts_with($lower, $prefix)));
        usort($prefixes, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        $analyses = [];
        foreach ($prefixes as $prefix) {
            $remainder = substr($lower, \strlen($prefix));
            if (mb_strlen($remainder) < self::MIN_REMAINDER) {
                continue;
            }
            foreach (($this->analyze)($remainder) as $analysis) {
                if ($analysis->tag->productive && $seen->add($prefix.$analysis->word, $analysis->tag, $analysis->paradigm)) {
                    $analyses[] = $analysis->prefixed($prefix, self::SCORE);
                }
            }
        }

        return $analyses;
    }
}
