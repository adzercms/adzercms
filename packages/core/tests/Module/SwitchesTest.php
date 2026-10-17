<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module;

use AdzerCMS\Contracts\Module\Switchable;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Setting\Catalog;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Notice\Fixture\Beacon\BeaconGateway;
use AdzerCMS\Core\Tests\Notice\Fixture\Relay\RelayGateway;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;

final class SwitchesTest extends SchemaTestCase
{
    use Configuration;

    private const RELAY = RelayGateway::SETTINGS;
    private const BEACON = BeaconGateway::SETTINGS;

    #[Group('ACMS-CORE-0004')]
    #[Group('ACMS-CORE-0053')]
    public function testCatalogHoldsSwitchAndCredentialsUnderKindAndModule(): void
    {
        $fields = self::service(Catalog::class)->fields();

        self::assertSame(
            [[Type::Toggle, false], [Type::Secret, true], [Type::Text, false]],
            array_map(
                static fn (string $name): array => [$fields[$name]->type, $fields[$name]->required],
                [self::RELAY.Switches::ENABLED, self::RELAY.RelayGateway::TOKEN, self::RELAY.RelayGateway::SENDER],
            ),
        );
    }

    #[Group('ACMS-CORE-0004')]
    public function testFirstEnabledWithRequiredCredentialsGoesInInstanceOrder(): void
    {
        $switches = self::service(Switches::class);
        $this->configure([self::BEACON.Switches::ENABLED => '1', self::BEACON.BeaconGateway::LOGIN => 'beacon-login', self::RELAY.Switches::ENABLED => '1']);
        $beacon = $switches->first(Switchable::class);
        self::assertInstanceOf(BeaconGateway::class, $beacon[0] ?? null, 'an enabled module without required credentials is skipped');

        $this->configure([self::RELAY.RelayGateway::TOKEN => 'relay-token']);
        [$first, $credentials] = $switches->first(Switchable::class) ?? self::fail('no module is enabled');
        self::assertInstanceOf(RelayGateway::class, $first, 'the first enabled module of the instance order goes');
        self::assertSame(['relay-token', ''], [$credentials->get(RelayGateway::TOKEN), $credentials->get(RelayGateway::SENDER)]);

        $this->configure([self::RELAY.Switches::ENABLED => '0', self::BEACON.Switches::ENABLED => '0']);
        self::assertNull($switches->first(Switchable::class));
        self::assertSame('relay-token', self::service(Settings::class)->get(self::RELAY.RelayGateway::TOKEN), 'a disabled module keeps its credentials');
    }
}
