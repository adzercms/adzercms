<?php

declare(strict_types=1);

namespace AdzerCMS\Cabinet\Tests;

use AdzerCMS\Core\Account\ConsentKind;
use AdzerCMS\Core\Account\Consents;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Account\ContactOutcome;
use AdzerCMS\Core\Account\Status;
use AdzerCMS\Core\Confirmation\ConfirmationService;
use AdzerCMS\Core\Confirmation\Kind;
use AdzerCMS\Core\Entity\Consent;
use AdzerCMS\Core\Entity\Notice;
use AdzerCMS\Core\Notice\CoreEvent;
use AdzerCMS\Core\Repository\ConsentRepository;
use AdzerCMS\Core\Repository\NoticeRepository;
use AdzerCMS\Core\Repository\UserRepository;
use AdzerCMS\Core\Tests\Account\Fixture\OpenPolicy;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Delivery;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Twig\Environment;

final class RegistrationTest extends SchemaTestCase
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

    #[Group('ACMS-CORE-0073')]
    #[Group('ACMS-US-0001')]
    public function testRegistrationLivesOnlyByPermissionOfModule(): void
    {
        foreach ([[false, 404], [true, 200]] as [$offered, $status]) {
            self::service(OpenPolicy::class)->registration = $offered;

            self::assertCount((int) $offered, $this->client->request('GET', '/login')->filter('a[href="/register"]'));
            $this->client->request('GET', '/register');
            self::assertResponseStatusCodeSame($status);
        }
    }

    #[Group('ACMS-CORE-0102')]
    #[Group('ACMS-CORE-0103')]
    #[Group('ACMS-US-0001')]
    public function testTermsLeadToOperatorDocuments(): void
    {
        $documents = ['https://operator.example/offer', 'https://operator.example/privacy'];
        $this->configure([Consents::OFFER_URL => $documents[0], Consents::PRIVACY_URL => $documents[1]]);

        $terms = new Crawler(self::service(Environment::class)->render('@Cabinet/cabinet/_terms.html.twig'));

        self::assertSame($documents, $terms->filter('a')->extract(['href']));
    }

    #[Group('ACMS-CORE-0070')]
    #[Group('ACMS-CORE-0094')]
    #[Group('ACMS-CORE-0102')]
    #[Group('ACMS-US-0001')]
    public function testPhoneCodeOpensRecord(): void
    {
        $service = self::service(ConfirmationService::class);

        self::assertSame(ContactOutcome::Sent, $service->requestRegistration('+7 (999) 123-45-67'));

        $notice = $this->last();
        self::assertSame(ContactKind::Phone->channel(), $notice->getChannel());
        self::assertSame(self::PHONE, $notice->getRecipient());
        self::assertSame([], self::service(UserRepository::class)->findAll(), 'the code comes before any record');

        $user = $service->completeRegistration(self::PHONE, self::code($this->texted()), self::CHOSEN) ?? self::fail('the code opened no record');

        self::assertSame(self::PHONE, $user->getPhone());
        self::assertSame(Status::Active, $user->getStatus());
        self::assertTrue(self::service(UserPasswordHasherInterface::class)->isPasswordValid($user, self::CHOSEN));
        self::assertSame([ConsentKind::Offer], array_map(
            static fn (Consent $consent): ConsentKind => $consent->getKind(),
            self::service(ConsentRepository::class)->findBy(['user' => $user]),
        ), 'the record keeps the accepted offer, personal data go by the contract');
    }

    #[Group('ACMS-CORE-0070')]
    #[Group('ACMS-US-0001')]
    public function testMailCodeOpensRecord(): void
    {
        $service = self::service(ConfirmationService::class);

        self::assertSame(ContactOutcome::Sent, $service->requestRegistration('Human@Example.Org'));
        $user = $service->completeRegistration(self::MAILBOX, self::code($this->letterText()), self::CHOSEN) ?? self::fail('the code opened no record');

        self::assertSame(self::MAILBOX, $user->getEmail());
        self::assertNull($user->getPhone());
    }

    #[Group('ACMS-CORE-0092')]
    #[Group('ACMS-US-0001')]
    public function testMailedCodeNamesItsTerm(): void
    {
        self::assertSame(ContactOutcome::Sent, self::service(ConfirmationService::class)->requestRegistration(self::MAILBOX));

        $minutes = (string) Kind::Code->minutes();
        self::assertStringContainsString($minutes, $this->letterText(), 'the letter text names the code term');
        self::assertStringContainsString($minutes, (string) $this->letter()->getHtmlBody(), 'the letter markup names the code term');
    }

    #[Group('ACMS-CORE-0068')]
    #[Group('ACMS-CORE-0095')]
    #[Group('ACMS-US-0001')]
    public function testKnownPhoneGetsRecoveryInsteadOfCode(): void
    {
        $known = $this->record(self::PHONE);
        $service = self::service(ConfirmationService::class);

        self::assertSame(ContactOutcome::Sent, $service->requestRegistration('+7 999 123-45-67'), 'the form answers as on a sent code');

        $notice = self::service(NoticeRepository::class)->findOneBy(['user' => $known]) ?? self::fail('the known record got nothing');
        self::assertSame(CoreEvent::RegistrationKnown->key(), $notice->getEvent());
        self::assertSame(ContactKind::Phone->channel(), $notice->getChannel());
        self::assertStringContainsString('/forgot?login='.self::PHONE, $notice->getBody());
        self::assertCount(1, self::service(NoticeRepository::class)->findAll(), 'a known contact gets no registration code');
        self::assertNull($service->completeRegistration(self::PHONE, '000001', self::CHOSEN));
    }

    #[Group('ACMS-CORE-0068')]
    #[Group('ACMS-CORE-0095')]
    #[Group('ACMS-US-0001')]
    public function testKnownMailGetsRecoveryInsteadOfCode(): void
    {
        $this->record(self::PHONE, self::MAILBOX);

        self::assertSame(ContactOutcome::Sent, self::service(ConfirmationService::class)->requestRegistration('Human@Example.org'));

        self::assertSame(self::MAILBOX, $this->letter()->getTo()[0]->getAddress());
        self::assertSame(1, preg_match('#/forgot\?(\S+)#', $this->letterText(), $link), 'the letter leads to the recovery');
        parse_str($link[1], $query);
        self::assertSame(self::MAILBOX, $query['login'] ?? null, 'the recovery opens with the login of the record');
        self::assertCount(1, self::service(UserRepository::class)->findAll(), 'no second record is opened');
    }

    #[Group('ACMS-CORE-0093')]
    #[Group('ACMS-CORE-0095')]
    #[Group('ACMS-US-0001')]
    public function testSecondCodeWaitsForThePauseOfEitherContact(): void
    {
        self::mockTime('2026-10-16 10:00');
        $this->record('79990000001');
        $service = self::service(ConfirmationService::class);

        foreach ([self::PHONE, '79990000001'] as $contact) {
            self::assertSame(ContactOutcome::Sent, $service->requestRegistration($contact));
            self::assertSame(ContactOutcome::TooOften, $service->requestRegistration($contact), 'the pause is the same to a free and a known contact');
        }
        self::assertCount(2, self::service(NoticeRepository::class)->findAll());

        self::mockTime('2026-10-16 10:04');

        self::assertSame(ContactOutcome::Sent, $service->requestRegistration(self::PHONE));
        self::assertCount(3, self::service(NoticeRepository::class)->findAll());
    }

    #[Group('ACMS-CORE-0092')]
    #[Group('ACMS-US-0001')]
    public function testWrongCodeOpensNoRecord(): void
    {
        $service = self::service(ConfirmationService::class);
        $service->requestRegistration(self::PHONE);

        self::assertNull($service->completeRegistration(self::PHONE, '000000', self::CHOSEN));
        self::assertNull(self::service(UserRepository::class)->byLogin(self::PHONE));
    }

    #[Group('ACMS-CORE-0070')]
    #[Group('ACMS-US-0001')]
    public function testMalformedContactIsRefused(): void
    {
        self::assertSame(ContactOutcome::Unavailable, self::service(ConfirmationService::class)->requestRegistration('12345'));
    }

    private function last(): Notice
    {
        return self::service(NoticeRepository::class)->findOneBy([], ['id' => 'DESC']) ?? self::fail('the journal is empty');
    }
}
