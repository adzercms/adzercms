<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Ui;

use AdzerCMS\Core\Ui\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[Group('ACMS-CORE-0037')]
    #[DataProvider('entered')]
    public function testEnteredSumIsDecimalNotation(string $entered, ?string $sum): void
    {
        self::assertSame($sum, Money::parse($entered));
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function entered(): iterable
    {
        yield 'grouped with comma' => ["1 500,5\u{a0}", '1500.50'];
        yield 'signed with point' => ['-0.75', '-0.75'];
        yield 'exponent' => ['1e3', null];
        yield 'hexadecimal' => ['0x1A', null];
        yield 'words' => ['free', null];
    }
}
