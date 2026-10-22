<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Tests;

use AdzerCMS\Leads\Handling;
use AdzerCMS\Leads\LiveLeads;
use AdzerCMS\Leads\Repository\StageRepository;
use AdzerCMS\Leads\StageGroup;
use AdzerCMS\Realtime\Channels;
use AdzerCMS\Realtime\Hub;
use AdzerCMS\Realtime\LiveChanges;
use AdzerCMS\Realtime\Tests\Fixture\HeraldHub;
use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\ParameterBag;

final class LiveLeadsTest extends LeadsTestCase
{
    private const STAFF = [['staff:localhost']];

    private HeraldHub $hub;

    private LiveChanges $changes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hub = new HeraldHub();
        $this->changes = new LiveChanges(new Hub([$this->hub]), new Channels('http://localhost'), [new LiveLeads()]);
        $this->em->getEventManager()->addEventListener([Events::onFlush, Events::postFlush], $this->changes);
    }

    protected function tearDown(): void
    {
        $this->em->getEventManager()->removeEventListener([Events::onFlush, Events::postFlush], $this->changes);
        parent::tearDown();
    }

    #[Group('ACMS-LEAD-0019')]
    #[Group('ACMS-UI-0060')]
    #[Group('ACMS-US-0041')]
    public function testNewLeadItsHandlingAndNoteReachStaffChannel(): void
    {
        $handling = self::service(Handling::class);

        $lead = $this->lead(StageGroup::Fresh, 'Анна', '79990000002');
        self::assertSame(self::STAFF, $this->released(), 'a new lead goes to the operators');

        $work = self::service(StageRepository::class)->first(StageGroup::Work);
        self::assertSame([], $handling->save($lead, $this->operator, new ParameterBag([Handling::STAGE => (string) $work->getId()])));
        self::assertSame(self::STAFF, $this->released(), 'the saved handling goes to the operators');

        self::assertNull($handling->note($lead, $this->operator, 'Перезвонить завтра'));
        self::assertSame(self::STAFF, $this->released(), 'the note goes to the operators');
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
