<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Account;

use AdzerCMS\Core\Account\Consents;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Text\Markup;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Contracts\Service\ResetInterface;

final class EditionsTest extends SchemaTestCase
{
    use Configuration;

    private const TEXT = '{{core.edition}}';

    #[Group('ACMS-CORE-1162')]
    #[Group('ACMS-US-0001')]
    #[Group('ACMS-US-0071')]
    public function testConsentTakesTheEditionInForceAndTextNamesThePublishedUpcomingOne(): void
    {
        $consents = self::service(Consents::class);
        self::mockTime('2026-11-05 12:00');
        self::assertSame('2026-11-05', $consents->revision(), 'without editions the consent takes the day of acceptance');

        $this->configure([Consents::EDITIONS => json_encode([
            [Consents::EFFECTIVE => '2026-11-13', Consents::PUBLISHED => '2026-11-02'],
            [Consents::EFFECTIVE => '2026-10-12'],
            [Consents::EFFECTIVE => '2026-12-20', Consents::PUBLISHED => '2026-12-09'],
        ], \JSON_THROW_ON_ERROR)]);

        self::assertSame('2026-10-12', $consents->revision());
        self::assertStringContainsString(
            self::phrase('core.legal.edition', ['effective' => self::day('2026-10-12')]).' '
            .self::phrase('core.legal.upcoming', ['published' => self::day('2026-11-02'), 'effective' => self::day('2026-11-13')]),
            self::service(Markup::class)->html(self::TEXT),
            'an edition published before its day is named with both dates after the one in force',
        );

        self::mockTime('2026-11-13 00:30');
        self::service(ResetInterface::class, 'services_resetter')->reset();
        self::assertSame('2026-11-13', $consents->revision());
        self::assertSame(
            self::service(Markup::class)->html(self::phrase('core.legal.edition', ['effective' => self::day('2026-11-13')])),
            self::service(Markup::class)->html(self::TEXT),
            'an edition not yet published stays unnamed',
        );
    }

    private static function day(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date);
    }
}
