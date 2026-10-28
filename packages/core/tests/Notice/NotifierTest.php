<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Notice;

use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Entity\Notice;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Notice\CoreEvent;
use AdzerCMS\Core\Notice\Journal;
use AdzerCMS\Core\Notice\MailChannel;
use AdzerCMS\Core\Notice\MailTransport;
use AdzerCMS\Core\Notice\Notifier;
use AdzerCMS\Core\Notice\QuietHours;
use AdzerCMS\Core\Notice\Status;
use AdzerCMS\Core\Repository\NoticeRepository;
use AdzerCMS\Core\Setting\Brand;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Delivery;
use AdzerCMS\Core\Tests\Notice\Fixture\Flare\FlareChannel;
use AdzerCMS\Core\Tests\Notice\Fixture\NoticeController;
use AdzerCMS\Core\Tests\Notice\Fixture\Relay\RelayGateway;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Translation\LocaleSwitcher;

final class NotifierTest extends SchemaTestCase
{
    use Accounts;
    use Configuration;
    use Delivery;

    private const MOMENT = '2026-10-16 10:00:00';
    private const NIGHT = '2026-10-16 23:30:00';
    private const MINUTE = 60;
    private const DAY = 86400;
    private const QUIET = [QuietHours::FROM => '22:00', QuietHours::TO => '07:00'];
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    private const SENT = 'sent';

    #[Group('ACMS-CORE-0118')]
    #[Group('ACMS-CORE-0120')]
    #[Group('ACMS-CORE-0127')]
    #[Group('ACMS-CORE-0172')]
    public function testModuleMessageGoesByMailIntoJournal(): void
    {
        $this->configure(self::MAIL);
        $user = $this->record('+7 900 111-22-33', 'Owner@Example.org');

        self::assertSame(self::SENT, $this->message($user, 'Service suspended', "Balance is below zero.\nTop up to resume."));

        $email = $this->letter();
        self::assertSame('Village network: Service suspended', $email->getSubject());
        self::assertSame(['robot@example.org', 'Village network'], [$email->getFrom()[0]->getAddress(), $email->getFrom()[0]->getName()]);
        self::assertSame("Balance is below zero.\nTop up to resume.", $email->getTextBody());
        $html = (string) $email->getHtmlBody();
        self::assertMatchesRegularExpression('/<p class="message" style="[^"]+">Balance is below zero./', $html, 'styles of the sheet are inlined');
        self::assertMatchesRegularExpression('#<td class="head" style="[^"]+">Village network</td>#', $html);
        self::assertStringContainsString('<a href="http://localhost/"', $html, 'the foot links the instance address');

        $notice = $this->journal($user)[0];
        self::assertSame(
            [CoreEvent::Message->key(), ContactKind::Email->channel(), 'owner@example.org', "Balance is below zero.\nTop up to resume.", Status::Sent],
            [$notice->getEvent(), $notice->getChannel(), $notice->getRecipient(), $notice->getBody(), $notice->getStatus()],
        );
    }

    #[Group('ACMS-CORE-0121')]
    #[Group('ACMS-CORE-0127')]
    #[Group('ACMS-CORE-0134')]
    public function testModuleMessageGoesByGatewayBeforeMail(): void
    {
        $this->configure(self::MAIL + self::RELAY);
        $user = $this->record('+7 900 111-22-33', 'owner@example.org');

        self::assertSame(self::SENT, $this->message($user, 'Service suspended', "Balance is below zero.\nTop up to resume."));

        self::assertSame([['relay-token', '79001112233', "Balance is below zero.\nTop up to resume."]], $this->relay()->sent, 'the gateway gets its credentials and the text without the subject');
        self::assertEmailCount(0);
        $notice = $this->journal($user)[0];
        self::assertSame([ContactKind::Phone->channel(), '79001112233', Status::Sent], [$notice->getChannel(), $notice->getRecipient(), $notice->getStatus()]);
    }

