<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology;

use AdzerCMS\Morphology\Dawg\Dawg;

final readonly class ProbabilityEstimator
{
    private const int MULTIPLIER = 1000000;

    public function __construct(private Dawg $probabilities)
    {
    }

    /**
     * @param list<Analysis> $analyses
     *
     * @return list<Analysis>
     */
    public function apply(string $lower, array $analyses): array
    {
        $probabilities = array_map(
            fn (Analysis $analysis): float => ($this->probabilities->find($lower.':'.$analysis->tag->value) ?? 0) / self::MULTIPLIER,
            $analyses,
        );
        if ([] === array_filter($probabilities)) {
            if ([] === $analyses) {
                return [];
            }
            $factor = 1.0 / self::sum(array_map(static fn (Analysis $analysis): float => $analysis->score, $analyses));

            return array_map(static fn (Analysis $analysis): Analysis => $analysis->scored($analysis->score * $factor), $analyses);
        }
        $scored = array_map(static fn (Analysis $analysis, float $probability): Analysis => $analysis->scored($probability), $analyses, $probabilities);
        usort($scored, static fn (Analysis $a, Analysis $b): int => $b->score <=> $a->score);

        return $scored;
    }

    /**
     * @param list<float> $values
     */
    private static function sum(array $values): float
    {
        $sum = $compensation = 0.0;
        foreach ($values as $value) {
            $total = $sum + $value;
            $compensation += abs($sum) >= abs($value) ? ($sum - $total) + $value : ($value - $total) + $sum;
            $sum = $total;
        }

        return 0.0 !== $compensation && is_finite($compensation) ? $sum + $compensation : $sum;
    }
}
