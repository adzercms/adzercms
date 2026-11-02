<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology\Unit;

use AdzerCMS\Morphology\Analysis;
use AdzerCMS\Morphology\Russian;
use AdzerCMS\Morphology\Seen;
use AdzerCMS\Morphology\Tag;

final readonly class InitialsUnit implements Unit
{
    public const string FIRST_NAME = 'NOUN,anim,%s,Sgtm,Name,Fixd,Abbr,Init sing,%s';
    public const string PATRONYMIC = 'NOUN,anim,%s,Sgtm,Patr,Fixd,Abbr,Init sing,%s';

    private const float SCORE = 0.1;
    private const array GENDERS = ['masc', 'femn'];
    private const array CASES = ['nomn', 'gent', 'datv', 'accs', 'ablt', 'loct'];

    /** @var array<string, true> */
    private array $letters;
    /** @var list<Tag> */
    private array $tags;

    public function __construct(string $pattern)
    {
        $this->letters = array_fill_keys(mb_str_split(Russian::INITIALS), true);
        $tags = [];
        foreach (self::GENDERS as $gender) {
            foreach (self::CASES as $case) {
                $tags[] = new Tag(\sprintf($pattern, $gender, $case));
            }
        }
        $this->tags = $tags;
    }

    public function parse(string $word, string $lower, Seen $seen): array
    {
        if (!isset($this->letters[$word])) {
            return [];
        }

        return array_map(static fn (Tag $tag): Analysis => new Analysis($lower, $tag, $lower, self::SCORE, null), $this->tags);
    }
}
