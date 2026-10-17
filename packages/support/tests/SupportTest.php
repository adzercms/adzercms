<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Tests;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Repository\NoticeRepository;
use AdzerCMS\Core\Security\CsrfGuard;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Delivery;
use AdzerCMS\Core\Tests\Queries;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Support\Entity\Ticket;
use AdzerCMS\Support\Entity\TicketAttachment;
use AdzerCMS\Support\Entity\TicketMessage;
use AdzerCMS\Support\Repository\TicketAttachmentRepository;
use AdzerCMS\Support\Repository\TicketMessageRepository;
use AdzerCMS\Support\Repository\TicketRepository;
use AdzerCMS\Support\StaffDigest;
use AdzerCMS\Support\SupportEvent;
use AdzerCMS\Support\SupportSettings;
use AdzerCMS\Support\Tests\Fixture\SubjectFixture;
use AdzerCMS\Support\Tickets;
use AdzerCMS\Support\TicketStatus;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function Symfony\Component\Clock\now;

final class SupportTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use Configuration;
    use Delivery;
    use Queries;

    private User $staff;
    private int $phones = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure(self::MAIL);
        $this->staff = $this->member()->setRole(Role::Admin);
        $this->em->flush();
    }

    #[Group('ACMS-SUP-0001')]
    #[Group('ACMS-US-0008')]
    public function testUserOpensTicketWithDepartmentSubjectAndMessage(): void
    {
        $user = $this->member();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/support/new');
        self::assertSame(self::support()->departments(), $crawler->filter('select[name="department"] option')->each(static fn (Crawler $option): string => (string) $option->attr('value')));

        $this->client->submit($crawler->filter('form[enctype="multipart/form-data"]')->form(['subject' => 'No connection', 'body' => 'The internet is gone']));

        $ticket = $this->ticketOf($user) ?? self::fail('no ticket was opened');
        self::assertResponseRedirects(\sprintf('/support/%d', $ticket->getId()));
        self::assertSame('No connection', $ticket->getSubject());
        self::assertSame(self::support()->departments()[0], $ticket->getDepartment());
        self::assertSame(TicketStatus::Open, $ticket->getStatus());

        $messages = $this->messagesOf($ticket);
        self::assertCount(1, $messages);
        self::assertFalse($messages[0]->isFromStaff());
        self::assertSame($user->getId(), $messages[0]->getAuthor()?->getId());
    }

    #[Group('ACMS-SUP-0001')]
    #[Group('ACMS-US-0008')]
    public function testEmptySubjectOrMessageOpensNothing(): void
    {
        $user = $this->member();
        $this->client->loginUser($user);

        foreach ([['subject' => '  ', 'body' => 'text'], ['subject' => 'Subject', 'body' => '   ']] as $fields) {
            $this->open($fields);

            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.toast-error');
            self::assertNull($this->ticketOf($user));
        }
    }

    #[Group('ACMS-SUP-0011')]
    #[Group('ACMS-US-0008')]
    public function testSubjectOfModuleIsCheckedBeforeOpeningAndLinkedForTheViewer(): void
    {
        self::service(SubjectFixture::class)->offered = true;
        $user = $this->member();
        $this->client->loginUser($user);

        self::assertCount(1, $this->client->request('GET', '/support/new')->filter(\sprintf('input[name="%s"]', SubjectFixture::FIELD)), 'the module offers its field');

        $this->open(['subject' => 'Broken item', 'body' => 'It does not start', SubjectFixture::FIELD => 'foreign']);
        self::assertSelectorTextContains('.toast-error', 'Not your item');
        self::assertNull($this->ticketOf($user), 'a subject refused by the module opens nothing');

        $this->open(['subject' => 'Broken item', 'body' => 'It does not start', SubjectFixture::FIELD => SubjectFixture::OWN]);
        $page = \sprintf('/support/%d', ($this->ticketOf($user) ?? self::fail('no ticket was opened'))->getId());

        foreach ([$user, $this->staff] as $viewer) {
            $this->client->loginUser($viewer);
            self::assertSame(\sprintf('/items/%s/%d', SubjectFixture::OWN, $viewer->getId()), $this->client->request('GET', $page)->filter('.ticket-side a.subject')->attr('href'));
        }
    }

    #[Group('ACMS-SUP-0002')]
    #[Group('ACMS-SUP-0009')]
    #[Group('ACMS-US-0008')]
    public function testAnswersMoveTheTicketBetweenSidesUntilItIsClosed(): void
    {
        $user = $this->member('human@example.org');
        $ticket = $this->ticket($user, 'No connection');
        $page = \sprintf('/support/%d', $ticket->getId());

        $this->client->loginUser($this->staff);
        $this->reply($ticket, 'Checking');

        self::assertEmailCount(1);
        self::assertStringContainsString((string) $ticket->getId(), (string) $this->letter()->getSubject());
        self::assertStringContainsString($page.'"', (string) $this->letter()->getHtmlBody(), 'the letter leads to the ticket');
        self::assertSame(TicketStatus::WaitingUser, $this->reread($ticket)->getStatus());

        $this->client->loginUser($user);
        $this->reply($ticket, 'Still broken');

        self::assertSame(TicketStatus::WaitingStaff, $this->reread($ticket)->getStatus());

        $this->client->submit($this->client->request('GET', $page)->filter('form[action$="/close"]')->form());

        self::assertResponseRedirects('/support');
        self::assertSame(TicketStatus::Closed, $this->reread($ticket)->getStatus());

        $this->client->request('POST', $page.'/reply', [CsrfGuard::FIELD => $this->formToken(CsrfGuard::TOKEN_ID), 'body' => 'one more question']);
        $this->client->followRedirect();

        self::assertSelectorExists('.toast-error');
        self::assertCount(3, $this->messagesOf($ticket));
    }

    #[Group('ACMS-SUP-0001')]
    #[Group('ACMS-US-0008')]
    public function testSupportAnswerIsSignedByDepartmentAndSidesAreOpposite(): void
    {
        $user = $this->member();
        $ticket = $this->ticket($user, 'No connection');
        $page = \sprintf('/support/%d', $ticket->getId());

        $this->client->loginUser($this->staff);
        $this->reply($ticket, 'Checking');

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', $page);

        self::assertSame([(string) $user->getId(), $ticket->getDepartment()], $crawler->filter('.chat-author')->each(static fn (Crawler $author): string => $author->text()));
        self::assertSame(['own', 'other'], self::sides($crawler));

        $this->client->loginUser($this->staff);

        self::assertSame(['other', 'own'], self::sides($this->client->request('GET', $page)));
    }

    #[Group('ACMS-UI-0009')]
    #[Group('ACMS-US-0008')]
    public function testListingSplitsActiveAndArchiveAndCarriesUserForStaff(): void
    {
        $owner = $this->member();
        $other = $this->member();
        $open = $this->ticket($owner, 'No connection');
        $closed = $this->ticket($other, 'Old question');
        self::service(Tickets::class)->close($closed);

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/support');

        self::assertSame([(string) $open->getId()], self::numbers($crawler));
        self::assertCount(0, $crawler->filter('#rows tbody a[href^="/admin/users/"]'), 'the cabinet listing does not name the user');

        $this->client->loginUser($this->staff);
        $crawler = $this->client->request('GET', '/support');

        self::assertSame([(string) $open->getId()], self::numbers($crawler));
        self::assertCount(1, $crawler->filter(\sprintf('#rows tbody a[href="/admin/users/%d"]', $owner->getId())));

        $crawler = $this->client->request('GET', '/support?tab=archive');
        self::assertSame([(string) $closed->getId()], self::numbers($crawler));
        self::assertCount(1, $crawler->filter(\sprintf('#rows tbody a[href="/admin/users/%d"]', $other->getId())));
    }

    #[Group('ACMS-CORE-0067')]
    #[Group('ACMS-US-0008')]
    public function testStaffSeesCommentAndNotesOfTheUserInTicket(): void
    {
        $user = $this->member()->setComment('Daisy LLC')->setNote("pays by invoice\ncall in the afternoon");
        $this->em->flush();
        $page = \sprintf('/support/%d', $this->ticket($user, 'No connection')->getId());

        $this->client->loginUser($this->staff);
        $side = $this->client->request('GET', $page)->filter('.ticket-side')->text();

        self::assertStringContainsString('Daisy LLC', $side);
        self::assertStringContainsString('call in the afternoon', $side);

        $this->client->loginUser($user);
        $side = $this->client->request('GET', $page)->filter('.ticket-side')->text();

        self::assertStringNotContainsString('Daisy', $side, 'the operator comment does not reach the cabinet');
        self::assertStringNotContainsString('call in the afternoon', $side, 'notes do not reach the cabinet');
    }

    #[Group('ACMS-SUP-0003')]
    #[Group('ACMS-US-0008')]
    public function testListingSiftsByDepartmentStatusAndSearchOnBothSides(): void
    {
        $this->configure([SupportSettings::DEPARTMENTS => 'Tech, Billing, Sales']);
        $owner = $this->member();
        $wanted = $this->ticket($owner, 'No connection', 'Tech');
        $answered = $this->ticket($owner, 'Invoice mismatch', 'Billing');
        $foreign = $this->ticket($this->member('stranger@example.org'), 'Invoice MISMATCH', 'Sales');
        self::service(Tickets::class)->reply($answered, $this->staff, true, 'Looking');
        $id = static fn (Ticket ...$tickets): array => array_map(static fn (Ticket $ticket): string => (string) $ticket->getId(), $tickets);

        foreach ([[$owner, ['Billing', 'Tech'], [$answered]], [$this->staff, ['Billing', 'Sales', 'Tech'], [$answered, $foreign]]] as [$viewer, $departments, $mismatched]) {
            $this->client->loginUser($viewer);

            self::assertSame($departments, $this->client->request('GET', '/support')->filter('select[name="department"] option:not([value=""])')->each(static fn (Crawler $option): string => (string) $option->attr('value')), 'departments of the visible tickets');
            self::assertSame($id($wanted), self::numbers($this->client->request('GET', '/support?department=Tech')));
            self::assertSame($id($answered), self::numbers($this->client->request('GET', '/support?status='.TicketStatus::WaitingUser->value)));
            self::assertEqualsCanonicalizing($id(...$mismatched), self::numbers($this->client->request('GET', '/support?q=mismatch')), 'the search ignores the case');
        }

        self::assertSame($id($foreign), self::numbers($this->client->request('GET', '/support?q=stranger')), 'the operator finds tickets by the user');

        $this->client->loginUser($owner);
        self::assertSame($id($answered), self::numbers($this->client->request('GET', '/support?q='.$answered->getId())), 'the number finds its ticket');
    }

    #[Group('ACMS-UI-0001')]
    #[Group('ACMS-US-0008')]
    public function testMenuCounterShowsWhoIsAwaited(): void
    {
        $user = $this->member();
        $ticket = $this->ticket($user, 'No connection');

        $this->client->loginUser($user);
        self::assertSame([], $this->menuCounter());

        $this->client->loginUser($this->staff);
        self::assertSame(['1'], $this->menuCounter());

        $this->reply($ticket, 'Checking');
        self::assertSame([], $this->menuCounter());

        $this->client->loginUser($user);
        self::assertSame(['1'], $this->menuCounter());
    }

    #[Group('ACMS-SUP-0005')]
    #[Group('ACMS-CORE-0086')]
    #[Group('ACMS-US-0008')]
    public function testStaffEditsAndRemovesMessageWhileUserCannot(): void
    {
        $user = $this->member();
        $ticket = $this->ticket($user, 'No connection');
        $message = $this->messagesOf($ticket)[0];
        $action = \sprintf('/support/message/%d/', $message->getId());

        $this->client->loginUser($user);
        $this->client->request('POST', $action.'edit', [CsrfGuard::FIELD => $this->formToken(CsrfGuard::TOKEN_ID), 'body' => 'forgery']);

        self::assertResponseRedirects('/', null, 'a closed action leads the user to the home page without the edit');
        self::assertSame('First message', $this->messagesOf($ticket)[0]->getBody());

        $this->client->loginUser($this->staff);
        $crawler = $this->client->request('GET', \sprintf('/support/%d', $ticket->getId()));
        $this->client->submit($crawler->filter(\sprintf('form[action="%sedit"]', $action))->form(['body' => 'The internet is gone since morning']));
        $this->client->followRedirect();

        $edited = $this->messagesOf($ticket)[0];
        self::assertSame('The internet is gone since morning', $edited->getBody());
        self::assertNotNull($edited->getEditedAt());
        self::assertSelectorExists('.chat-edited');

        $crawler = $this->client->request('GET', \sprintf('/support/%d', $ticket->getId()));
        $this->client->submit($crawler->filter(\sprintf('form[action="%sdrop"]', $action))->form());
        $this->client->followRedirect();

        self::assertSame([], $this->messagesOf($ticket));
    }

    #[Group('ACMS-SUP-0002')]
    #[Group('ACMS-US-0008')]
    public function testClosedTicketTakesNeitherEditNorRemoval(): void
    {
        $ticket = $this->ticket($this->member(), 'No connection');
        $message = $this->messagesOf($ticket)[0];
        self::service(Tickets::class)->close($ticket);

        $this->client->loginUser($this->staff);

        foreach (['edit', 'drop'] as $action) {
            $this->client->request('POST', \sprintf('/support/message/%d/%s', $message->getId(), $action), [CsrfGuard::FIELD => $this->formToken(CsrfGuard::TOKEN_ID), 'body' => 'edit']);
            $this->client->followRedirect();

            self::assertSelectorExists('.toast-error');
        }

        self::assertSame('First message', $this->messagesOf($ticket)[0]->getBody());
    }

    #[Group('ACMS-CORE-0029')]
    #[Group('ACMS-SUP-0006')]
    #[Group('ACMS-US-0008')]
    public function testAttachmentReachesOnlyTheSidesOfItsTicket(): void
    {
        $user = $this->member();
        $stranger = $this->member();

        $this->client->loginUser($user);
        $this->open(['subject' => 'Screenshot', 'body' => 'Here is the error'], [$this->picture('shot.png'), $this->scratchFile('fake.png', 'not a picture')]);

        $ticket = $this->ticketOf($user) ?? self::fail('no ticket was opened');
        [$attachment, $fake] = $this->attachmentsOf($this->messagesOf($ticket)[0]) + [null, null];
        self::assertInstanceOf(TicketAttachment::class, $attachment, 'the attachment is not kept');
        self::assertInstanceOf(TicketAttachment::class, $fake, 'the attachment is not kept');
        self::assertSame(['shot.png', 'image/png'], [$attachment->getName(), $attachment->getMime()]);
        self::assertSame(['fake.png', 'text/plain'], [$fake->getName(), $fake->getMime()], 'the type follows the content, not the name');
        $address = \sprintf('/support/attachment/%d', $attachment->getId());

        foreach ([[$attachment, 'inline'], [$fake, 'attachment']] as [$file, $disposition]) {
            $this->client->request('GET', \sprintf('/support/attachment/%d', $file->getId()));
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
            self::assertStringStartsWith($disposition, (string) $this->client->getResponse()->headers->get('Content-Disposition'), 'only pictures and PDF show in the browser');
        }

        $this->client->loginUser($stranger);
        $this->client->request('GET', $address);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', \sprintf('/support/%d', $ticket->getId()));
        self::assertResponseStatusCodeSame(404);

        $this->client->loginUser($this->staff);
        $this->client->request('GET', $address);
        self::assertResponseIsSuccessful();
    }

    #[Group('ACMS-SUP-0007')]
    #[Group('ACMS-US-0008')]
    public function testAttachmentBeyondTypeOrSizeIsNotStored(): void
    {
        $this->configure([SupportSettings::ATTACHMENT_MAX_MB => '1']);
        $user = $this->member();
        $this->client->loginUser($user);

        foreach ([$this->scratchFile('dump.exe', 'MZ'), $this->scratchFile('big.log', str_repeat('x', 1024 * 1024 + 1))] as $file) {
            $this->open(['subject' => 'Archive', 'body' => 'Logs'], [$file]);

            self::assertSelectorExists('.toast-error');
            self::assertNull($this->ticketOf($user));
        }
    }

    #[Group('ACMS-SUP-0007')]
    #[Group('ACMS-US-0008')]
    public function testDroppedAttachmentLeavesNeitherRecordNorFile(): void
    {
        foreach (['edit', 'drop'] as $action) {
            $user = $this->member();
            $this->client->loginUser($user);
            $this->open(['subject' => 'Screenshot', 'body' => 'Here is the error'], [$this->picture('shot.png')]);

            $message = $this->messagesOf($this->ticketOf($user) ?? self::fail('no ticket was opened'))[0];
            $attachment = $this->attachmentsOf($message)[0] ?? self::fail('the attachment is not kept');
            $id = (int) $attachment->getId();
            $path = self::service(Tickets::class)->path($attachment);
            self::assertFileExists($path);

            $this->client->loginUser($this->staff);
            $this->client->request('POST', \sprintf('/support/message/%d/%s', $message->getId(), $action), [
                CsrfGuard::FIELD => $this->formToken(CsrfGuard::TOKEN_ID),
                'body' => 'Here is the error',
                'dropped' => [(string) $id],
            ]);
            $this->client->followRedirect();

            self::assertNull(self::service(TicketAttachmentRepository::class)->find($id), $action);
            self::assertFileDoesNotExist($path, $action);
        }
    }

    #[Group('ACMS-SUP-0008')]
    #[Group('ACMS-US-0008')]
    public function testSilentUserClosesTheTicketAndZeroTermStopsIt(): void
    {
        $tickets = self::service(Tickets::class);
        $answered = $this->ticket($this->member(), 'Awaits the user');
        $asking = $this->ticket($this->member(), 'Awaits support');
        $tickets->reply($answered, $this->staff, true, 'Check the cable');
        $run = self::command('adzercms:support');
        $start = now();

        foreach ([['0', '+30 days', ''], ['7', '+6 days', ''], ['7', '+8 days', 'closed without the user: 1']] as [$days, $moment, $said]) {
            $this->configure([SupportSettings::AUTO_CLOSE_DAYS => $days]);
            self::mockTime($start->modify($moment));

            self::assertSame(Command::SUCCESS, $run->execute([]));
            self::assertSame($said, trim($run->getDisplay()), \sprintf('term %s, moment %s', $days, $moment));
        }

        self::assertSame(TicketStatus::Closed, $this->reread($answered)->getStatus());
        self::assertSame(TicketStatus::Open, $this->reread($asking)->getStatus());
    }

    #[Group('ACMS-SUP-0009')]
    #[Group('ACMS-US-0008')]
    public function testAutoClosedTicketsAreNamedToTheUserInOneNotice(): void
    {
        $tickets = self::service(Tickets::class);
        $user = $this->member('human@example.org');
        $first = $this->ticket($user, 'Site does not load');
        $second = $this->ticket($user, 'Plan change');
        $quiet = $this->ticket($this->member('second@example.org'), 'Invoice question');

        foreach ([$first, $second, $quiet] as $ticket) {
            $tickets->reply($ticket, $this->staff, true, 'Please clarify');
        }

        $this->configure([SupportSettings::AUTO_CLOSE_DAYS => '7']);
        $notices = self::service(NoticeRepository::class);

        self::assertSame(3, $tickets->autoClose(now('+8 days')));

        $closed = $notices->findBy(['event' => SupportEvent::TicketClosed->key(), 'user' => $user]);
        self::assertCount(1, $closed, 'one notice per user, not per ticket');
        self::assertCount(2, $notices->findBy(['event' => SupportEvent::TicketClosed->key()]));
        foreach ([$first, $second] as $ticket) {
            self::assertStringContainsString((string) $ticket->getId(), $closed[0]->getBody());
            self::assertStringContainsString($ticket->getSubject(), $closed[0]->getBody());
        }
    }

    #[Group('ACMS-SUP-0003')]
    #[Group('ACMS-US-0008')]
    public function testDepartmentsOfSeparatorsOnlyReadAsTheDefaultNames(): void
    {
        $default = self::support()->departments();

        $this->configure([SupportSettings::DEPARTMENTS => ' , , ']);

        self::assertSame($default, self::support()->departments());
    }

    #[Group('ACMS-SUP-0012')]
    #[Group('ACMS-US-0010')]
    public function testStaffDigestCarriesWaitingTicketsAndMarksTheNotifiedOnes(): void
    {
        $digest = self::service(StaffDigest::class);
        $user = $this->member();
        $waiting = $this->ticket($user, 'No connection');
        $answered = $this->ticket($this->member(), 'Invoice');
        $this->client->loginUser($this->staff);
        $this->reply($answered, 'Answered');

        $collected = $digest->collect(false);

        self::assertSame([(int) $waiting->getId()], $collected['ids'], 'an answered ticket does not await the operator');
        self::assertStringContainsString('1', $collected['summary']);
        self::assertStringContainsString((string) $user->getId(), $collected['lines'][0]);

        self::assertSame(1, $digest->mark($collected['ids']));
        self::assertSame([], $digest->collect(true)['ids'], 'a notified ticket does not go to the event digest');
        self::assertSame([(int) $waiting->getId()], $digest->collect(false)['ids'], 'the daily digest keeps the ticket until the answer');
    }

    #[Group('ACMS-SUP-0012')]
    #[Group('ACMS-US-0010')]
    public function testStaffDigestMarksOverdueTicketsByTheirWaitingTime(): void
    {
        $now = new \DateTimeImmutable('2026-10-16 12:00');
        $user = $this->member();
        $ticket = $this->ticket($user, 'No connection');
        $ticket->touch($now->modify('-3 days'));
        $this->em->flush();
        $digest = self::service(StaffDigest::class);

        $daily = $digest->collect(false, $now)['lines'][0];
        $event = $digest->collect(true, $now)['lines'][0];

        foreach (['#'.$ticket->getId(), (string) $user->getId(), '72', 'No connection'] as $part) {
            self::assertStringContainsString($part, $daily, 'the daily digest: number, user, waiting time, subject');
        }
        self::assertSame(\sprintf('#%d %d, %s, %s', $ticket->getId(), $user->getId(), $ticket->getDepartment(), 'No connection'), $event, 'the event digest: number, user, department, subject');
    }

    #[Group('ACMS-SUP-0012')]
    #[Group('ACMS-US-0010')]
    public function testStaffDigestCutsLongSectionByTheRestAndIsEmptyWithoutDeeds(): void
    {
        $digest = self::service(StaffDigest::class);
        $opened = 0;

        foreach ([[0, 0, ''], [StaffDigest::SHOWN, StaffDigest::SHOWN, ''], [StaffDigest::SHOWN + 2, StaffDigest::SHOWN + 1, '2']] as [$waiting, $lines, $rest]) {
            for (; $opened < $waiting; ++$opened) {
                $this->ticket($this->member(), \sprintf('Request %d', $opened));
            }

            $collected = $digest->collect(false);

            self::assertCount($lines, $collected['lines'], \sprintf('%d waiting', $waiting));
            self::assertSame(0 === $waiting, '' === $collected['summary'], 'no deeds — no summary');
            if ('' !== $rest) {
                self::assertStringContainsString($rest, (string) end($collected['lines']), 'the last line names the rest');
            }
        }
    }

    #[Group('ACMS-UI-0014')]
    #[Group('ACMS-UI-0015')]
    #[Group('ACMS-US-0008')]
    public function testTicketListingKeepsItsQueriesWhileRowsGrow(): void
    {
        $this->client->loginUser($this->staff);

        $this->assertListingKeepsItsQueries('/support', function (int $from, int $to): void {
            for ($number = $from; $number <= $to; ++$number) {
                $this->ticket($this->member(), \sprintf('Request %d', $number));
            }
        });
    }

    private function member(?string $email = null): User
    {
        return $this->record(\sprintf('7999%07d', ++$this->phones), $email);
    }

    private function ticket(User $user, string $subject, ?string $department = null): Ticket
    {
        $opened = self::service(Tickets::class)->open($user, $subject, $department ?? self::support()->departments()[0], 'First message');

        return $opened instanceof Ticket ? $opened : self::fail('no ticket was opened');
    }

    /**
     * @param array<string, string> $fields
     * @param list<UploadedFile>    $files
     */
    private function open(array $fields, array $files = []): void
    {
        $this->client->request(
            'POST',
            '/support/new',
            $fields + ['department' => self::support()->departments()[0], CsrfGuard::FIELD => $this->formToken(CsrfGuard::TOKEN_ID)],
            ['files' => $files],
        );
    }

    private function reply(Ticket $ticket, string $body): void
    {
        $crawler = $this->client->request('GET', \sprintf('/support/%d', $ticket->getId()));
        $this->client->submit($crawler->filter('form.chat-reply')->form(['body' => $body]));
    }

    /** @return list<string> */
    private function menuCounter(): array
    {
        return $this->client->request('GET', '/support')->filter('a.nav-item[href="/support"] .badge')->each(static fn (Crawler $badge): string => $badge->text());
    }

    private function ticketOf(User $user): ?Ticket
    {
        return self::service(TicketRepository::class)->findOneBy(['user' => $user]);
    }

    /** @return list<TicketMessage> */
    private function messagesOf(Ticket $ticket): array
    {
        return self::service(TicketMessageRepository::class)->findBy(['ticket' => $ticket], ['id' => 'ASC']);
    }

    /** @return list<TicketAttachment> */
    private function attachmentsOf(TicketMessage $message): array
    {
        return self::service(TicketAttachmentRepository::class)->findBy(['message' => $message], ['id' => 'ASC']);
    }

    /** @return list<string> */
    private static function sides(Crawler $crawler): array
    {
        return $crawler->filter('.chat-message')->each(static fn (Crawler $message): string => trim(str_replace('chat-message', '', (string) $message->attr('class'))));
    }

    /** @return list<string> */
    private static function numbers(Crawler $crawler): array
    {
        return $crawler->filter('#rows tbody tr td:first-child')->each(static fn (Crawler $cell): string => $cell->text());
    }

    private static function support(): SupportSettings
    {
        return self::service(SupportSettings::class);
    }
}