    #[Group('ACMS-CORE-0121')]
    public function testModuleChannelGoesBeforePhoneAndMail(): void
    {
        $this->configure(self::MAIL + self::RELAY + [FlareChannel::READY => '1']);
        $user = $this->record('+7 900 111-22-33', 'owner@example.org');

        self::assertSame(self::SENT, $this->message($user, 'Service suspended', 'Top up to resume.'));

        self::assertSame([['flare:79001112233', 'Top up to resume.']], self::service(FlareChannel::class)->sent);
        self::assertSame([], $this->relay()->sent);
        self::assertEmailCount(0);
    }

    #[Group('ACMS-CORE-0121')]
    #[Group('ACMS-CORE-0132')]
    public function testRefusedMessagePassesToMail(): void
    {
        $this->configure(self::MAIL + self::RELAY);
        $this->relay()->refusal = 'balance is exhausted';
        $user = $this->record('+7 900 111-22-33', 'owner@example.org');

        self::assertSame(self::SENT, $this->message($user, 'Backup', 'Backup is done'));

        self::assertEmailCount(1);
        self::assertSame(
            [[ContactKind::Phone->channel(), Status::Failed, 'balance is exhausted'], [ContactKind::Email->channel(), Status::Sent, null]],
            array_map(static fn (Notice $notice): array => [$notice->getChannel(), $notice->getStatus(), $notice->getError()], $this->journal($user)),
        );
    }

    #[Group('ACMS-CORE-0903')]
    public function testEventBesidesReachedContactSkipsItsChannel(): void
    {
        $this->configure(self::MAIL + self::RELAY);
        $this->relay()->refusal = 'balance is exhausted';
        $user = $this->record('+7 900 111-22-33', 'owner@example.org');

        self::service(Notifier::class)->notify($user, CoreEvent::Message, ['subject' => 'Invoice', 'text' => 'The invoice is mailed'], besides: ContactKind::Email);

        self::assertEmailCount(0);
        self::assertSame(
            [[ContactKind::Phone->channel(), Status::Failed]],
            array_map(static fn (Notice $notice): array => [$notice->getChannel(), $notice->getStatus()], $this->journal($user)),
        );
    }

    #[Group('ACMS-CORE-0121')]
    public function testUserWithoutAnyContactGetsNothing(): void
    {
        $this->configure(self::MAIL + self::RELAY);
        $user = new User();
        $this->em->persist($user);
        $this->em->flush();

        self::assertSame(NoticeController::SKIPPED, $this->message($user, 'Backup', 'Backup is done'));
        self::assertSame([], $this->relay()->sent);
        self::assertEmailCount(0);
        self::assertSame([], $this->journal($user), 'an unreached record leaves no trace');
    }

    #[Group('ACMS-CORE-0121')]
    public function testUserWithoutPhoneIsReachedByMail(): void
    {
        $this->configure(self::MAIL + self::RELAY);
        $user = (new User())->setEmail('owner@example.org');
        $this->em->persist($user);
        $this->em->flush();

        self::assertSame(self::SENT, $this->message($user, 'Backup', 'Backup is done'));
        self::assertSame([], $this->relay()->sent);
        self::assertSame('owner@example.org', $this->letter()->getTo()[0]->getAddress());
    }

    #[Group('ACMS-CORE-0121')]
    #[Group('ACMS-CORE-0122')]
    #[Group('ACMS-US-0002')]
    public function testMutedChannelHandsTheEventToTheNextOne(): void
    {
        $this->configure(self::MAIL + self::RELAY);
        $user = $this->mute($this->record('+7 900 111-22-33', 'owner@example.org'), ContactKind::Phone);

        self::assertSame(self::SENT, $this->message($user, 'Backup', 'Backup is done'));
        self::assertSame([], $this->relay()->sent);
        self::assertEmailCount(1);
    }

    #[Group('ACMS-CORE-0120')]
    #[Group('ACMS-CORE-0127')]
    public function testLetterSubjectAndTextKeepTheTypedCharacters(): void
    {
        $this->configure(self::MAIL);
        $user = $this->record('+7 900 111-22-33', 'owner@example.org');
        $typed = 'Домен "пример.рф" & <резерв>';

        $this->message($user, $typed, $typed);

        $email = $this->letter();
        self::assertSame(['Village network: '.$typed, $typed], [$email->getSubject(), $email->getTextBody()]);
        self::assertStringNotContainsString('<резерв>', (string) $email->getHtmlBody(), 'typed markup is text of the letter');
        self::assertSame($typed, $this->journal($user)[0]->getBody());
    }

