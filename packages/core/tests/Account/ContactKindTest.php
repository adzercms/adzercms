<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Account;

use AdzerCMS\Core\Account\ContactKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class ContactKindTest extends TestCase
{
    /** @return iterable<string, array{ContactKind, string, string}> */
    public static function contacts(): iterable
    {
        yield 'a city number' => [ContactKind::Phone, '74951234567', '+7 495 123-45-67'];
        yield 'a toll-free number' => [ContactKind::Phone, '78005553535', '8 800 555-35-35'];
        yield 'a number outside the Russian form' => [ContactKind::Phone, '380441234567', '380441234567'];
        yield 'a mail' => [ContactKind::Email, '78005553535@example.test', '78005553535@example.test'];
    }

    #[Group('ACMS-CORE-1160')]
    #[DataProvider('contacts')]
    public function testPhoneIsShownGroupedWithTollFreeCodeAfterEight(ContactKind $kind, string $contact, string $shown): void
    {
        self::assertSame($shown, $kind->display($contact));
    }
}
