<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Tests;

use AdzerCMS\Admin\AssembledModules;
use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Module\Catalog as ModuleCatalog;
use AdzerCMS\Core\Module\CatalogRow;
use AdzerCMS\Core\Module\ModuleAction;
use AdzerCMS\Core\Module\RequestState;
use AdzerCMS\Core\Module\Sources;
use AdzerCMS\Core\Module\Tree;
use AdzerCMS\Core\Repository\ModuleRequestRepository;
use AdzerCMS\Core\Repository\ModuleSourceRepository;
use AdzerCMS\Core\Setting\Catalog;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\Module\CatalogAnswers;
use AdzerCMS\Core\Tests\Notice\Fixture\Relay\RelayGateway;
use AdzerCMS\Core\Tests\Notice\Fixture\Relay\RelaySettings;
use AdzerCMS\Core\Tests\SchemaTestCase;
use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;

final class ModuleWebTest extends SchemaTestCase
{
    use Accounts;
    use Browser;
    use CatalogAnswers;

    private const SENDER = RelayGateway::SETTINGS.RelayGateway::SENDER;
    private const REPOSITORY = 'https://repo.acme.test';

    private ?string $tree = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->record('79990000001')->setRole(Role::Admin));
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        if (null !== $this->tree) {
            (new Filesystem())->remove($this->tree);
        }
        parent::tearDown();
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
        $this->client->submit($card->selectButton(self::phrase('core.action.save'))->form([
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

    #[Group('ACMS-CORE-0145')]
    #[Group('ACMS-US-0004')]
    public function testSiteUnderGitTakesNoRequests(): void
    {
        $this->answerCatalog();
        $this->listed(['acme/shop']);
        $this->offer('acme/shop', '1.0.0');
        self::command('adzercms:modules')->execute([]);

        self::assertSame([], self::actions($this->client->request('GET', '/admin/modules'), 'acme/shop'));
        $this->post('/admin/modules/request/install?package=acme/shop');
        self::assertSame(self::phrase('core.module.refusal.versioned'), self::toast($this->arrive('/admin/modules'), 'error'));
    }

    #[Group('ACMS-CORE-0935')]
    #[Group('ACMS-CORE-0936')]
    #[Group('ACMS-CORE-0937')]
    #[Group('ACMS-US-0004')]
    public function testCatalogRowFilesRequestOfItsActionsOnePerPackage(): void
    {
        $this->tree();
        $this->answerCatalog();
        $this->listed(['acme/shop']);
        $this->offer('acme/shop', '1.0.0');
        self::command('adzercms:modules')->execute([]);

        $rows = self::service(ModuleCatalog::class)->rows()['rows'];
        $idle = array_values(array_filter($rows, static fn (CatalogRow $row): bool => null !== $row->installed && !$row->selected))[0] ?? self::fail('the tree has no installed module outside the suite');
        $chosen = array_values(array_filter($rows, static fn (CatalogRow $row): bool => $row->selected && CoreKind::Admin !== $row->kind))[0] ?? self::fail('the suite selects no module beside the admin panel');
        $page = $this->client->request('GET', '/admin/modules');
        self::assertSame([self::phrase(ModuleAction::Install->label())], self::actions($page, 'acme/shop'));
        self::assertSame([self::phrase(ModuleAction::Update->label())], self::actions($page, 'adzercms/admin'));
        self::assertSame(array_map(static fn (ModuleAction $action): string => self::phrase($action->label()), [ModuleAction::Update, ModuleAction::Disable]), self::actions($page, $chosen->name));
        self::assertSame(array_map(static fn (ModuleAction $action): string => self::phrase($action->label()), [ModuleAction::Update, ModuleAction::Enable, ModuleAction::Remove]), self::actions($page, $idle->name));

        $this->post('/admin/modules/request/install?package=acme/shop');
        $this->post('/admin/modules/request/remove?package=acme/shop');
        $page = $this->arrive('/admin/modules');
        self::assertSame(self::phrase('core.module.refusal.action'), self::toast($page, 'error'));
        $this->post('/admin/modules/request/enable?package='.$idle->name);
        $this->post('/admin/modules/request/update?package='.$idle->name);
        $page = $this->arrive('/admin/modules');
        self::assertSame(self::phrase('core.module.refusal.open', ['package' => $idle->name]), self::toast($page, 'error'));
        self::assertSame(
            [[$idle->name, self::phrase(ModuleAction::Enable->label()), self::phrase(RequestState::Pending->label())], ['acme/shop', self::phrase(ModuleAction::Install->label()), self::phrase(RequestState::Pending->label())]],
            $page->filter('#requests tbody tr')->each(static fn (Crawler $row): array => \array_slice($row->filter('td')->each(static fn (Crawler $cell): string => trim($cell->text())), 0, 3)),
        );

        $running = self::service(ModuleRequestRepository::class)->inState(RequestState::Pending)[0] ?? self::fail('the request is not filed');
        $running->start();
        $this->em->flush();
        self::command('adzercms:modules')->execute([]);
        $this->em->clear();
        $interrupted = self::service(ModuleRequestRepository::class)->find($running->getId()) ?? self::fail('the request is gone');
        self::assertSame([RequestState::Failed, self::phrase('core.module.refusal.interrupted')], [$interrupted->getState(), $interrupted->getOutput()]);
    }

    #[Group('ACMS-CORE-0938')]
    #[Group('ACMS-CORE-0944')]
    #[Group('ACMS-US-0004')]
    #[DataProvider('refreshes')]
    public function testEnablingRefreshesSiteOnNewSelectionBeforeWritingIt(?string $refusal, RequestState $state, string $environment): void
    {
        $tree = $this->tree();
        file_put_contents($tree.'/.env.local', "APP_SECRET=kept\nSMS_MODULES=Stale\n");
        if (null !== $refusal) {
            file_put_contents($tree.'/refusal', $refusal);
        }
        $sources = self::service(Sources::class);
        $sources->add(self::REPOSITORY);
        $sources->grant(self::service(ModuleSourceRepository::class)->findOneBy(['url' => self::REPOSITORY]) ?? self::fail('the source is not kept'), '', 'secret');

        $this->post('/admin/modules/request/enable?package=adzercms/sms-pilot');
        self::command('adzercms:modules')->execute([]);
        $this->em->clear();
        $request = self::service(ModuleRequestRepository::class)->findOneBy(['package' => 'adzercms/sms-pilot']) ?? self::fail('the request is not filed');
        $refreshed = file($tree.'/refreshed.env', \FILE_IGNORE_NEW_LINES) ?: [];

        self::assertContains('SMS_MODULES=SmsPilot', $refreshed, 'the refresh builds the new selection');
        self::assertContains('PHP_BINARY='.\PHP_BINARY, $refreshed);
        self::assertContains('COMPOSER_AUTH={"bearer":{"repo.acme.test":"secret"}}', $refreshed, 'Composer of the refresh takes credentials of the own sources');
        self::assertSame([], preg_grep('/^(APP_SECRET|SYMFONY_DOTENV_VARS)=/', $refreshed), 'the refresh reads the environment files itself');
        self::assertSame([$state, $refusal], [$request->getState(), $request->getOutput()]);
        self::assertSame($environment, file_get_contents($tree.'/.env.local'));
    }

    /** @return iterable<string, array{?string, RequestState, string}> */
    public static function refreshes(): iterable
    {
        yield 'refreshed' => [null, RequestState::Done, "APP_SECRET=kept\nSMS_MODULES=SmsPilot\n"];
        yield 'refresh failed' => ['migration failed', RequestState::Failed, "APP_SECRET=kept\nSMS_MODULES=Stale\n"];
    }

    /** @param list<string> $locked */
    #[Group('ACMS-CORE-0940')]
    #[Group('ACMS-CORE-0946')]
    #[Group('ACMS-US-0004')]
    #[DataProvider('compositions')]
    public function testComposerRequestRunsInStagedCopyCommittedOnlyOnSuccess(ModuleAction $action, string $package, RequestState $state, array $locked, bool $zipped = false): void
    {
        $tree = $this->tree(__DIR__.'/Fixture/site');
        $this->answerCatalog();
        $this->listed([$package]);
        $this->offer($package, '1.0.0');
        if ($zipped) {
            $this->post('/admin/modules/sources/zip', [], ['archive' => $this->archive(['composer.json' => json_encode(self::manifest($package, '1.0.0'), \JSON_THROW_ON_ERROR)])]);
        }
        self::command('adzercms:modules')->execute([]);

        $this->post(\sprintf('/admin/modules/request/%s?package=%s', $action->value, $package));
        self::command('adzercms:modules')->execute([]);
        $this->em->clear();
        $request = self::service(ModuleRequestRepository::class)->findOneBy(['package' => $package]) ?? self::fail('the request is not filed');
        $lock = json_decode((string) file_get_contents($tree.'/composer.lock'), true, flags: \JSON_THROW_ON_ERROR);
        $manifest = json_decode((string) file_get_contents($tree.'/composer.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame($state, $request->getState(), (string) $request->getOutput());
        self::assertEqualsCanonicalizing($locked, array_column($lock['packages'], 'name'));
        self::assertEqualsCanonicalizing($locked, array_keys($manifest['require']));
        self::assertSame($zipped, \in_array('adzercms-zip', array_column($manifest['repositories'], 'name'), true), 'the repositories carry the ZIP directory while it holds archives');
        self::assertDirectoryDoesNotExist($tree.'/var/stage');
        self::assertSame(RequestState::Done === $state, is_file($tree.'/refreshed.env'), 'the refresh follows a committed change only');
    }

    /** @return iterable<string, array{ModuleAction, string, RequestState, list<string>}> */
    public static function compositions(): iterable
    {
        yield 'install' => [ModuleAction::Install, 'acme/shop', RequestState::Done, ['acme/shop', 'adzercms/sms-pilot', 'adzercms/theme']];
        yield 'install refused by Composer' => [ModuleAction::Install, 'acme/absent', RequestState::Failed, ['adzercms/sms-pilot', 'adzercms/theme']];
        yield 'update of an engine package' => [ModuleAction::Update, 'adzercms/sms-pilot', RequestState::Done, ['adzercms/sms-pilot', 'adzercms/theme']];
        yield 'remove' => [ModuleAction::Remove, 'adzercms/sms-pilot', RequestState::Done, ['adzercms/theme']];
        yield 'install of an uploaded ZIP' => [ModuleAction::Install, 'acme/zipped', RequestState::Done, ['acme/zipped', 'adzercms/sms-pilot', 'adzercms/theme'], true];
    }

    #[Group('ACMS-CORE-0143')]
    #[Group('ACMS-CORE-0944')]
    #[Group('ACMS-US-0004')]
    public function testOwnSourcesShareCredentialsOfTheirHostAndShowUnavailability(): void
    {
        self::assertSame(self::phrase('admin.sources.empty'), trim($this->client->request('GET', '/admin/modules')->filter('#sources .empty-state')->text()));

        $this->connect(self::REPOSITORY.'/one');
        $this->connect(self::REPOSITORY.'/two');
        $this->connect(self::REPOSITORY.'/one');
        self::assertSame(self::phrase('core.module.source.taken', ['url' => self::REPOSITORY.'/one']), self::toast($this->arrive('/admin/modules'), 'error'));
        $one = self::service(ModuleSourceRepository::class)->findOneBy(['url' => self::REPOSITORY.'/one']) ?? self::fail('the source is not kept');
        $dialog = $this->client->request('GET', '/admin/modules/sources/'.$one->getId().'/credentials');
        $this->client->submit($dialog->selectButton(self::phrase('core.action.save'))->form(['token' => 'secret']));
        $this->connect(self::REPOSITORY.'/three');

        $this->answerCatalog();
        $this->listed([]);
        $this->unavailable = [self::REPOSITORY];
        self::command('adzercms:modules')->execute([]);
        $sources = $this->client->request('GET', '/admin/modules')->filter('#sources');
        foreach (['one', 'two', 'three'] as $path) {
            self::assertSame(self::phrase('admin.setting.set'), trim(self::cell($sources, self::REPOSITORY.'/'.$path, self::phrase('admin.sources.access'))->text()), 'a source takes credentials of its host');
            self::assertSame(self::phrase('admin.sources.down'), trim(self::cell($sources, self::REPOSITORY.'/'.$path, self::phrase('admin.sources.source'))->filter('.badge')->text()));
        }

        $this->post('/admin/modules/sources/'.$one->getId().'/drop');
        self::assertSame(
            [self::REPOSITORY.'/two', self::REPOSITORY.'/three'],
            $this->arrive('/admin/modules')->filter('#sources tbody tr td strong')->each(static fn (Crawler $source): string => trim($source->text())),
        );
    }

    private function connect(string $url): void
    {
        $dialog = $this->client->request('GET', '/admin/modules/sources/new');
        $this->client->submit($dialog->selectButton(self::phrase('admin.sources.add'))->form(['url' => $url]));
    }

    private function tree(?string $site = null): string
    {
        $this->tree = sys_get_temp_dir().'/adzercms-tree-'.bin2hex(random_bytes(4));
        null === $site ? (new Filesystem())->mkdir($this->tree) : (new Filesystem())->mirror($site, $this->tree);
        self::getContainer()->set(Tree::class, new Tree($this->tree, __DIR__.'/Fixture/refresh'));

        return $this->tree;
    }

    /** @return list<string> */
    private static function actions(Crawler $page, string $name): array
    {
        $row = $page->filter('#catalog tbody tr')->reduce(static fn (Crawler $row): bool => $name === trim($row->filter('td strong')->text()));

        return $row->filter('td.row-actions button')->each(static fn (Crawler $button): string => trim($button->text()));
    }

    /** @return list<string> */
    private static function cards(Crawler $listing): array
    {
        return $listing->filter('#rows tbody tr')->each(static fn (Crawler $row): string => (string) $row->attr('data-href'));
    }
}