    #[Group('ACMS-CORE-0168')]
    public function testLetterSpeaksLanguageOfAddressee(): void
    {
        $this->configure(self::MAIL);
        $user = $this->record('+7 900 111-22-33', 'owner@example.org')->setLanguage('ru');
        $this->em->flush();

        self::service(Notifier::class)->notify($user, CoreEvent::AccountDeletion, ['url' => 'http://localhost/deletion', 'minutes' => 30]);

        self::assertSame(
            self::phrase('core.notice.account_deletion.subject', ['brand' => 'Village network', 'login' => 'owner@example.org'], 'ru'),
            $this->letter()->getSubject(),
        );
        self::assertSame('en', self::service(LocaleSwitcher::class)->getLocale(), 'the language of the run comes back');
    }

    #[Group('ACMS-UI-0007')]
    #[Group('ACMS-CORE-0172')]
    public function testLogoReplacesTitleInLetterHead(): void
    {
        $this->configure(self::MAIL);
        $logo = self::parameter('adzercms.files_dir').'/upload/logo';
        (new Filesystem())->dumpFile($logo, (string) base64_decode(self::PNG));
        self::assertSame(Command::SUCCESS, self::command('adzercms:setting')->execute(['name' => Brand::LOGO, 'value' => $logo]));

        $this->message($this->record('+7 900 111-22-33', 'owner@example.org'), 'Service suspended', 'Balance is below zero.');

        self::assertMatchesRegularExpression(
            '#<td class="head" style="[^"]+"><img class="logo" src="http://localhost/setting/brand\.logo" alt="Village network"[^>]*></td>#',
            (string) $this->letter()->getHtmlBody(),
        );
    }

    #[Group('ACMS-CORE-0120')]
    #[Group('ACMS-CORE-0129')]
    public function testMessageOfNamedItemGoesOncePerDay(): void
    {
        $clock = self::mockTime(self::MOMENT);
        $this->configure(self::MAIL);
        $user = $this->record('+7 900 111-22-33', 'owner@example.org');

        self::assertSame(self::SENT, $this->message($user, 'Invoice', 'Invoice 7 is issued', 'invoices.7'));
        self::assertSame(NoticeController::SKIPPED, $this->message($user, 'Invoice', 'Invoice 7 is issued', 'invoices.7'), 'a repeat of the day is skipped');
        self::assertEmailCount(0);
        self::assertSame(self::SENT, $this->message($user, 'Invoice', 'Invoice 8 is issued', 'invoices.8'), 'another item goes');
        $clock->sleep(self::DAY);
        self::assertSame(self::SENT, $this->message($user, 'Invoice', 'Invoice 7 is issued', 'invoices.7'), 'the next day goes');
        self::assertEmailCount(1);
    }

    #[Group('ACMS-CORE-0120')]
    public function testMessageWithoutItemIsNotLimited(): void
    {
        $this->configure(self::MAIL);
        $user = $this->record('+7 900 111-22-33', 'owner@example.org');

        self::assertSame(self::SENT, $this->message($user, 'Backup', 'Backup is done'));
        self::assertSame(self::SENT, $this->message($user, 'Backup', 'Backup is done'));
        self::assertEmailCount(1);
    }

    #[Group('ACMS-CORE-0122')]
    #[Group('ACMS-CORE-0170')]
    public function testChannelMutedByUserIsSkipped(): void
    {
        $this->configure(self::MAIL);
        $user = $this->mute($this->record('+7 900 111-22-33', 'owner@example.org'));

        self::assertSame(NoticeController::SKIPPED, $this->message($user, 'Backup', 'Backup is done'));
        self::assertEmailCount(0);
    }

