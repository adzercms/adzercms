<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Tests;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Realtime\Channels;
use AdzerCMS\Realtime\Hub;
use AdzerCMS\Realtime\LiveChanges;
use AdzerCMS\Realtime\Tests\Fixture\HeraldHub;
use AdzerCMS\Support\Entity\Ticket;
use AdzerCMS\Support\LiveTickets;
use AdzerCMS\Support\Repository\TicketMessageRepository;
use AdzerCMS\Support\SupportSettings;
use AdzerCMS\Support\Tickets;
use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\Group;

use function Symfony\Component\Clock\now;

final class LiveTicketsTest extends SchemaTestCase
{
    use Accounts;

    private const STAFF = 'staff:localhost';

    private HeraldHub $hub;

    private LiveChanges $changes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hub = new HeraldHub();
        $this->changes = new LiveChanges(new Hub([$this->hub]), new Channels('http://localhost'), [new LiveTickets()]);
        $this->em->getEventManager()->addEventListener([Events::onFlush, Events::postFlush], $this->changes);
    }

    protected function tearDown(): void
    {
        $this->em->getEventManager()->removeEventListener([Events::onFlush, Events::postFlush], $this->changes);
        parent::tearDown();
    }

    #[Group('ACMS-UI-0060')]
    #[Group('ACMS-UI-0062')]
    #[Group('ACMS-SUP-0010')]
    public function testTicketEventsReachOwnerAndStaffChannels(): void
    {
        $user = $this->record('79990000020');
        $operator = $this->record('79990000021')->setRole(Role::Admin);
        $this->em->flush();
        $this->released();
        $tickets = self::service(Tickets::class);
        $both = [['herald:'.$user->getId().'@localhost', self::STAFF]];

        $ticket = $tickets->open($user, 'No connection', self::service(SupportSettings::class)->departments()[0], 'Help');
        self::assertInstanceOf(Ticket::class, $ticket);
        self::assertSame($both, $this->released(), 'a new ticket goes to the owner and the operators');

        self::assertNull($tickets->reply($ticket, $operator, true, 'Looking'));
        self::assertSame($both, $this->released(), 'the answer goes to the conversation of the owner and the list of the operators');

        self::assertNull($tickets->edit($this->lastMessageOf($ticket), 'Looking closely'));
        self::assertSame($both, $this->released(), 'the edited message goes to both sides');

        self::assertNull($tickets->remove($this->lastMessageOf($ticket)));
        self::assertSame($both, $this->released(), 'the removed message leaves the conversation of both sides');

        $ticket->staffNotified(now());
        $this->em->flush();
        self::assertSame([], $this->released(), 'the service mark of the ticket gives no event');

        $tickets->close($ticket);
        self::assertSame($both, $this->released(), 'the closing goes to both sides');
    }

    private function lastMessageOf(Ticket $ticket): \AdzerCMS\Support\Entity\TicketMessage
    {
        return self::service(TicketMessageRepository::class)->findBy(['ticket' => $ticket], ['id' => 'DESC'])[0] ?? self::fail('the ticket has no messages');
    }

    /**
     * @phpstan-impure
     *
     * @return list<list<string>>
     */
    private function released(): array
    {
        $this->changes->release();

        return $this->hub->released();
    }
}
