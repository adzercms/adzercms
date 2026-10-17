<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Module;
use AdzerCMS\Core\Module\Installed;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Module\Selection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

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

        Registry::of(['acme/card' => self::manifest(Fixture\CardBundle::class), 'other/card' => self::manifest(Fixture\Twin\CardBundle::class)]);
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

    #[Group('ACMS-CORE-0018')]
    public function testSelectionIdFollowsSelectedSet(): void
    {
        $card = ['MODULES' => 'Shop', 'TEST_GATEWAY' => 'Card'];

        self::assertSame(self::select($card)->id(), self::select($card)->id());
        self::assertNotSame(self::select($card)->id(), self::select(['MODULES' => 'Shop', 'TEST_GATEWAY' => 'Cash'])->id());
    }

    /** @param array<string, string> $environment */
    private static function select(array $environment): Selection
    {
        $registry = Registry::of([
            'acme/shop' => self::manifest(Fixture\ShopBundle::class),
            'acme/card' => self::manifest(Fixture\CardBundle::class, 'acme/shop'),
            'acme/cash' => self::manifest(Fixture\CashBundle::class, 'acme/shop'),
            'acme/pigeon' => self::manifest(Fixture\PigeonBundle::class, 'acme/card'),
            'acme/drum' => self::manifest(Fixture\DrumBundle::class),
            'acme/till' => self::manifest(Fixture\TillBundle::class, 'adzercms/gateway-implementation'),
        ]);

        return Selection::of($registry, static fn (string $name): string => $environment[$name] ?? '');
    }

    /**
     * @param class-string<Module> $bundle
     *
     * @return array<string, mixed>
     */
    private static function manifest(string $bundle, string ...$requires): array
    {
        return [
            'require' => array_fill_keys(['php', ...$requires], '*'),
            'provide' => CoreKind::Feature === $bundle::kind() ? [] : [Registry::implementation($bundle::kind()) => '*'],
            'extra' => ['adzercms' => ['bundle' => $bundle]],
        ];
    }

    /** @return list<string> */
    private static function keys(Selection $selection): array
    {
        return array_map(static fn (Installed $module): string => $module->key, $selection->modules);
    }
}
