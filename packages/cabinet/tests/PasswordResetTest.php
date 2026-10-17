<?php

declare(strict_types=1);

namespace AdzerCMS\Cabinet\Tests;

use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Account\Status;
use AdzerCMS\Core\Confirmation\ConfirmationService;
use AdzerCMS\Core\Confirmation\Kind;
use AdzerCMS\Core\Entity\Notice;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Repository\NoticeRepository;
use AdzerCMS\Core\Repository\UserRepository;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Delivery;
use AdzerCMS\Core\Tests\Notice\Fixture\Relay\RelayGateway;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordResetTest extends SchemaTestCase
{
    use Accounts;
    use Configuration;
    use Delivery;

    private const PHONE = '79991234567';
    private const MAILBOX = 'human@example.org';
    private const CHOSEN = 'chosen-password';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure(self::MAIL + self::RELAY);
    }

    #[Group('ACMS-CORE-0090')]
    #[Group('ACMS-CORE-0091')]
    #[Group('ACMS-US-0002')]
    public function testCodeFromMessageSetsNewPasswordOnce(): void
    {
        $user = $this->record(self::PHONE);
        $service = self::service(ConfirmationService::class);

        self::assertTrue($service->request(self::PHONE));
        $code = self::code($this->texted());

        self::assertSame($user, $service->complete(self::PHONE, $code, self::CHOSEN));
        self::assertTrue($this->accepts($user, self::CHOSEN));
        self::assertNull($service->complete(self::PHONE, $code, 'second-password'), 'the code works once');
        self::assertNull($service->complete(self::PHONE, '000000', 'second-password'));
    }

    #[Group('ACMS-CORE-0093')]
    #[Group('ACMS-US-0002')]
    public function testRecoveryCodeWaitsForThePause(): void
    {
        self::mockTime('2026-10-16 10:00');
        $this->record(self::PHONE);
        $service = self::service(ConfirmationService::class);

        self::assertTrue($service->request(self::PHONE));
        self::assertFalse($service->request(self::PHONE), 'a repeat within the pause sends no code');
        self::assertCount(1, self::service(NoticeRepository::class)->findAll());

        self::mockTime('2026-10-16 10:04');

        self::assertTrue($service->request(self::PHONE));
        self::assertCount(2, self::service(NoticeRepository::class)->findAll());
    }

    #[Group('ACMS-CORE-0121')]
    #[Group('ACMS-US-0002')]
    public function testRecordWithoutContactsGetsNothing(): void
    {
        $user = $this->record(self::PHONE)->setPhone(null);
        $this->em->flush();

        self::assertFalse(self::service(ConfirmationService::class)->request((string) $user->getId()));
        self::assertSame([], self::service(NoticeRepository::class)->findAll());
    }

    #[Group('ACMS-CORE-0092')]
    #[Group('ACMS-CORE-0121')]
    #[Group('ACMS-US-0002')]
    public function testRecordWithoutPhoneGetsMailLinkNamingItsTerm(): void
    {
        $this->record(self::PHONE, self::MAILBOX)->setPhone(null);
        $this->em->flush();

        self::assertTrue(self::service(ConfirmationService::class)->request(self::MAILBOX));

        self::assertSame(ContactKind::Email->channel(), $this->last()->getChannel());
        $minutes = (string) Kind::Link->minutes();
        self::assertStringContainsString($minutes, $this->letterText(), 'the letter text names the link term');
        self::assertStringContainsString($minutes, (string) $this->letter()->getHtmlBody(), 'the letter markup names the link term');
    }

    #[Group('ACMS-CORE-0121')]
    #[Group('ACMS-US-0002')]
    public function testResetGoesByMailWhileSmsGatewayIsOff(): void
    {
        $this->configure([RelayGateway::SETTINGS.Switches::ENABLED => '']);
        $this->record(self::PHONE, self::MAILBOX);

        self::assertTrue(self::service(ConfirmationService::class)->request(self::PHONE));

        self::assertSame(ContactKind::Email->channel(), $this->last()->getChannel(), 'without a ready gateway the recovery goes by a mail link');
    }

    #[Group('ACMS-CORE-0076')]
    #[Group('ACMS-US-0002')]
    public function testMailLoginIgnoresLetterCase(): void
    {
        $user = $this->record(self::PHONE, 'Human@Example.ORG');

        self::assertSame($user, self::service(UserRepository::class)->byLogin(' human@example.org '), 'mail is kept and found in one form');
    }

    #[Group('ACMS-CORE-0069')]
    #[Group('ACMS-CORE-0074')]
    #[Group('ACMS-US-0002')]
    public function testStoppedAndArchivedRecordsGetNoCode(): void
    {
        $this->record(self::PHONE)->setStatus(Status::Stopped);
        $this->record('79991234568')->setStatus(Status::Archived);
        $this->em->flush();
        $service = self::service(ConfirmationService::class);

        self::assertFalse($service->request(self::PHONE));
        self::assertFalse($service->request('79991234568'), 'an operator archive is not lifted by recovery');
    }

    #[Group('ACMS-CORE-0074')]
    #[Group('ACMS-CORE-0100')]
    #[Group('ACMS-US-0002')]
    public function testNewPasswordReturnsRecordDeletedByItsUser(): void
    {
        $user = $this->record(self::PHONE)->delete();
        $this->em->flush();
        $service = self::service(ConfirmationService::class);

        self::assertTrue($service->request(self::PHONE), 'recovery is open to a record deleted by its user');
        self::assertNotNull($service->complete(self::PHONE, self::code($this->texted()), self::CHOSEN));

        $returned = $this->reread($user);
        self::assertSame(Status::Active, $returned->getStatus());
        self::assertFalse($returned->isDeleted());
        self::assertTrue($this->accepts($returned, self::CHOSEN));
    }

    #[Group('ACMS-CORE-0076')]
    #[Group('ACMS-US-0002')]
    public function testRecoveryPagesTakeMailAsLogin(): void
    {
        $user = $this->record(self::PHONE, self::MAILBOX);

        $crawler = $this->client->request('GET', '/forgot');
        $this->client->submit($crawler->filter('form')->form(['login' => 'Human@Example.org']));
        $crawler = $this->client->followRedirect();

        self::assertSame('Human@Example.org', $crawler->filter('input[name="login"]')->attr('value'));

        $this->client->submit($crawler->filter('form')->form(['code' => self::code($this->texted()), 'password' => self::CHOSEN]));

        self::assertResponseRedirects('/login');
        self::assertTrue($this->accepts($this->reread($user), self::CHOSEN));
    }

    private function accepts(User $user, string $password): bool
    {
        return self::service(UserPasswordHasherInterface::class)->isPasswordValid($user, $password);
    }

    private function last(): Notice
    {
        return self::service(NoticeRepository::class)->findOneBy([], ['id' => 'DESC']) ?? self::fail('the journal is empty');
    }
}
