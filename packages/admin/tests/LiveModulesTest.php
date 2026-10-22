<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Tests;

use AdzerCMS\Admin\LiveModules;
use AdzerCMS\Core\Entity\ModuleOperation;
use AdzerCMS\Core\Module\ModuleAction;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Realtime\Channels;
use AdzerCMS\Realtime\Hub;
use AdzerCMS\Realtime\LiveChanges;
use AdzerCMS\Realtime\Tests\Fixture\HeraldHub;
use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\Group;

final class LiveModulesTest extends SchemaTestCase
{
    private HeraldHub $hub;

    private LiveChanges $changes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hub = new HeraldHub();
        $this->changes = new LiveChanges(new Hub([$this->hub]), new Channels('http://localhost'), [new LiveModules()]);
        $this->em->getEventManager()->addEventListener([Events::onFlush, Events::postFlush], $this->changes);
    }

    protected function tearDown(): void
    {
        $this->em->getEventManager()->removeEventListener([Events::onFlush, Events::postFlush], $this->changes);
        parent::tearDown();
    }

    #[Group('ACMS-CORE-0980')]
    #[Group('ACMS-UI-0062')]
    #[Group('ACMS-US-0004')]
    public function testOperationStateReachesStaffChannelOnly(): void
    {
        $staff = [['staff:localhost']];
        $operation = new ModuleOperation('acme/shop', ModuleAction::Install);
        $this->em->persist($operation);
        $this->em->flush();
        self::assertSame($staff, $this->released(), 'a filed operation goes to the operators: it has no owner');

        $operation->start();
        $this->em->flush();
        self::assertSame($staff, $this->released(), 'the running operation shows its progress in the row');

        $operation->finish(null);
        $this->em->flush();
        self::assertSame($staff, $this->released());
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
