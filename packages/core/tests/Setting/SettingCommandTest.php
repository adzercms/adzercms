<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Setting;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Notice\QuietHours;
use AdzerCMS\Core\Setting\Catalog;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\Notice\Fixture\Relay\RelayGateway;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Tests\Setting\Fixture\Shop;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

final class SettingCommandTest extends SchemaTestCase
{
    use Configuration;

    #[Group('ACMS-CORE-0056')]
    public function testCommandSavesFieldOfOwnerAndListsCatalog(): void
    {
        $save = self::command('adzercms:setting');
        self::assertSame(Command::SUCCESS, $save->execute(['name' => Shop::TITLE, 'value' => 'Bridge shop']));

        $read = self::command('adzercms:setting');
        $read->execute(['name' => Shop::TITLE]);
        self::assertSame('Bridge shop', trim($read->getDisplay()));

        $listing = self::command('adzercms:setting');
        $listing->execute([]);
        self::assertMatchesRegularExpression('~shop\.title\s+Shop title\s+Bridge shop~', $listing->getDisplay());
        self::assertMatchesRegularExpression('~shop\.phone\s+Shop phone\s+—~', $listing->getDisplay());
    }

    #[Group('ACMS-CORE-0056')]
    public function testListingTellsSwitchedOffFromUnset(): void
    {
        $this->configure([RelayGateway::SETTINGS.Switches::ENABLED => '0']);

        $listing = self::command('adzercms:setting');
        $listing->execute([]);
        self::assertMatchesRegularExpression('~sms\.relay\.enabled\s+Enabled\s+0\s*$~m', $listing->getDisplay(), 'a switched off toggle shows its value');
        self::assertMatchesRegularExpression('~sms\.relay\.sender\s+Relay sender\s+—\s*$~m', $listing->getDisplay());
    }

    #[Group('ACMS-CORE-0042')]
    public function testPlainSettingTakesStdinAsArgument(): void
    {
        $piped = self::command('adzercms:setting');
        $piped->setInputs(['River shop']);

        self::assertSame(Command::SUCCESS, $piped->execute(['name' => Shop::TITLE, '--stdin' => true]));
        self::assertSame('River shop', self::service(Settings::class)->get(Shop::TITLE));
    }

    #[Group('ACMS-CORE-0042')]
    #[Group('ACMS-CORE-0058')]
    public function testSecretComesOnlyThroughStdinAndIsNotShown(): void
    {
        $argued = self::command('adzercms:setting');
        self::assertSame(Command::INVALID, $argued->execute(['name' => Shop::TOKEN, 'value' => 'in-argument']));
        self::assertSame('', self::service(Settings::class)->get(Shop::TOKEN), 'secret by argument is not saved');

        $piped = self::command('adzercms:setting');
        $piped->setInputs(['till-token']);
        self::assertSame(Command::SUCCESS, $piped->execute(['name' => Shop::TOKEN, '--stdin' => true]));
        self::assertSame('till-token', self::service(Settings::class)->get(Shop::TOKEN));

        $listing = self::command('adzercms:setting');
        $listing->execute([]);
        self::assertMatchesRegularExpression('~shop\.token\s+Till token\s+set~', $listing->getDisplay());
        self::assertStringNotContainsString('till-token', $listing->getDisplay().$piped->getDisplay());
    }

    #[Group('ACMS-CORE-0055')]
    public function testTimeIsRefusedOutsideDayAndStoredPadded(): void
    {
        $wrong = self::command('adzercms:setting');
        self::assertSame(Command::INVALID, $wrong->execute(['name' => QuietHours::FROM, 'value' => '24:00']));
        self::assertStringContainsString(self::phrase('core.setting.time', ['field' => self::phrase('core.setting.notice_quiet_from')]), $wrong->getDisplay());
        self::assertSame('', self::service(Settings::class)->get(QuietHours::FROM), 'a refused time is not saved');

        self::assertSame(Command::SUCCESS, self::command('adzercms:setting')->execute(['name' => QuietHours::FROM, 'value' => '7:05']));
        self::assertSame('07:05', self::service(Settings::class)->get(QuietHours::FROM));
    }

    #[Group('ACMS-CORE-0057')]
    public function testValueRefusedByRuleOfOwnerIsNotSaved(): void
    {
        $wrong = self::command('adzercms:setting');
        self::assertSame(Command::INVALID, $wrong->execute(['name' => Shop::PHONE, 'value' => 'call us']));
        self::assertStringContainsString(Shop::DIGITS, $wrong->getDisplay(), 'the reason is named');
        self::assertSame('', self::service(Settings::class)->get(Shop::PHONE), 'the refused value does not reach the base');

        self::assertSame(Command::SUCCESS, self::command('adzercms:setting')->execute(['name' => Shop::PHONE, 'value' => '79990001122']));
        self::assertSame('79990001122', self::service(Settings::class)->get(Shop::PHONE));
    }

    #[Group('ACMS-CORE-0055')]
    #[Group('ACMS-UI-0031')]
    public function testToggleTakesOnlyOnOrOff(): void
    {
        $enabled = RelayGateway::SETTINGS.Switches::ENABLED;
        $wrong = self::command('adzercms:setting');
        self::assertSame(Command::INVALID, $wrong->execute(['name' => $enabled, 'value' => 'yes']));
        self::assertStringContainsString('Enabled takes 1 to switch on or 0 to switch off', $wrong->getDisplay());

        self::assertSame(Command::SUCCESS, self::command('adzercms:setting')->execute(['name' => $enabled, 'value' => '1']));
        self::assertTrue(self::service(Settings::class)->on($enabled));
    }

    #[Group('ACMS-CORE-0056')]
    public function testUnknownKeyIsRefusedWithAvailable(): void
    {
        $command = self::command('adzercms:setting');

        self::assertSame(Command::INVALID, $command->execute(['name' => 'shop.nope', 'value' => '1']));
        self::assertStringContainsString(Shop::PHONE, $command->getDisplay());
    }

    #[Group('ACMS-CORE-0053')]
    public function testKeyOfTwoOwnersStopsCatalog(): void
    {
        $twin = new class implements Owner {
            public function settingFields(): array
            {
                return [new Field(Shop::TITLE, t('Title'))];
            }
        };

        $this->expectExceptionObject(new \LogicException('setting key shop.title is declared by two owners'));

        (new Catalog([new Shop(), $twin], [], self::service(TranslatorInterface::class, 'translator')))->fields();
    }
}