    #[Group('ACMS-CORE-0052')]
    public function testMailWaitsForServerSenderAndAddress(): void
    {
        $user = $this->record('+7 900 111-22-33', 'owner@example.org');
        self::assertSame(NoticeController::SKIPPED, $this->message($user, 'Backup', 'Backup is done'));

        $this->configure([MailChannel::HOST => 'smtp.example.org']);
        self::assertSame(NoticeController::SKIPPED, $this->message($user, 'Backup', 'Backup is done'), 'a server without a sender is not ready');

        $this->configure(self::MAIL);
        self::assertSame(NoticeController::SKIPPED, $this->message($this->record('+7 900 444-55-66'), 'Backup', 'Backup is done'), 'a record without an email is not reached');
        self::assertEmailCount(0);
    }

    #[Group('ACMS-CORE-0121')]
    #[Group('ACMS-CORE-0132')]
    public function testRefusedLetterIsJournaledAndKeepsItemOpen(): void
    {
        self::getContainer()->set('mailer.mailer', new class implements MailerInterface {
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                throw new TransportException('550 mailbox unavailable');
            }
        });
        $this->configure(self::MAIL);
        $user = $this->record('+7 900 111-22-33', 'owner@example.org');

        self::assertSame(Status::Failed->value, $this->message($user, 'Invoice', 'Invoice 7 is issued', 'invoices.7'));
        self::assertSame(Status::Failed->value, $this->message($user, 'Invoice', 'Invoice 7 is issued', 'invoices.7'), 'a refused letter does not take the item');

