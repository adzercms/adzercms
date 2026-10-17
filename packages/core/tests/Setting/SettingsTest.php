<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Setting;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Contracts\Service\ResetInterface;

use function Symfony\Component\Translation\t;

final class SettingsTest extends SchemaTestCase
{
    private const TITLE = 'fixture.title';
    private const TOKEN = 'fixture.token';
    private const PAUSE = 'fixture.pause';

    #[Group('ACMS-CORE-0050')]
    #[Group('ACMS-US-0004')]
    public function testSavedValueActsOnNextRequest(): void
    {
        $this->save(new Field(self::TITLE, t('Title')), 'Village network');
        self::assertSame('Village network', $this->settings()->get(self::TITLE));

        $this->em->getConnection()->update('setting', ['value' => 'Town network'], ['name' => self::TITLE]);
        self::service(ResetInterface::class, 'services_resetter')->reset();

        self::assertSame('Town network', $this->settings()->get(self::TITLE));
    }

    #[Group('ACMS-CORE-0058')]
    #[Group('ACMS-US-0004')]
    public function testSecretIsStoredEncrypted(): void
    {
        $this->save(new Field(self::TOKEN, t('Token'), Type::Secret), 'bank-token');
        self::service(ResetInterface::class, 'services_resetter')->reset();

        $stored = $this->em->getConnection()->fetchOne('SELECT value FROM setting WHERE name = ?', [self::TOKEN]);
        self::assertIsString($stored);
        self::assertStringNotContainsString('bank-token', $stored);
        self::assertSame('bank-token', $this->settings()->get(self::TOKEN));
    }

    #[Group('ACMS-CORE-0161')]
    #[Group('ACMS-US-0004')]
    public function testEmptySecretKeepsStoredOne(): void
    {
        $token = new Field(self::TOKEN, t('Token'), Type::Secret);
        $this->save($token, 'bank-token');
        $this->save($token, ' ');

        self::assertSame('bank-token', $this->settings()->get(self::TOKEN));
    }

    #[Group('ACMS-CORE-0050')]
    #[Group('ACMS-US-0004')]
    public function testMissingValueFallsBackToDefault(): void
    {
        self::assertSame(7, $this->settings()->int(self::PAUSE, 7));

        $this->save(new Field(self::PAUSE, t('Pause')), '0');
        self::assertSame(0, $this->settings()->int(self::PAUSE, 7), 'a stored zero is a value');
    }

    #[Group('ACMS-CORE-0161')]
    public function testEmptyValueRemovesSetting(): void
    {
        $title = new Field(self::TITLE, t('Title'));
        $this->save($title, 'Village network');
        $this->save($title, ' ');

        self::assertSame('', $this->settings()->get(self::TITLE));
        self::assertFalse($this->em->getConnection()->fetchOne('SELECT 1 FROM setting WHERE name = ?', [self::TITLE]));
    }

    private function save(Field $field, string $value): void
    {
        $this->settings()->save($field, $value);
        $this->em->flush();
    }

    private function settings(): Settings
    {
        return self::service(Settings::class);
    }
}
