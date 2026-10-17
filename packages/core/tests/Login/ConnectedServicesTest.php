<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Login;

use AdzerCMS\Contracts\Login\Profile;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\Status;
use AdzerCMS\Core\Entity\ConnectedService;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Login\ConnectedServices;
use AdzerCMS\Core\Login\ServiceConnected;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Repository\ConnectedServiceRepository;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Login\Fixture\Latch\LatchService;
use AdzerCMS\Core\Tests\Login\Fixture\Wicket\WicketService;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Translation\TranslatableMessage;

final class ConnectedServicesTest extends SchemaTestCase
{
    use Accounts;
    use Configuration;

    private const LATCH = 'latch';
    private const WICKET = 'wicket';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure([LatchService::SETTINGS.Switches::ENABLED => '1', LatchService::SETTINGS.LatchService::CLIENT => 'latch-client']);
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0125')]
    public function testConnectedProfileEntersItsRecordAndNamesTheConnection(): void
    {
        $user = $this->record('+7 900 111-22-33');
        $this->em->persist(new ConnectedService($user, self::LATCH, 'p1', 'old'));
        $this->em->flush();

        self::assertSame($user, $this->services()->enter(self::LATCH, new Profile('p1', account: 'new')));
        self::assertSame('new', $this->connection($user)?->getAccount());

        $this->services()->enter(self::LATCH, new Profile('p1'));
        self::assertSame('new', $this->connection($user)?->getAccount(), 'a nameless profile keeps the account name');
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0065')]
    public function testProfileEntersRecordOfItsContactAndFillsEmptyData(): void
    {
        $user = $this->record('+7 900 111-22-33');
        $this->record('+7 900 444-55-66', 'taken@example.com');

        self::assertSame($user, $this->services()->enter(self::LATCH, new Profile('p1', '8 900 111 22 33', 'TAKEN@example.com', 'Ivan', 'Petrov')));

        $user = $this->reread($user);
        self::assertSame('p1', $this->connection($user)?->getProfile());
        self::assertTrue($user->isNamed());
        self::assertNull($user->getEmail(), 'an email of another record is not taken');

        $other = $this->record('+7 900 777-88-99');
        $other->setName('Sidorov', null, null);
        $this->em->flush();
        self::assertNull($this->services()->link($other, self::LATCH, new Profile('p2', email: 'free@example.com', firstName: 'Ignored')));
        self::assertSame('free@example.com', $this->reread($other)->getEmail());
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0173')]
    public function testProfileWithoutPhoneEntersRecordOfItsEmail(): void
    {
        $user = $this->record('+7 900 111-22-33', 'owner@example.com');

        self::assertSame($user, $this->services()->enter(self::LATCH, new Profile('p1', email: 'Owner@Example.com')));
        self::assertSame('p1', $this->connection($user)?->getProfile());
    }

    #[Group('ACMS-US-0002')]
    public function testProfileWithoutRecordIsSentToLoginAndConnect(): void
    {
        self::assertSame('core.login.unknown', self::reason($this->services()->enter(self::LATCH, new Profile('p1', email: 'nobody@example.com'))));
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0088')]
    public function testStoppedAndDeletedRecordsDoNotEnter(): void
    {
        $this->record('+7 900 111-22-33')->setStatus(Status::Stopped);
        $this->record('+7 900 444-55-66')->delete();
        $this->em->flush();

        self::assertSame('core.account.stopped', self::reason($this->services()->enter(self::LATCH, new Profile('p1', '79001112233'))));
        self::assertSame('core.account.deleted', self::reason($this->services()->enter(self::LATCH, new Profile('p2', '79004445566'))));
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0083')]
    #[Group('ACMS-CORE-0173')]
    public function testProfileIsOfOneRecordAndRecordHasOneProfileOfService(): void
    {
        $owner = $this->record('+7 900 111-22-33');
        $other = $this->record('+7 900 444-55-66');
        $this->em->persist(new ConnectedService($owner, self::LATCH, 'p1', null));
        $this->em->flush();

        self::assertSame('core.login.taken', self::reason($this->services()->link($other, self::LATCH, new Profile('p1'))));
        self::assertSame('core.login.other', self::reason($this->services()->link($owner, self::LATCH, new Profile('p2'))));
        self::assertSame('core.login.other', self::reason($this->services()->enter(self::LATCH, new Profile('p3', '79001112233'))), 'a contact match does not connect a second profile');
        self::assertSame(['p1'], array_map(static fn (ConnectedService $service): string => $service->getProfile(), array_values(self::service(ConnectedServiceRepository::class)->of($owner))));
    }

    #[Group('ACMS-CORE-0900')]
    public function testConnectingProfileIsAnnouncedAfterCommitAndEnteringIsNot(): void
    {
        $user = $this->record('+7 900 111-22-33');
        $connection = $this->em->getConnection();
        /** @var \ArrayObject<int, list<mixed>> $heard */
        $heard = new \ArrayObject();
        self::service(EventDispatcherInterface::class)->addListener(
            ServiceConnected::class,
            static function (ServiceConnected $event) use ($heard, $connection): void {
                $heard->append([$event->user, $event->service, $event->profile->id, $connection->fetchOne('SELECT profile FROM connected_service WHERE user_id = ?', [$event->user->getId()])]);
            },
        );

        self::assertNull($this->services()->link($user, self::LATCH, new Profile('p1')));
        self::assertSame($user, $this->services()->enter(self::LATCH, new Profile('p1')));

        self::assertSame([[$user, self::LATCH, 'p1', 'p1']], $heard->getArrayCopy());
    }

    #[Group('ACMS-US-0002')]
    #[Group('ACMS-CORE-0084')]
    public function testLastWayOfSigningInIsNotUnlinked(): void
    {
        $user = $this->record('+7 900 111-22-33')->setPassword(null);
        $this->em->persist(new ConnectedService($user, self::LATCH, 'p1', null));
        $this->em->persist(new ConnectedService($user, self::WICKET, 'w1', null));
        $this->em->flush();

        self::assertSame('core.login.last_way', self::reason($this->services()->unlink($user, self::LATCH)), 'a disabled service is no way to sign in');

        $this->configure([WicketService::SETTINGS.Switches::ENABLED => '1']);
        self::assertNull($this->services()->unlink($user, self::LATCH));
        self::assertSame('core.login.absent', self::reason($this->services()->unlink($user, self::LATCH)));
        self::assertSame('core.login.last_way', self::reason($this->services()->unlink($user, self::WICKET)));

        $user->setPassword('hash');
        self::assertNull($this->services()->unlink($user, self::WICKET));
    }

    private function services(): ConnectedServices
    {
        return self::service(ConnectedServices::class);
    }

    /** @phpstan-impure */
    private function connection(User $user): ?ConnectedService
    {
        return self::service(ConnectedServiceRepository::class)->of($user)[self::LATCH] ?? null;
    }

    private static function reason(mixed $outcome): string
    {
        return $outcome instanceof Refusal && $outcome->reason instanceof TranslatableMessage
            ? $outcome->reason->getMessage()
            : self::fail('the outcome is not a refusal');
    }
}
