<?php

declare(strict_types=1);

namespace AdzerCMS\Mailing\Tests;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Module\Snapshot;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Notice\Fixture\Relay\RelayGateway;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class GatewayTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use Configuration;

    private const CONTRACT = ['id' => '1042', 'name' => 'Village network', 'email' => 'owner@example.org', 'phone' => '79991234567', 'balance' => '152.40', 'senders' => 'VILLAGE'];

    private RelayGateway $relay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure([
            RelayGateway::SETTINGS.Switches::ENABLED => '1',
            RelayGateway::SETTINGS.RelayGateway::TOKEN => 'relay-token',
        ]);
        $admin = $this->record('79990000001')->setRole(Role::Admin);
        $this->em->flush();
        $this->client->loginUser($admin);
        $this->relay = self::service(RelayGateway::class);
        $this->relay->contract = self::CONTRACT;
    }

    #[Group('ACMS-CORE-0135')]
    #[Group('ACMS-US-0005')]
    public function testContractIsPolledByRequestAndHeldUntilTheNextPoll(): void
    {
        self::assertSame(self::phrase('mailing.gateway.unread'), trim($this->gateway(false)->filter('#gateway .muted')->text()), 'nothing is shown before the first poll');
        self::assertSame(0, $this->relay->polled);

        $polled = $this->gateway(true);
        self::assertSame(1, $this->relay->polled);
        foreach (self::CONTRACT as $field => $value) {
            self::assertSame($value, self::tile($polled, self::phrase('mailing.gateway.'.$field)), $field);
        }

        $this->relay->contract['balance'] = '10.00';
        self::assertSame('152.40', self::tile($this->gateway(false), self::phrase('mailing.gateway.balance')), 'the shown contract holds until the next poll');
        self::assertSame('152.40', self::tile($this->client->request('GET', '/admin/notices?view=journal&tab=sms'), self::phrase('mailing.gateway.balance')), 'the tab of messages shows the held contract');
        self::assertSame(1, $this->relay->polled, 'showing the held contract asks the gateway nothing');

        self::assertSame('10.00', self::tile($this->gateway(true), self::phrase('mailing.gateway.balance')));
        self::assertSame(2, $this->relay->polled);
    }

    #[Group('ACMS-CORE-0135')]
    #[Group('ACMS-US-0005')]
    public function testRefusalOfTheGatewayIsShownInItsWords(): void
    {
        $this->relay->refusal = 'Account is blocked';

        self::assertSame('Account is blocked', trim($this->gateway(true)->filter('#gateway .flash-error')->text()));
    }

    private function gateway(bool $rescan): Crawler
    {
        $page = $this->client->request('GET', '/admin/notices/gateway', server: $rescan ? ['HTTP_'.strtoupper(str_replace('-', '_', Snapshot::RESCAN)) => '1'] : []);
        self::assertResponseIsSuccessful();

        return $page;
    }
}
