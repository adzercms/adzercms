<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Tests;

use AdzerCMS\Admin\AssembledModules;
use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Setting\Catalog;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\Module\CatalogAnswers;
use AdzerCMS\Core\Tests\Notice\Fixture\Relay\RelayGateway;
use AdzerCMS\Core\Tests\Notice\Fixture\Relay\RelaySettings;
use AdzerCMS\Core\Tests\SchemaTestCase;
use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class ModuleWebTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use CatalogAnswers;

    private const SENDER = RelayGateway::SETTINGS.RelayGateway::SENDER;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->record('79990000001')->setRole(Role::Admin));
        $this->em->flush();
    }

    #[Group('ACMS-CORE-0023')]
    #[Group('ACMS-US-0004')]
    public function testModulesGoOneListingWithKindAndSchema(): void
    {
        $modules = self::service(AssembledModules::class)->modules();
        $listing = $this->client->request('GET', '/admin/modules');

        self::assertSame(
            [self::phrase('admin.modules.name'), self::phrase('admin.modules.kind'), self::phrase('admin.modules.schema')],
            $listing->filter('#rows thead th')->each(static fn (Crawler $head): string => trim($head->text())),
        );
        self::assertEqualsCanonicalizing(
            array_map(static fn (string $key): string => '/admin/modules/'.$key, array_keys($modules)),
            self::cards($listing),
            'the section lists every assembled module',
        );
        self::assertArrayHasKey('relay', $modules);
        self::assertSame(['/admin/modules/relay'], self::cards($this->fragment('/admin/modules?q=Relay')));

        $card = $this->client->request('GET', '/admin/modules/relay');
        self::assertSame(self::phrase($modules['relay']['kind']), self::tile($card, self::phrase('admin.modules.kind')));

        $this->client->request('GET', '/admin/modules/absent');
        self::assertResponseStatusCodeSame(404);
    }

    #[Group('ACMS-CORE-0053')]
    #[Group('ACMS-US-0004')]
    public function testModuleSettingsLiveOnItsCardOnly(): void
    {
        $card = $this->client->request('GET', '/admin/modules/relay');
        $this->client->submit($card->selectButton(self::phrase('admin.save'))->form([
            \sprintf('value[%s]', self::SENDER) => 'VILLAGE',
            \sprintf('value[%s]', RelaySettings::SIGNATURE) => 'Village',
        ]));

        self::assertResponseRedirects('/admin/modules/relay');
        self::assertSame('VILLAGE', self::service(Settings::class)->get(self::SENDER), 'credentials of the switch');
        self::assertSame('Village', self::service(Settings::class)->get(RelaySettings::SIGNATURE), 'own setting of the module');

        foreach (array_keys(self::service(Catalog::class)->groups()) as $tab) {
            $names = $this->client->request('GET', '/admin/settings?tab='.$tab)->filter('form [name]')->each(static fn (Crawler $node): string => (string) $node->attr('name'));
            foreach ([self::SENDER, RelaySettings::SIGNATURE] as $name) {
                self::assertNotContains(\sprintf('value[%s]', $name), $names, 'the settings tabs do not repeat the module card');
            }
        }
    }

    #[Group('ACMS-CORE-0934')]
    #[Group('ACMS-CORE-0147')]
    #[Group('ACMS-US-0004')]
    public function testCatalogListsOfferedAndInstalledModulesAsOfItsSnapshotByCategory(): void
    {
        self::assertSame(self::phrase('admin.catalog.untaken'), trim($this->client->request('GET', '/admin/modules')->filter('#catalog p.muted')->text()));

        $this->answerCatalog();
        $this->listed(['acme/till', 'acme/shop']);
        $this->offer('acme/till', '1.2.0', provide: ['adzercms/sms-implementation' => '1.0.0'], categories: ['messaging']);
        $this->offer('acme/shop', '1.0.0', categories: ['commerce']);
        self::mockTime('2026-10-20 12:30');
        self::command('adzercms:modules')->execute([]);
        $catalog = $this->client->request('GET', '/admin/modules')->filter('#catalog');

        self::assertSame(self::phrase('admin.catalog.taken', ['at' => new \DateTimeImmutable('2026-10-20 12:30')]), trim($catalog->filter('p.muted')->text()));
        self::assertSame(
            [self::phrase(CoreKind::Sms->label()), '1.2.0', 'repo.packagist.org'],
            array_map(static fn (string $column): string => trim(self::cell($catalog, 'acme/till', self::phrase($column))->text()), ['admin.modules.kind', 'admin.catalog.version', 'admin.catalog.repository']),
        );
        self::assertSame(InstalledVersions::getPrettyVersion('adzercms/admin'), trim(self::cell($catalog, 'adzercms/admin', self::phrase('admin.catalog.installed'))->text()));

        $chosen = $this->fragment('/admin/modules?catalog_category=messaging')->filter('#catalog tbody tr td:first-child strong')->each(static fn (Crawler $name): string => trim($name->text()));
        self::assertContains('acme/till', $chosen);
        self::assertNotContains('acme/shop', $chosen);
        self::assertNotContains('adzercms/admin', $chosen);
    }

    /** @return list<string> */
    private static function cards(Crawler $listing): array
    {
        return $listing->filter('#rows tbody tr')->each(static fn (Crawler $row): string => (string) $row->attr('data-href'));
    }
}
