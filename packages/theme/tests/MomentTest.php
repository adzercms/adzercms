<?php

declare(strict_types=1);

namespace AdzerCMS\Theme\Tests;

use AdzerCMS\Core\Tests\Container;
use AdzerCMS\Theme\ThemeExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

final class MomentTest extends KernelTestCase
{
    use Container;

    /** @return iterable<string, array{string}> */
    public static function languages(): iterable
    {
        yield 'en' => ['en'];
        yield 'ru' => ['ru'];
    }

    #[Group('ACMS-CORE-0933')]
    #[DataProvider('languages')]
    public function testMomentSpeaksLanguageOfTranslator(string $locale): void
    {
        $at = new \DateTimeImmutable('2026-10-20 15:07');
        $theme = self::service(Environment::class, 'twig')->getExtension(ThemeExtension::class);

        $cell = self::service(LocaleSwitcher::class)->runWithLocale($locale, static fn (): string => $theme->moment($at));

        self::assertSame(
            self::phrase('core.moment.day', ['at' => $at], $locale)."\n".$theme->caption(self::phrase('core.moment.time', ['at' => $at], $locale)),
            $cell,
        );
    }
}
