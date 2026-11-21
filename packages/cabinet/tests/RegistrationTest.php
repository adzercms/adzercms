<?php

declare(strict_types=1);

namespace AdzerCMS\Cabinet\Tests;

use AdzerCMS\Core\Account\ConsentKind;
use AdzerCMS\Core\Account\Consents;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Account\ContactOutcome;
use AdzerCMS\Core\Account\LoginNames;
use AdzerCMS\Core\Account\Status;
use AdzerCMS\Core\Confirmation\ConfirmationService;
use AdzerCMS\Core\Confirmation\Kind;
use AdzerCMS\Core\Confirmation\Purpose;
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
use AdzerCMS\Core\Ui\PublicForm;
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
    #[Group('ACMS-CORE-1200')]
    #[Group('ACMS-US-0001')]
    public function testPhoneLinkOpensRecordWithIssuedPassword(): void
    {
        $service = self::service(ConfirmationService::class);

        self::assertSame(ContactOutcome::Sent, $service->requestRegistration('+7 (999) 123-45-67'));

        $notice = $this->last();
        self::assertSame(ContactKind::Phone->channel(), $notice->getChannel());
        self::assertSame(self::PHONE, $notice->getRecipient());
        self::assertSame([], self::service(UserRepository::class)->findAll(), 'the link comes before any record');

        $user = $service->completeRegistration($service->pending(Purpose::Registration, self::token($this->texted())) ?? self::fail('the link is not pending')) ?? self::fail('the link opened no record');

        self::assertSame(self::PHONE, $user->getPhone());
        self::assertSame(Status::Active, $user->getStatus());
        self::assertTrue(self::service(UserPasswordHasherInterface::class)->isPasswordValid($user, self::issued($this->texted())), 'the message to the contact carries the password of the record');
        self::assertSame([ConsentKind::Offer], array_map(
            static fn (Consent $consent): ConsentKind => $consent->getKind(),
            self::service(ConsentRepository::class)->findBy(['user' => $user]),
        ), 'the record keeps the accepted offer, personal data go by the contract');
    }

    #[Group('ACMS-CORE-0070')]
    #[Group('ACMS-CORE-1200')]
    #[Group('ACMS-US-0001')]
    public function testMailLinkPageOpensCabinetAndMailsLoginAndPassword(): void
    {
        self::service(OpenPolicy::class)->registration = true;
        $form = $this->client->request('GET', '/register')->filter('form[method="post"]')->form();
        $this->client->submit($form, ['contact' => 'Human@Example.Org']);
        $token = self::token($this->letterText());

        self::assertCount(1, $this->client->followRedirect()->filter('[data-sent]'), 'the form names the contact the link went to');
        $confirm = $this->client->request('GET', '/register/'.$token);
        self::assertSame([], self::service(UserRepository::class)->findAll(), 'opening the link alone opens no record');

        $this->client->submit($confirm->filter('form[data-autosubmit]')->form());

        $user = self::service(UserRepository::class)->byLogin(self::MAILBOX) ?? self::fail('the link opened no record');
        self::assertNull($user->getPhone());
        self::assertResponseRedirects('/cabinet', null, 'the record signs in');
        self::assertSame(self::MAILBOX, $this->letter()->getTo()[0]->getAddress());
        self::assertStringContainsString(self::service(LoginNames::class)->of($user), $this->letterText(), 'the letter names the login of the record');
        self::assertTrue(self::service(UserPasswordHasherInterface::class)->isPasswordValid($user, self::issued($this->letterText())), 'the letter carries the password of the record');
    }

    #[Group('ACMS-CORE-0092')]
    #[Group('ACMS-US-0001')]
    public function testMailedLinkNamesItsTerm(): void
    {
        self::assertSame(ContactOutcome::Sent, self::service(ConfirmationService::class)->requestRegistration(self::MAILBOX));

        $minutes = (string) Kind::Link->minutes();
        self::assertStringContainsString($minutes, $this->letterText(), 'the letter text names the link term');
        self::assertStringContainsString($minutes, (string) $this->letter()->getHtmlBody(), 'the letter markup names the link term');
    }

    #[Group('ACMS-CORE-0068')]
    #[Group('ACMS-CORE-0095')]
    #[Group('ACMS-US-0001')]
    public function testKnownPhoneGetsRecoveryInsteadOfLink(): void
    {
        $known = $this->record(self::PHONE);
        $service = self::service(ConfirmationService::class);

        self::assertSame(ContactOutcome::Sent, $service->requestRegistration('+7 999 123-45-67'), 'the form answers as on a sent link');

        $notice = self::service(NoticeRepository::class)->findOneBy(['user' => $known]) ?? self::fail('the known record got nothing');
        self::assertSame(CoreEvent::RegistrationKnown->key(), $notice->getEvent());
        self::assertSame(ContactKind::Phone->channel(), $notice->getChannel());
        self::assertStringContainsString('/forgot?login='.self::PHONE, $notice->getBody());
        self::assertCount(1, self::service(NoticeRepository::class)->findAll(), 'a known contact gets no registration link');
    }

    #[Group('ACMS-CORE-0068')]
    #[Group('ACMS-CORE-0095')]
    #[Group('ACMS-US-0001')]
    public function testKnownMailGetsRecoveryInsteadOfLink(): void
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
    public function testSecondLinkWaitsForThePauseOfEitherContact(): void
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

    #[Group('ACMS-CORE-1196')]
    #[Group('ACMS-US-0001')]
    #[Group('ACMS-US-0002')]
    public function testRequestsOfOneAddressStopAtThePublicFormLimit(): void
    {
        self::service(OpenPolicy::class)->registration = true;
        $this->client->followRedirects();

        foreach (['/register' => 'contact', '/forgot' => 'login'] as $path => $field) {
            for ($request = 0; $request <= PublicForm::LIMIT; ++$request) {
                $form = $this->client->request('GET', $path)->filterXPath(\sprintf('//form[.//input[@name="%s"]]', $field))->form();
                $page = $this->client->submit($form, [$field => 'human'.$request.'@example.org']);
            }

            self::assertSame(self::phrase('core.form.often'), trim($page->filter('.flash-error')->text()), $path);
        }
        self::assertCount(PublicForm::LIMIT, self::service(NoticeRepository::class)->findBy(['recipient' => array_map(static fn (int $request): string => 'human'.$request.'@example.org', range(0, PublicForm::LIMIT))]));
    }

    #[Group('ACMS-CORE-1200')]
    #[Group('ACMS-US-0001')]
    public function testLinkOfContactTakenSinceOpensNoRecord(): void
    {
        self::service(OpenPolicy::class)->registration = true;
        $service = self::service(ConfirmationService::class);
        $service->requestRegistration(self::MAILBOX);
        $token = self::token($this->letterText());
        $this->record(self::PHONE, self::MAILBOX);

        $page = $this->client->request('GET', '/register/'.$token);
        $this->client->submit($page->filter('form[data-autosubmit]')->form());

        self::assertCount(1, self::service(UserRepository::class)->findAll(), 'a contact taken since the link opens no second record');
        self::assertCount(0, $this->client->getCrawler()->filter('form[data-autosubmit]'), 'the page refuses the link');
        $this->client->request('GET', '/register/'.str_repeat('0', 64));
        self::assertCount(0, $this->client->getCrawler()->filter('form[data-autosubmit]'), 'an unknown link opens nothing');
    }

    #[Group('ACMS-CORE-0070')]
    #[Group('ACMS-US-0001')]
    public function testMalformedContactIsRefused(): void
    {
        self::assertSame(ContactOutcome::Unavailable, self::service(ConfirmationService::class)->requestRegistration('12345'));
    }

    private static function token(string $text): string
    {
        return 1 === preg_match('#/register/([0-9a-f]{64})#', $text, $match) ? $match[1] : self::fail('the message carries no registration link');
    }

    private static function issued(string $text): string
    {
        return 1 === preg_match('/([A-Za-z0-9]{12})$/m', $text, $match) ? $match[1] : self::fail('the message carries no password');
    }

    private function last(): Notice
    {
        return self::service(NoticeRepository::class)->findOneBy([], ['id' => 'DESC']) ?? self::fail('the journal is empty');
    }
}
