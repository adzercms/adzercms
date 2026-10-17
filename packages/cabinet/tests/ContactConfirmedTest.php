<?php

declare(strict_types=1);

namespace AdzerCMS\Cabinet\Tests;

use AdzerCMS\Core\Account\ContactConfirmed;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Confirmation\Confirmations;
use AdzerCMS\Core\Confirmation\ConfirmationService;
use AdzerCMS\Core\Confirmation\Purpose;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class ContactConfirmedTest extends SchemaTestCase
{
    use Accounts;

    private const REGISTERED = '79004445566';
    private const CHANGED = '79007778899';

    #[Group('ACMS-CORE-0902')]
    public function testAppliedContactIsAnnouncedAfterCommit(): void
    {
        $connection = $this->em->getConnection();
        /** @var \ArrayObject<int, list<mixed>> $heard */
        $heard = new \ArrayObject();
        self::service(EventDispatcherInterface::class)->addListener(
            ContactConfirmed::class,
            static function (ContactConfirmed $event) use ($heard, $connection): void {
                $heard->append([$event->user, $event->kind, $event->contact, $connection->fetchOne('SELECT phone FROM users WHERE id = ?', [$event->user->getId()])]);
            },
        );
        $confirmations = self::service(Confirmations::class);
        $service = self::service(ConfirmationService::class);

        $code = $confirmations->code(Purpose::Registration, null, self::REGISTERED) ?? self::fail('the registration code is not issued');
        $user = $service->completeRegistration(self::REGISTERED, $code, 'secret-password') ?? self::fail('the record is not opened');
        $code = $confirmations->code(Purpose::Contact, $user, self::CHANGED) ?? self::fail('the contact code is not issued');
        self::assertTrue($service->completeContactCode($user, $code));

        self::assertSame([
            [$user, ContactKind::Phone, self::REGISTERED, self::REGISTERED],
            [$user, ContactKind::Phone, self::CHANGED, self::CHANGED],
        ], $heard->getArrayCopy());
    }
}
