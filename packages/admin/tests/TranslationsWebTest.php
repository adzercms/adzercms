<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Tests;

use AdzerCMS\Admin\Controller\TranslationsController;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Setting\Language;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Text\Texts;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class TranslationsWebTest extends SchemaTestCase
{
    use Accounts;
    use Browser;

    private const DOMAIN = 'messages';
    private const MESSAGE = 'admin.menu.users';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->record('79990000001')->setRole(Role::Admin));
        $this->em->flush();
    }

    #[Group('ACMS-CORE-0995')]
    #[Group('ACMS-US-0004')]
    public function testAdminTranslatesTextOfEachLanguageAndRestoresCatalogOne(): void
    {
        $language = self::service(Language::class);
        $instance = $language->instance();
        $other = (string) array_key_first(array_diff_key($language->choices(), [$instance => true]));
        $catalog = self::phrase(self::MESSAGE);

        try {
            self::assertSame([[self::text($instance), $catalog]], $this->rows(''), 'the site language comes first');

            $this->post(self::text($other), ['value' => 'Crew']);
            self::assertSame([[self::text($other), 'Crew']], $this->rows($other));
            self::assertSame($catalog, $this->label(), 'another language keeps the text of the site language');

            $this->post(self::text($instance), ['value' => 'Команда']);
            self::assertResponseHeaderSame('HX-Trigger', TranslationsController::CHANGED);
            self::assertSame('Команда', $this->label());

            $broken = $this->post(self::text($instance), ['value' => '{Команда']);
            self::assertSame(self::phrase('core.text.broken'), trim($broken->filter('.flash-error')->text()));
            self::assertSame('Команда', $this->label());

            $this->post(self::text($instance), ['reset' => '1']);
            self::assertSame($catalog, $this->label());
        } finally {
            foreach ([$instance, $other] as $locale) {
                self::service(Texts::class)->save($locale, self::DOMAIN, self::MESSAGE, '');
            }
        }
    }

    private static function text(string $locale): string
    {
        return '/admin/translations/'.$locale.'/'.self::DOMAIN.'/'.self::MESSAGE;
    }

    /** @return list<array{string, string}> */
    private function rows(string $language): array
    {
        return $this->client->request('GET', '/admin/translations?'.http_build_query(['q' => self::MESSAGE, 'language' => $language]))
            ->filter('tr.clickable-row')
            ->each(static fn (Crawler $row): array => [(string) $row->attr('data-record'), trim($row->filter('td')->first()->text())]);
    }

    private function label(): string
    {
        return trim($this->client->request('GET', '/admin/users')->filter('a.nav-item[href="/admin/users"]')->text());
    }
}