        self::assertSame(
            [[Status::Failed, '550 mailbox unavailable'], [Status::Failed, '550 mailbox unavailable']],
            array_map(static fn (Notice $notice): array => [$notice->getStatus(), $notice->getError()], $this->journal($user)),
        );
    }

    #[Group('ACMS-CORE-0122')]
    #[Group('ACMS-CORE-0171')]
    public function testContactGoesPastMutedChannelEveryTime(): void
    {
        $this->configure(self::MAIL);
        $user = $this->mute($this->record('+7 900 111-22-33', 'owner@example.org'));

        self::assertSame(self::SENT, $this->contact(' New@Example.org ', $user));
        self::assertSame('new@example.org', $this->letter()->getTo()[0]->getAddress());
        self::assertSame(self::SENT, $this->contact('new@example.org', $user), 'a repeat goes');
        self::assertEmailCount(1);

        self::assertSame(
            array_fill(0, 2, [ContactKind::Email->channel(), 'new@example.org', Status::Sent]),
            array_map(static fn (Notice $notice): array => [$notice->getChannel(), $notice->getRecipient(), $notice->getStatus()], $this->journal($user)),
        );
    }

    #[Group('ACMS-CORE-0094')]
    #[Group('ACMS-CORE-0171')]
    public function testContactWithoutRecordIsJournaledWithoutUser(): void
    {
        $this->configure(self::MAIL);

        self::assertSame(self::SENT, $this->contact('guest@example.org'));

        self::assertSame([['guest@example.org', 'Code 1234']], array_map(static fn (Notice $notice): array => [$notice->getRecipient(), $notice->getBody()], $this->addressed('guest@example.org')));
    }

    #[Group('ACMS-CORE-0098')]
    #[Group('ACMS-CORE-0099')]
    #[Group('ACMS-CORE-0134')]
    public function testKindIsReachableByReadyChannel(): void
    {
        $notifier = self::service(Notifier::class);
        self::assertFalse($notifier->reachable(ContactKind::Email));
        self::assertSame(NoticeController::SKIPPED, $this->contact('guest@example.org'));

        $this->configure(self::MAIL);
        self::assertTrue($notifier->reachable(ContactKind::Email));
        self::assertFalse($notifier->reachable(ContactKind::Phone), 'a phone without a message gateway is unreachable');
        self::assertSame(NoticeController::SKIPPED, $this->contact('8 (900) 444-55-66'));
        self::assertSame(NoticeController::SKIPPED, $this->contact('guest@'), 'a malformed address is not reached');
        self::assertEmailCount(0);

        $this->configure(self::RELAY);
        self::assertTrue($notifier->reachable(ContactKind::Phone), 'an enabled gateway with its credentials reaches a phone');
        self::assertSame(self::SENT, $this->contact('8 (900) 444-55-66'));
        self::assertSame([['relay-token', '79004445566', 'Code 1234']], $this->relay()->sent);
    }

    #[Group('ACMS-CORE-0129')]
    #[Group('ACMS-CORE-0130')]
    public function testMailWithoutRecordGoesOncePerDay(): void
    {
        $clock = self::mockTime(self::MOMENT);
        $this->configure(self::MAIL);

        self::assertSame(self::SENT, $this->mail('Admin@Example.org', 'domains.7'));
        self::assertSame('admin@example.org', $this->letter()->getTo()[0]->getAddress());
        self::assertSame(NoticeController::SKIPPED, $this->mail('admin@example.org', 'domains.7'), 'a repeat of the day is skipped');
        self::assertSame(self::SENT, $this->mail('admin@example.org', 'domains.8'), 'another item goes');
        $clock->sleep(self::DAY);
        self::assertSame(self::SENT, $this->mail('admin@example.org', 'domains.7'), 'the next day goes');
        self::assertSame(NoticeController::SKIPPED, $this->mail('+7 900 444-55-66', 'domains.7'), 'a phone is not an address');

        self::assertCount(3, $this->addressed('admin@example.org'));
    }

    #[Group('ACMS-CORE-0131')]
    #[Group('ACMS-CORE-0132')]
    #[Group('ACMS-US-0002')]
    public function testQuietDefersLetterTillFirstReleaseAfterIt(): void
    {
        $clock = self::mockTime(self::NIGHT);
        $this->configure(self::MAIL + self::QUIET);
        $user = $this->record('+7 900 111-22-33', 'owner@example.org');

        self::assertSame(Status::Deferred->value, $this->message($user, 'Invoice', 'Invoice 7 is issued', 'invoices.7'));
        self::assertSame(NoticeController::SKIPPED, $this->message($user, 'Invoice', 'Invoice 7 is issued', 'invoices.7'), 'the deferred letter takes the item');
        $clock->sleep(7 * 3600 + 29 * self::MINUTE);
        self::assertSame('', $this->notices(), 'the quiet goes on past midnight');
        self::assertEmailCount(0);

        $clock->sleep(self::MINUTE);
        self::assertSame('notices sent: 1, failed: 0', $this->notices());
        $email = $this->letter();
        self::assertSame(['Village network: Invoice', 'Invoice 7 is issued'], [$email->getSubject(), $email->getTextBody()]);
        self::assertStringContainsString('Invoice 7 is issued</p>', (string) $email->getHtmlBody());
        self::assertSame([Status::Sent], array_map(static fn (Notice $notice): Status => $notice->getStatus(), $this->journal($user)));
        self::assertSame('', $this->notices(), 'a released letter goes once');
    }

    #[Group('ACMS-CORE-0131')]
    #[Group('ACMS-CORE-0171')]
    public function testContactKnowsNoQuiet(): void
    {
        self::mockTime(self::NIGHT);
        $this->configure(self::MAIL + self::QUIET);

        self::assertSame(self::SENT, $this->contact('guest@example.org'));
        self::assertEmailCount(1);
    }

    #[Group('ACMS-CORE-0132')]
    public function testDeferredLetterFailsOnChannelNoLongerReady(): void
    {
        $clock = self::mockTime(self::NIGHT);
        $this->configure(self::MAIL + self::QUIET);
        $user = $this->record('+7 900 111-22-33', 'owner@example.org');
        $this->message($user, 'Backup', 'Backup is done');
        $this->configure([MailChannel::HOST => '']);
        $clock->sleep(8 * 3600);

        self::assertSame('notices sent: 0, failed: 1', $this->notices());
        self::assertSame(
            [[Status::Failed, 'channel mail is not ready']],
            array_map(static fn (Notice $notice): array => [$notice->getStatus(), $notice->getError()], $this->journal($user)),
        );
    }

    #[Group('ACMS-CORE-0133')]
    public function testSweepKeepsDeferred(): void
    {
        $clock = self::mockTime(self::NIGHT);
        $this->configure(self::MAIL + self::QUIET + [Journal::KEEP_DAYS => '30']);
        $user = $this->record('+7 900 111-22-33', 'owner@example.org');
        $this->message($user, 'Backup', 'Backup is done');
        $clock->sleep(31 * self::DAY);

        $sweep = self::command('adzercms:sweep');

        self::assertSame(Command::SUCCESS, $sweep->execute([]));
        self::assertStringContainsString('notices removed: 0', $sweep->getDisplay());
        self::assertSame([Status::Deferred], array_map(static fn (Notice $notice): Status => $notice->getStatus(), $this->journal($user)));
    }

    #[Group('ACMS-CORE-0133')]
    public function testSweepRemovesJournalPastTerm(): void
    {
        $clock = self::mockTime(self::MOMENT);
        $this->configure(self::MAIL + [Journal::KEEP_DAYS => '30']);
        $user = $this->record('+7 900 111-22-33', 'owner@example.org');
        $this->message($user, 'Backup', 'Old backup is done');
        $clock->sleep(31 * self::DAY);
        $this->message($user, 'Backup', 'Fresh backup is done');

        $sweep = self::command('adzercms:sweep');

        self::assertSame(Command::SUCCESS, $sweep->execute([]));
        self::assertStringContainsString('notices removed: 1', $sweep->getDisplay());
        self::assertSame(['Fresh backup is done'], array_map(static fn (Notice $notice): string => $notice->getBody(), $this->journal($user)));
    }

    #[Group('ACMS-CORE-0052')]
    public function testTransportIsAssembledFromSettings(): void
    {
        $this->configure([MailChannel::HOST => 'smtp.example.org', MailChannel::LOGIN => 'postmaster']);
        $transport = self::service(MailTransport::class);
        $smtps = $transport->create(Dsn::fromString(MailTransport::DSN));

        self::assertSame('smtps://smtp.example.org', (string) $smtps, 'an empty port is SMTPS');
        self::assertSame('postmaster', $smtps instanceof EsmtpTransport ? $smtps->getUsername() : self::fail('the transport is not SMTP'));

        $this->configure([MailChannel::PORT => '465']);
        self::assertSame('smtps://smtp.example.org', (string) $transport->create(Dsn::fromString(MailTransport::DSN)), 'the SMTPS port takes implicit TLS');

        $this->configure([MailChannel::PORT => '587']);
        self::assertSame('smtp://smtp.example.org:587', (string) $transport->create(Dsn::fromString(MailTransport::DSN)));
    }

    private function message(User $user, string $subject, string $text, string $item = ''): string
    {
        return $this->post('/fixture/notice/message/'.$user->getId(), ['subject' => $subject, 'text' => $text, 'item' => $item]);
    }

    private function contact(string $contact, ?User $user = null): string
    {
        return $this->post('/fixture/notice/contact', ['contact' => $contact, 'subject' => 'Code', 'text' => 'Code 1234'] + (null === $user ? [] : ['user' => (string) $user->getId()]));
    }

    private function mail(string $address, string $item): string
    {
        return $this->post('/fixture/notice/mail', ['address' => $address, 'subject' => 'Domain', 'text' => 'Confirm the administrator', 'item' => $item]);
    }

    /** @param array<string, string> $parameters */
    private function post(string $uri, array $parameters): string
    {
        $this->client->request('POST', $uri, $parameters);
        self::assertResponseIsSuccessful();

        return (string) $this->client->getResponse()->getContent();
    }

    private function mute(User $user, ContactKind $kind = ContactKind::Email): User
    {
        $this->em->getConnection()->update('users', ['muted_channels' => json_encode([$kind->channel()])], ['id' => $user->getId()]);
        $user = $this->reread($user);

        return $user;
    }

    private function relay(): RelayGateway
    {
        return self::service(RelayGateway::class);
    }

    /** @return list<Notice> */
    private function journal(User $user): array
    {
        return self::service(NoticeRepository::class)->findBy(['user' => $user], ['id' => 'ASC']);
    }

    /** @return list<Notice> */
    private function addressed(string $recipient): array
    {
        return self::service(NoticeRepository::class)->findBy(['user' => null, 'recipient' => $recipient], ['id' => 'ASC']);
    }
}
