<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Tests;

use AdzerCMS\Admin\Controller\TextsController;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Setting\Language;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Text\Texts;

final class TextsWebTest extends SchemaTestCase
{
    use Accounts;
    use Browser;

    private const DOMAIN = 'messages';
    private const MESSAGE = 'admin.menu.users';
    private const TEXT = '/admin/texts/'.self::DOMAIN.'/'.self::MESSAGE;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->record('79990000001')->setRole(Role::Admin));
        $this->em->flush();
    }

    public function testAdminChangesTextOfSiteAndRestoresCatalogOne(): void
    {
        $catalog = self::phrase(self::MESSAGE);

        try {
            self::assertSame([self::TEXT], $this->client->request('GET', '/admin/texts?q='.self::MESSAGE)->filter('tr.clickable-row')->extract(['data-record']));

            $this->post(self::TEXT, ['value' => 'Команда']);
            self::assertResponseHeaderSame('HX-Trigger', TextsController::CHANGED);
            self::assertSame('Команда', $this->label());

            $broken = $this->post(self::TEXT, ['value' => '{Команда']);
            self::assertSame(self::phrase('core.text.broken'), trim($broken->filter('.flash-error')->text()));
            self::assertSame('Команда', $this->label());

            $this->post(self::TEXT, ['reset' => '1']);
            self::assertSame($catalog, $this->label());
        } finally {
            self::service(Texts::class)->save(self::service(Language::class)->instance(), self::DOMAIN, self::MESSAGE, '');
        }
    }

    private function label(): string
    {
        return trim($this->client->request('GET', '/admin/users')->filter('a.nav-item[href="/admin/users"]')->text());
    }
}
