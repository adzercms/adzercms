<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology;

use AdzerCMS\Morphology\Unit\DictionaryUnit;
use AdzerCMS\Morphology\Unit\InitialsUnit;
use AdzerCMS\Morphology\Unit\KnownPrefixUnit;
use AdzerCMS\Morphology\Unit\KnownSuffixUnit;
use AdzerCMS\Morphology\Unit\LatinUnit;
use AdzerCMS\Morphology\Unit\NumberUnit;
use AdzerCMS\Morphology\Unit\RomanNumberUnit;
use AdzerCMS\Morphology\Unit\Unit;
use AdzerCMS\Morphology\Unit\UnknownPrefixUnit;
use AdzerCMS\Morphology\Unit\UnknownUnit;
use Composer\InstalledVersions;

final class Analyzer
{
    private const string DICTIONARY = 'adzercms/morphology-ru';

    /** @var list<list<Unit>> */
    private array $groups = [];
    private ProbabilityEstimator $estimator;

    /**
     * @return list<Parse>
     */
    public function parse(string $word): array
    {
        if (1 !== preg_match('/^[\p{L}\p{N}]+$/Du', $word)) {
            throw new \InvalidArgumentException(\sprintf('The token "%s" is not letters and digits', $word));
        }

        return array_map(
            static fn (Analysis $analysis): Parse => new Parse($analysis->tag->value, $analysis->normalForm, $analysis->score),
            $this->analyze($word),
        );
    }

    /**
     * @return list<Analysis>
     */
    private function analyze(string $word): array
    {
        if ([] === $this->groups) {
            $this->load();
        }
        $lower = mb_strtolower($word);
        $seen = new Seen();
        $analyses = [];
        foreach ($this->groups as $group) {
            foreach ($group as $unit) {
                array_push($analyses, ...$unit->parse($word, $lower, $seen));
            }
            if ([] !== $analyses) {
                break;
            }
        }

        return $this->estimator->apply($lower, $analyses);
    }

    private function load(): void
    {
        $dictionary = new Dictionary(InstalledVersions::getInstallPath(self::DICTIONARY).'/data');
        $words = new DictionaryUnit($dictionary);
        $this->estimator = new ProbabilityEstimator($dictionary->probabilities);
        $this->groups = [
            [$words, new InitialsUnit(InitialsUnit::FIRST_NAME), new InitialsUnit(InitialsUnit::PATRONYMIC)],
            [new NumberUnit()],
            [new RomanNumberUnit(), new LatinUnit()],
            [new KnownPrefixUnit($this->analyze(...), Russian::KNOWN_PREFIXES)],
            [new UnknownPrefixUnit($words), new KnownSuffixUnit($dictionary)],
            [new UnknownUnit()],
        ];
    }
}
