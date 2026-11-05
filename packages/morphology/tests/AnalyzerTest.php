<?php

declare(strict_types=1);

namespace AdzerCMS\Morphology\Tests;

use AdzerCMS\Morphology\Analyzer;
use AdzerCMS\Morphology\Parse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class AnalyzerTest extends TestCase
{
    private static ?Analyzer $analyzer = null;

    /**
     * @return iterable<string, array{string, list<array{string, string, float}>}>
     */
    public static function references(): iterable
    {
        /** @var array{parses: array<string, list<array{string, string, float}>>} $reference */
        $reference = json_decode((string) file_get_contents(__DIR__.'/reference.json'), true, flags: \JSON_THROW_ON_ERROR);
        foreach ($reference['parses'] as $token => $parses) {
            yield (string) $token => [(string) $token, $parses];
        }
    }

    /**
     * @param list<array{string, string, float}> $parses
     */
    #[DataProvider('references')]
    #[Group('ACMS-MORPH-0002')]
    #[Group('ACMS-MORPH-0005')]
    public function testParsesAsReference(string $token, array $parses): void
    {
        $actual = array_map(static fn (Parse $parse): array => [$parse->tag, $parse->normalForm, $parse->score], self::analyzer()->parse($token));

        self::assertSame($parses, $actual);
    }

    #[TestWith([''])]
    #[TestWith(['кто-то'])]
    #[TestWith(['a b'])]
    #[TestWith(['1,5'])]
    #[TestWith(["сло\u{301}во"])]
    #[TestWith(["\xff"])]
    #[Group('ACMS-MORPH-0003')]
    public function testRejectsTokenOutOfScope(string $token): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::analyzer()->parse($token);
    }

    private static function analyzer(): Analyzer
    {
        return self::$analyzer ??= new Analyzer();
    }
}
