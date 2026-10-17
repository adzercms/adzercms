<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Confirmation;

use AdzerCMS\Core\Confirmation\Confirmations;
use AdzerCMS\Core\Confirmation\Kind;
use AdzerCMS\Core\Confirmation\Purpose;
use AdzerCMS\Core\Entity\Confirmation;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;

final class ConfirmationsTest extends SchemaTestCase
{
    use Accounts;
    use Configuration;

    private const MOMENT = '2026-10-16 10:00:00';
    private const MINUTE = 60;
    private const PHONE = '79004445566';

    #[Group('ACMS-CORE-0091')]
    #[Group('ACMS-CORE-0092')]
    public function testCodeMatchesOnceByLastCodeOfPurpose(): void
    {
        $clock = self::mockTime(self::MOMENT);
        $confirmations = self::service(Confirmations::class);
        $user = $this->record('+7 900 111-22-33');
        $first = $confirmations->code(Purpose::Password, $user) ?? self::fail('the first code is not issued');
        $clock->sleep(3 * self::MINUTE);
        $last = $confirmations->code(Purpose::Password, $user) ?? self::fail('the code after the pause is not issued');

        self::assertMatchesRegularExpression('/^\d{6}$/', $last);
        self::assertNull($confirmations->matching(Purpose::Password, $user, $first), 'an earlier code does not match');
        self::assertNull($confirmations->matching(Purpose::Contact, $user, $last), 'a code of another purpose does not match');
        $this->use($confirmations->matching(Purpose::Password, $user, $last));
        self::assertNull($confirmations->matching(Purpose::Password, $user, $last), 'a used code does not match again');
    }

    #[Group('ACMS-CORE-0092')]
    public function testSpentAttemptsAndTermCloseCode(): void
    {
        $clock = self::mockTime(self::MOMENT);
        $confirmations = self::service(Confirmations::class);
        $user = $this->record('+7 900 111-22-33');
        $code = $confirmations->code(Purpose::Password, $user) ?? self::fail('the code is not issued');
        $wrong = '000000' === $code ? '000001' : '000000';
        for ($attempt = 0; $attempt < Confirmation::ATTEMPTS; ++$attempt) {
            self::assertNull($confirmations->matching(Purpose::Password, $user, $wrong));
        }
        self::assertNull($confirmations->matching(Purpose::Password, $user, $code), 'spent attempts close the code');

        $code = $confirmations->code(Purpose::Registration, null, self::PHONE) ?? self::fail('the registration code is not issued');
        $clock->sleep(Kind::Code->minutes() * self::MINUTE + 1);
        self::assertNull($confirmations->matching(Purpose::Registration, self::PHONE, $code), 'an expired code does not match');
    }

    #[Group('ACMS-CORE-0091')]
    #[Group('ACMS-CORE-0092')]
    public function testLinkIsFoundByTokenOfPurposeWithinTerm(): void
    {
        $clock = self::mockTime(self::MOMENT);
        $confirmations = self::service(Confirmations::class);
        $user = $this->record('+7 900 111-22-33');
        $token = $confirmations->link(Purpose::Deletion, $user) ?? self::fail('the link is not issued');

        self::assertNull($confirmations->pending(Purpose::Password, $token), 'a link of another purpose is not found');
        self::assertNull($confirmations->pending(Purpose::Deletion, strrev($token)));
        self::assertSame($user->getId(), $confirmations->pending(Purpose::Deletion, $token)?->getUser()?->getId());
        $clock->sleep(Kind::Link->minutes() * self::MINUTE + 1);
        self::assertNull($confirmations->pending(Purpose::Deletion, $token), 'an expired link is not found');
    }

    #[Group('ACMS-CORE-0093')]
    public function testPauseHoldsPurposeAndKindOfAddressee(): void
    {
        $clock = self::mockTime(self::MOMENT);
        $this->configure([Confirmations::PAUSE_MINUTES => '5']);
        $confirmations = self::service(Confirmations::class);
        $user = $this->record('+7 900 111-22-33');
        $code = $confirmations->code(Purpose::Password, $user) ?? self::fail('the code is not issued');

        $clock->sleep(4 * self::MINUTE);
        self::assertNull($confirmations->code(Purpose::Password, $user), 'a repeat within the pause is not issued');
        self::assertNotNull($confirmations->link(Purpose::Password, $user), 'another kind goes');
        self::assertNotNull($confirmations->code(Purpose::Contact, $user, self::PHONE), 'another purpose goes');
        self::assertNotNull($confirmations->code(Purpose::Registration, null, self::PHONE), 'a contact without a record is its own addressee');
        $this->use($confirmations->matching(Purpose::Password, $user, $code));

        $clock->sleep(self::MINUTE);
        self::assertNotNull($confirmations->code(Purpose::Password, $user), 'the pause is over');
    }

    #[Group('ACMS-CORE-0133')]
    public function testSweepRemovesExpired(): void
    {
        $clock = self::mockTime(self::MOMENT);
        $confirmations = self::service(Confirmations::class);
        $user = $this->record('+7 900 111-22-33');
        $confirmations->code(Purpose::Password, $user);
        $clock->sleep((Kind::Code->minutes() + 1) * self::MINUTE);
        $token = $confirmations->link(Purpose::Password, $user) ?? self::fail('the link is not issued');

        $sweep = self::command('adzercms:sweep');

        self::assertSame(Command::SUCCESS, $sweep->execute([]));
        self::assertStringContainsString('confirmations removed: 1', $sweep->getDisplay());
        self::assertNotNull($confirmations->pending(Purpose::Password, $token), 'a confirmation within its term stays');
    }

    private function use(?Confirmation $confirmation): void
    {
        ($confirmation ?? self::fail('the code does not match'))->use();
        $this->em->flush();
    }
}
