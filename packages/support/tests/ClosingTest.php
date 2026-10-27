<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Tests;

use AdzerCMS\Core\Attachment\Attachments;
use AdzerCMS\Core\Repository\NoticeRepository;
use AdzerCMS\Core\Security\CsrfGuard;
use AdzerCMS\Support\Repository\TicketRepository;
use AdzerCMS\Support\SupportEvent;
use AdzerCMS\Support\SupportSettings;
use AdzerCMS\Support\Tickets;
use AdzerCMS\Support\TicketStatus;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;

use function Symfony\Component\Clock\now;

final class ClosingTest extends SupportTestCase
{
    #[Group('ACMS-SUP-0019')]
    #[Group('ACMS-US-0008')]
    public function testStaffDeletesTicketInAnyStateWithItsConversation(): void
    {
        $user = $this->member();
        $this->client->loginUser($user);
        $this->open(['subject' => 'Screenshot', 'body' => 'Here is the error'], [$this->picture('shot.png')]);

        $ticket = $this->ticketOf($user) ?? self::fail('no ticket was opened');
        $id = (int) $ticket->getId();
        $path = self::service(Attachments::class)->path($this->attachmentsOf($this->messagesOf($ticket)[0])[0] ?? self::fail('the attachment is not kept'));
        $action = \sprintf('/support/%d/drop', $id);

        self::assertCount(0, $this->client->request('GET', \sprintf('/support/%d', $id))->filter(\sprintf('form[action="%s"]', $action)));
        $this->client->request('POST', $action, [CsrfGuard::FIELD => $this->formToken(CsrfGuard::TOKEN_ID)]);
        self::assertNotNull(self::service(TicketRepository::class)->find($id), 'the user does not delete the ticket');

        self::service(Tickets::class)->close($ticket);
        $this->client->loginUser($this->staff);
        $crawler = $this->client->request('GET', \sprintf('/support/%d', $id));
        $this->client->submit($crawler->filter(\sprintf('form[action="%s"][data-confirm]', $action))->form());
        $this->client->followRedirect();

        self::assertNull(self::service(TicketRepository::class)->find($id), 'a closed ticket is deleted too');
        self::assertFileDoesNotExist($path, 'the attachments leave with the ticket');
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
}
