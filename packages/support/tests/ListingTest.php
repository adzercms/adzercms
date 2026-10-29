<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Tests;

use AdzerCMS\Core\Tests\Queries;
use AdzerCMS\Support\Entity\Ticket;
use AdzerCMS\Support\SupportSettings;
use AdzerCMS\Support\Tickets;
use AdzerCMS\Support\TicketStatus;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class ListingTest extends SupportTestCase
{
    use Queries;

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

    /** @return list<string> */
    private function menuCounter(): array
    {
        return $this->client->request('GET', '/support')->filter('a.nav-item[href="/support"] .badge')->each(static fn (Crawler $badge): string => $badge->text());
    }

    /** @return list<string> */
    private static function numbers(Crawler $crawler): array
    {
        return self::firstCells($crawler->filter('#rows'));
    }
}
