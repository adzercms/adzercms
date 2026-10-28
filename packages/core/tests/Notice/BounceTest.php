<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Notice;

use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Entity\Notice;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Notice\Attachment;
use AdzerCMS\Core\Notice\Bounces;
use AdzerCMS\Core\Notice\CoreEvent;
use AdzerCMS\Core\Notice\MailChannel;
use AdzerCMS\Core\Notice\Notifier;
use AdzerCMS\Core\Notice\Status;
use AdzerCMS\Core\Repository\NoticeRepository;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Delivery;
use AdzerCMS\Core\Tests\SchemaTestCase;
use DirectoryTree\ImapEngine\Testing\FakeFolder;
use DirectoryTree\ImapEngine\Testing\FakeMailbox;
use DirectoryTree\ImapEngine\Testing\FakeMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

final class BounceTest extends SchemaTestCase
{
    use Accounts;
    use Configuration;
    use Delivery;

    private const MOMENT = '2026-10-16 10:00:00';
    private const ADDRESS = 'owner@example.org';
    private const BOUNCES = [MailChannel::BOUNCE_ADDRESS => 'Bounces@Example.org', MailChannel::BOUNCE_HOST => 'imap.example.org', MailChannel::BOUNCE_LOGIN => 'bounces@example.org'];

    /** @return iterable<string, array{string, string}> */
    public static function reports(): iterable
    {
        yield 'Exim without a diagnostic' => ['exim', '5.0.0'];
        yield 'Exim with a diagnostic' => ['exim-diagnostic', '554 5.7.1 Message rejected under suspicion of SPAM'];
        yield 'Exim with a folded diagnostic' => ['exim-folded', "550-5.1.1 The email account that you tried to reach does not exist. Please try 550-5.1.1 double-checking the recipient's email address for typos or 550 5.1.1 unnecessary spaces."];
        yield 'Exim with recipient groups' => ['exim-groups', '552 5.2.2 <second@example.net> Quota exceeded (mailbox for user is full)'];
        yield 'Exim of an international address' => ['exim-global', '550 5.1.1 Почтовый ящик не существует'];
        yield 'Postfix' => ['postfix', 'Message delivery failed'];
    }

    #[DataProvider('reports')]
    #[Group('ACMS-CORE-1007')]
    #[Group('ACMS-CORE-1008')]
    #[Group('ACMS-CORE-1009')]
    #[Group('ACMS-US-0005')]
    public function testBouncedLetterFailsNoticeAndMarksMail(string $report, string $reason): void
    {
        self::mockTime(self::MOMENT);
        $this->configure(self::MAIL + self::BOUNCES);
        $user = $this->record('+7 900 111-22-33', self::ADDRESS);
        $notice = $this->send($user);

        $sender = $this->sender();
        self::assertMatchesRegularExpression('/^bounces\+'.$notice->getId().'-[0-9a-f]+@example\.org$/', $sender, 'the envelope sender is a subaddress of the bounce mailbox');
        self::assertSame('robot@example.org', $this->letter()->getFrom()[0]->getAddress(), 'the letter keeps its sender');
        $inbox = $this->inbox($this->report($report, $sender));

        self::assertSame('notices bounced: 1', $this->notices());
        $notice = $this->reread($notice);
        self::assertSame([Status::Failed, $reason], [$notice->getStatus(), $notice->getError()]);
        $user = $this->reread($user);
        self::assertSame([self::MOMENT, $reason], [$user->getUndeliverableAt()?->format('Y-m-d H:i:s'), $user->getUndeliverableReason()]);
        self::assertSame([], $inbox->getMessages(), 'the report leaves the mailbox');
    }

    #[Group('ACMS-CORE-1006')]
    #[Group('ACMS-CORE-1008')]
    public function testOtherMailLeavesMailboxWithoutEffect(): void
    {
        $this->configure(self::MAIL + self::BOUNCES);
        $user = $this->record('+7 900 111-22-33', self::ADDRESS);
        $notice = $this->send($user);
        $sender = $this->sender();
        $forged = (string) preg_replace_callback('/-([0-9a-f]+)@/', static fn (array $signature): string => '-'.strtr($signature[1], '0123456789abcdef', 'fedcba9876543210').'@', $sender);

        $inbox = $this->inbox(
            $this->report('letter', $sender),
            $this->report('exim-delayed', $sender),
            $this->report('exim', $forged),
            $this->report('exim', 'bounces@example.org'),
        );

        self::assertSame('', $this->notices());
        self::assertSame(Status::Sent, $this->reread($notice)->getStatus());
        self::assertNull($this->reread($user)->getUndeliverableAt());
        self::assertSame([], $inbox->getMessages(), 'every letter leaves the mailbox');
    }

