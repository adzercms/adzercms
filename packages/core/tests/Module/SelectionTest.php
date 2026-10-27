<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module;

use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Core\Module\Installed;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Module\Selection;
use AdzerCMS\Core\Tests\ModuleSuite;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class SelectionTest extends TestCase
{
    #[Group('ACMS-CORE-0003')]
    #[Group('ACMS-CORE-0153')]
    public function testRequiredModuleGoesBeforeItsDependentAndListKeepsOrder(): void
    {
        $selection = self::select(['MODULES' => 'Shop', 'SMS_MODULES' => 'Pigeon, Drum', 'TEST_GATEWAY' => 'Card']);

        self::assertSame(['Shop', 'Card', 'Pigeon', 'Drum'], self::keys($selection));
    }

    #[Group('ACMS-CORE-0003')]
    public function testEmptyEnvironmentSelectsNothing(): void
    {
        $selection = self::select([]);

        self::assertSame([], $selection->modules);
        self::assertSame('', $selection->id());
    }

    #[Group('ACMS-CORE-0003')]
    public function testUnknownModuleStopsBuildWithAvailableOfItsKind(): void
    {
        $this->expectExceptionObject(new \LogicException('module "Coin" in TEST_GATEWAY is not installed, available: Card, Cash'));

        self::select(['MODULES' => 'Shop', 'TEST_GATEWAY' => 'Coin']);
    }

    #[Group('ACMS-CORE-0003')]
    public function testModuleOfAnotherKindIsUnknownToKind(): void
    {
        $this->expectExceptionObject(new \LogicException('module "Card" in SMS_MODULES is not installed, available: Pigeon, Drum'));

        self::select(['MODULES' => 'Shop', 'SMS_MODULES' => 'Card']);
    }

    #[Group('ACMS-CORE-0153')]
    public function testModuleWithoutSelectedRequiredStopsBuild(): void
    {
        $this->expectExceptionObject(new \LogicException('module "Card" requires module "Shop" selected in MODULES'));

        self::select(['TEST_GATEWAY' => 'Card']);
    }

    #[Group('ACMS-CORE-0153')]
    public function testRequiredKindPlacesItsSelectedModuleFirst(): void
    {
        $selection = self::select(['MODULES' => 'Shop, Till', 'TEST_GATEWAY' => 'Cash']);

        self::assertSame(['Shop', 'Cash', 'Till'], self::keys($selection));
    }

    #[Group('ACMS-CORE-0153')]
    public function testRequiredKindWithoutSelectedModuleStopsBuild(): void
    {
        $this->expectExceptionObject(new \LogicException('module "Till" requires a module selected in TEST_GATEWAY'));

        self::select(['MODULES' => 'Shop, Till']);
    }

    #[Group('ACMS-CORE-0003')]
    public function testSingleKindTakesOneModule(): void
    {
        $this->expectExceptionObject(new \LogicException('TEST_GATEWAY selects one module, selected: Card, Cash'));

        self::select(['MODULES' => 'Shop', 'TEST_GATEWAY' => 'Card,Cash']);
    }

    #[Group('ACMS-CORE-0151')]
    public function testKeyOfTwoPackagesStopsBuild(): void
    {
        $this->expectExceptionObject(new \LogicException('module key "Card" is taken by packages acme/card and other/card'));

        Registry::of(['acme/card' => Fixture\Manifest::of(Fixture\CardBundle::class), 'other/card' => Fixture\Manifest::of(Fixture\Twin\CardBundle::class)]);
    }

    #[Group('ACMS-CORE-0151')]
    public function testPackageWithoutModuleBundleStopsBuild(): void
    {
        $this->expectExceptionObject(new \LogicException('module package acme/bare does not name a bundle class extending AdzerCMS\Contracts\Module\Module in extra.adzercms.bundle'));

        Registry::of(['acme/bare' => ['extra' => ['adzercms' => ['bundle' => \stdClass::class]]]]);
    }

    #[Group('ACMS-CORE-0153')]
    public function testKindModuleWithoutImplementationStopsBuild(): void
    {
        $this->expectExceptionObject(new \LogicException('module package acme/drum does not provide adzercms/sms-implementation of its kind'));

        Registry::of(['acme/drum' => ['extra' => ['adzercms' => ['bundle' => Fixture\DrumBundle::class]]]]);
    }

    #[Group('ACMS-CORE-1005')]
    #[DataProvider('environments')]
    public function testDevelopmentOnlyModuleIsInstalledOnlyForSuites(string $environment, bool $installed): void
    {
        $server = $_SERVER;
        $_SERVER['APP_ENV'] = $environment;

        try {
            self::assertSame($installed, null !== Registry::installed()->package('adzercms/trespass'));
        } finally {
            $_SERVER = $server;
        }
    }

    /** @return iterable<string, array{string, bool}> */
    public static function environments(): iterable
    {
        yield 'suites' => ['test', true];
        yield 'instance' => ['prod', false];
    }

    #[Group('ACMS-CORE-0018')]
    public function testSelectionIdFollowsSelectedSet(): void
    {
        $card = ['MODULES' => 'Shop', 'TEST_GATEWAY' => 'Card'];

        self::assertSame(self::select($card)->id(), self::select($card)->id());
        self::assertNotSame(self::select($card)->id(), self::select(['MODULES' => 'Shop', 'TEST_GATEWAY' => 'Cash'])->id());
    }

    /**
     * @param array<string, string> $environment
     * @param array{string, string} $expected
     */
    #[Group('ACMS-CORE-0938')]
    #[DataProvider('toggles')]
    public function testToggleRewritesKindKeyOfModule(array $environment, string $key, bool $on, array $expected): void
    {
        self::assertSame($expected, self::toggle($environment, $key, $on));
    }

    /** @return iterable<string, array{array<string, string>, string, bool, array{string, string}}> */
    public static function toggles(): iterable
    {
        yield 'enabling appends to a list' => [['MODULES' => 'Shop', 'TEST_GATEWAY' => 'Card', 'SMS_MODULES' => 'Pigeon'], 'Drum', true, ['SMS_MODULES', 'Pigeon,Drum']];
        yield 'enabling replaces the one of its kind' => [['MODULES' => 'Shop', 'TEST_GATEWAY' => 'Card'], 'Cash', true, ['TEST_GATEWAY', 'Cash']];
        yield 'disabling removes' => [['MODULES' => 'Shop', 'TEST_GATEWAY' => 'Card', 'SMS_MODULES' => 'Pigeon, Drum'], 'Pigeon', false, ['SMS_MODULES', 'Drum']];
    }

    #[Group('ACMS-CORE-0938')]
    public function testToggleBreakingSelectionStopsLikeBuild(): void
    {
        $this->expectExceptionObject(new \LogicException('module "Pigeon" requires module "Card" selected in TEST_GATEWAY'));

        self::toggle(['MODULES' => 'Shop', 'TEST_GATEWAY' => 'Card', 'SMS_MODULES' => 'Pigeon'], 'Card', false);
    }

    #[Group('ACMS-CORE-0993')]
    #[DataProvider('enablings')]
    public function testSelectionScriptEnablesModuleOnPackagesOfItsDirectory(string $package, int $code, string $output): void
    {
        $empty = array_fill_keys(array_map(static fn (Kind $kind): string => $kind->environment(), Registry::installed()->kinds()), '');
        $script = new Process([\PHP_BINARY, \dirname(__DIR__, 2).'/bin/adzercms-selection', $package], ModuleSuite::root(), $empty);
        $script->run();

        self::assertSame([$code, $output], [$script->getExitCode(), $script->getOutput().$script->getErrorOutput()]);
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function enablings(): iterable
    {
        yield 'enabled' => ['adzercms/sms-pilot', 0, "SMS_MODULES=SmsPilot\n"];
        yield 'selection refused' => ['adzercms/tbank', 1, "module \"TBank\" requires module \"Payments\" selected in MODULES\n"];
    }

    /** @param array<string, string> $environment */
    private static function select(array $environment): Selection
    {
        return Selection::of(self::registry(), static fn (string $name): string => $environment[$name] ?? '');
    }

    /**
     * @param array<string, string> $environment
     *
     * @return array{string, string}
     */
    private static function toggle(array $environment, string $key, bool $on): array
    {
        $registry = self::registry();

        return Selection::toggled($registry, static fn (string $name): string => $environment[$name] ?? '', $registry->find($key) ?? self::fail('no fixture module '.$key), $on);
    }

    private static function registry(): Registry
    {
        return Registry::of([
            'acme/shop' => Fixture\Manifest::of(Fixture\ShopBundle::class),
            'acme/card' => Fixture\Manifest::of(Fixture\CardBundle::class, 'acme/shop'),
            'acme/cash' => Fixture\Manifest::of(Fixture\CashBundle::class, 'acme/shop'),
            'acme/pigeon' => Fixture\Manifest::of(Fixture\PigeonBundle::class, 'acme/card'),
            'acme/drum' => Fixture\Manifest::of(Fixture\DrumBundle::class),
            'acme/till' => Fixture\Manifest::of(Fixture\TillBundle::class, 'adzercms/gateway-implementation'),
        ]);
    }

    /** @return list<string> */
    private static function keys(Selection $selection): array
    {
        return array_map(static fn (Installed $module): string => $module->key, $selection->modules);
    }
}
