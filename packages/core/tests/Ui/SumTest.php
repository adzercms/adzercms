<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Ui;

use AdzerCMS\Core\Tests\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Twig\Environment;

final class SumTest extends KernelTestCase
{
    use Container;

    #[Group('ACMS-CORE-0037')]
    #[DataProvider('sums')]
    public function testSumGoesInTheLanguageOfTheReaderWithKopecksOnlyWhenAsked(string $locale, string $value, string $whole, string $precise): void
    {
        self::service(LocaleAwareInterface::class, 'translator')->setLocale($locale);

        $page = self::service(Environment::class, 'twig')->createTemplate('{{ value|money }}|{{ value|precise }}')->render(['value' => $value]);

        self::assertSame($whole.'|'.$precise, $page);
    }

    /** @return iterable<string, array{string, string, string, string}> */
    public static function sums(): iterable
    {
        yield 'ru with kopecks' => ['ru', '1234567.50', "1\u{a0}234\u{a0}568\u{a0}₽", "1\u{a0}234\u{a0}567,50\u{a0}₽"];
        yield 'ru whole' => ['ru', '1500.00', "1\u{a0}500\u{a0}₽", "1\u{a0}500\u{a0}₽"];
        yield 'en with kopecks' => ['en', '1500.50', '₽1,501', '₽1,500.50'];
    }
}