    #[Group('ACMS-CORE-1009')]
    public function testMarkFollowsCurrentMail(): void
    {
        $this->configure(self::MAIL + self::BOUNCES);
        $user = $this->record('+7 900 111-22-33', self::ADDRESS);
        $notice = $this->send($user);
        $this->inbox($this->report('exim', $this->sender()));
        $user->setEmail('new@example.org');
        $this->em->flush();

        self::assertSame('notices bounced: 1', $this->notices());
        self::assertSame(Status::Failed, $this->reread($notice)->getStatus());
        $user = $this->reread($user);
        self::assertNull($user->getUndeliverableAt(), 'a bounce of the former mail leaves the record unmarked');

        $user->markUndeliverable('5.0.0')->setEmail('New@Example.org');
        self::assertNotNull($user->getUndeliverableAt(), 'the same mail keeps the mark');
        $user->setEmail('other@example.org');
        self::assertNull($user->getUndeliverableAt(), 'another mail drops the mark');
    }

    #[Group('ACMS-CORE-1011')]
    #[Group('ACMS-US-0002')]
    public function testMarkedMailGetsAwaitedLettersOnly(): void
    {
        $this->configure(self::MAIL + self::RELAY);
        $user = $this->record('+7 900 111-22-33', self::ADDRESS)->markUndeliverable('5.0.0');
        $this->em->flush();
        $notifier = self::service(Notifier::class);

        self::assertSame(ContactKind::Phone->channel(), $notifier->message($user, 'Backup', 'Backup is done')?->getChannel(), 'an event goes by other channels');
        self::assertNull(
            $notifier->notifyMail($user, CoreEvent::Message, ['subject' => 'Invoice', 'text' => 'Invoice 7 is attached'], [new Attachment('invoice.pdf', 'application/pdf', '%PDF-1.7')]),
            'a letter with attachments passes the marked mail',
        );
        self::assertEmailCount(0);

        $notifier->contact(self::ADDRESS, CoreEvent::Message, ['subject' => 'Code', 'text' => 'Code 1234'], $user);
        $notifier->notifyMail($user, CoreEvent::AccountDeletion, ['url' => 'http://localhost/deletion', 'minutes' => 30]);
        self::assertSame(
            [self::ADDRESS, self::ADDRESS],
            array_map(static fn (Notice $notice): string => $notice->getRecipient(), self::service(NoticeRepository::class)->findBy(['channel' => ContactKind::Email->channel()])),
            'awaited letters reach the marked mail',
        );
    }

    /** @return iterable<string, array{string}> */
    public static function lacks(): iterable
    {
        yield 'address' => [MailChannel::BOUNCE_ADDRESS];
        yield 'server' => [MailChannel::BOUNCE_HOST];
        yield 'login' => [MailChannel::BOUNCE_LOGIN];
    }

    #[DataProvider('lacks')]
    #[Group('ACMS-CORE-1006')]
    public function testBouncesWaitForAddressServerAndLogin(string $lack): void
    {
        $this->configure(self::MAIL + [$lack => ''] + self::BOUNCES);
        $this->send($this->record('+7 900 111-22-33', self::ADDRESS));

        self::assertSame('robot@example.org', $this->sender(), 'the envelope sender is the letter sender');
        $inbox = $this->inbox($this->report('exim', 'bounces@example.org'));
        self::assertSame('', $this->notices());
        self::assertCount(1, $inbox->getMessages(), 'the mailbox is not read');
    }

    #[Group('ACMS-CORE-1006')]
    public function testMailboxIsAssembledFromSettings(): void
    {
        $this->configure(self::BOUNCES + [MailChannel::BOUNCE_PASSWORD => 'mailbox-secret']);
        self::assertSame(['imap.example.org', 993, 'ssl', 'bounces@example.org', 'mailbox-secret'], $this->mailbox(), 'an empty port is IMAPS');

        $this->configure([MailChannel::BOUNCE_PORT => '993']);
        self::assertSame([993, 'ssl'], \array_slice($this->mailbox(), 1, 2), 'the IMAPS port takes implicit TLS');

        $this->configure([MailChannel::BOUNCE_PORT => '143']);
        self::assertSame([143, 'starttls'], \array_slice($this->mailbox(), 1, 2));
    }

    private function send(User $user): Notice
    {
        return self::service(Notifier::class)->message($user, 'Invoice', 'Invoice 7 is issued') ?? self::fail('the notice is not sent');
    }

    private function sender(): string
    {
        $events = self::getMailerEvents();
        $last = end($events);

        return false === $last ? self::fail('no letter was sent') : $last->getEnvelope()->getSender()->getAddress();
    }

    private function report(string $form, string $to): string
    {
        return strtr((string) file_get_contents(__DIR__.'/Fixture/Bounce/'.$form.'.eml'), ['{to}' => $to, '{mailbox}' => 'bounces@example.org', '{address}' => self::ADDRESS]);
    }

    private function inbox(string ...$letters): FakeFolder
    {
        $messages = [];
        foreach (array_values($letters) as $at => $letter) {
            $messages[] = new FakeMessage($at + 1, [], $letter);
        }
        $inbox = new FakeFolder('inbox', messages: $messages);
        self::getContainer()->set(Bounces::MAILBOX, new FakeMailbox(folders: [$inbox]));

        return $inbox;
    }

    /** @return list<mixed> */
    private function mailbox(): array
    {
        $config = Bounces::mailbox(self::service(Settings::class))->config();

        return [$config['host'], $config['port'], $config['encryption'], $config['username'], $config['password']];
    }
}
