<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Tests;

use AdzerCMS\Admin\AssembledModules;
use AdzerCMS\Admin\ModuleScope;
use AdzerCMS\Admin\ModuleState;
use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Module\Catalog as ModuleCatalog;
use AdzerCMS\Core\Module\CatalogRow;
use AdzerCMS\Core\Module\ModuleAction;
use AdzerCMS\Core\Module\OperationState;
use AdzerCMS\Core\Module\Sources;
use AdzerCMS\Core\Module\Tree;
use AdzerCMS\Core\Repository\ModuleOperationRepository;
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
    private const MODULES = '/admin/modules';
    private const SOURCES = '/admin/modules?tab=sources';
    private const OPERATIONS = '/admin/modules?tab=operations';

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
    #[Group('ACMS-CORE-0981')]
    #[Group('ACMS-US-0004')]
    public function testSectionBuildsOnlyItsActiveTabOpeningOnOneModuleList(): void
    {
        $modules = self::service(AssembledModules::class)->modules();
        $listing = $this->client->request('GET', self::MODULES);

        self::assertSame(
            array_map(self::phrase(...), ['admin.menu.modules', 'admin.sources.title', 'admin.operations.title']),
            $listing->filter('nav.tabs a.tab')->each(static fn (Crawler $tab): string => trim($tab->text())),
        );
        foreach (['' => 'rows', '?tab=sources' => 'sources', '?tab=operations' => 'operations', '?tab=absent' => 'rows'] as $query => $table) {
            self::assertSame(
                [$table],
                $this->client->request('GET', self::MODULES.$query)->filter('#rows, #sources, #operations')->each(static fn (Crawler $built): string => (string) $built->attr('id')),
                'only the active tab is built',
            );
        }
        self::assertSame(
            [...array_map(self::phrase(...), ['admin.modules.name', 'admin.modules.kind', 'admin.modules.version', 'admin.modules.repository', 'admin.modules.status']), ''],
            $listing->filter('#rows thead th')->each(static fn (Crawler $head): string => trim($head->text())),
        );
        $assembled = [];
        foreach (self::service(ModuleCatalog::class)->rows()['rows'] as $row) {
            $key = strtolower((string) $row->key);
            if ($row->selected && isset($modules[$key])) {
                $assembled[$row->name] = $key;
            }
        }
        self::assertArrayHasKey('adzercms/admin', $assembled);
        self::assertEqualsCanonicalizing(
            array_map(static fn (string $key): string => '/admin/modules/'.$key, array_values($assembled)),
            self::cards($listing),
            'a row of an assembled module opens its card',
        );
        self::assertEqualsCanonicalizing(
            array_map(static fn (string $key): string => '/admin/modules/'.$key.'#settings', array_values(array_filter($assembled, static fn (string $key): bool => [] !== $modules[$key]['fields']))),
            $listing->filter('#rows td.row-actions a')->each(static fn (Crawler $link): string => (string) $link->attr('href')),
            'a module with settings leads to them',
        );
        self::assertStringContainsString(self::phrase($modules['admin']['description']), self::cell($listing, 'adzercms/admin', self::phrase('admin.modules.name'))->text(),
            'an assembled module tells what it does in the language of the reader');
        self::assertSame(self::phrase(ModuleState::Enabled->label()), trim(self::cell($listing, 'adzercms/admin', self::phrase('admin.modules.status'))->text()));
        self::assertSame(['/admin/modules/admin'], self::cards($this->fragment(self::MODULES.'?q='.urlencode(self::phrase($modules['admin']['description'])))),
            'the search goes by what the module does too');

        $card = $this->client->request('GET', '/admin/modules/relay');
        self::assertSame(
            [self::phrase($modules['relay']['description']), self::phrase($modules['relay']['kind'])],
            [self::tile($card, self::phrase('admin.modules.description')), self::tile($card, self::phrase('admin.modules.kind'))],
        );
        self::assertCount(1, $card->filter('form#settings'));

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
    #[Group('ACMS-CORE-0981')]
    #[Group('ACMS-US-0004')]
    public function testModulesListOfferedAndInstalledPackagesAsOfCatalogSnapshotByScopeAndCategory(): void
    {
        self::assertSame(self::phrase('admin.modules.untaken'), trim($this->client->request('GET', self::MODULES)->filter('#rows p.muted')->text()));

        $this->answerCatalog();
        $this->listed(['acme/till', 'acme/shop', 'adzercms/admin']);
        $this->offer('acme/till', '1.2.0', provide: ['adzercms/sms-implementation' => '1.0.0'], categories: ['messaging']);
        $this->offer('acme/shop', '1.0.0', categories: ['commerce']);
        $this->branch('adzercms/admin', '1.0.x-dev');
        self::mockTime('2026-10-20 12:30');
        self::command('adzercms:modules')->execute([]);
        $idle = array_values(array_filter(self::service(ModuleCatalog::class)->rows()['rows'], static fn (CatalogRow $row): bool => null !== $row->installed && !$row->selected))[0] ?? self::fail('the tree has no installed module outside the suite');
        $listing = $this->client->request('GET', self::MODULES)->filter('#rows');
        $cells = static fn (string $name, string ...$columns): array => array_map(static fn (string $column): string => trim(self::cell($listing, $name, self::phrase($column))->text()), $columns);

        self::assertSame(self::phrase('admin.modules.taken', ['at' => new \DateTimeImmutable('2026-10-20 12:30')]), trim($listing->filter('p.muted')->text()));
        self::assertSame(
            [self::phrase(CoreKind::Sms->label()), '1.2.0', 'repo.packagist.org', self::phrase(ModuleState::Available->label())],
            $cells('acme/till', 'admin.modules.kind', 'admin.modules.version', 'admin.modules.repository', 'admin.modules.status'),
        );
        self::assertSame(
            [InstalledVersions::getPrettyVersion('adzercms/admin').' '.self::phrase('admin.modules.update', ['version' => '1.0.x-dev']), self::phrase(ModuleState::Enabled->label())],
            $cells('adzercms/admin', 'admin.modules.version', 'admin.modules.status'),
            'an installed module names its version and the newer one of the catalog',
        );
        self::assertSame([self::phrase(ModuleState::Disabled->label())], $cells($idle->name, 'admin.modules.status'));

        $names = fn (string $query): array => $this->fragment(self::MODULES.'?'.$query)->filter('#rows tbody tr td:first-child strong')->each(static fn (Crawler $name): string => trim($name->text()));
        $category = $names('category=messaging');
        self::assertContains('acme/till', $category);
        self::assertNotContains('acme/shop', $category);
        self::assertNotContains('adzercms/admin', $category);
        self::assertEqualsCanonicalizing(['acme/till', 'acme/shop'], $names('scope='.ModuleScope::Available->value));
        self::assertSame(['adzercms/admin'], $names('scope='.ModuleScope::Updatable->value));
        $installed = $names('scope='.ModuleScope::Installed->value);
        self::assertContains('adzercms/admin', $installed);
        self::assertContains($idle->name, $installed);
        self::assertNotContains('acme/till', $installed);
    }

    #[Group('ACMS-CORE-0960')]
    #[Group('ACMS-CORE-0961')]
    #[Group('ACMS-US-0004')]
    public function testVersionWithoutMarkGoesAfterConfirmation(): void
    {
        $this->tree();
        $this->answerCatalog();
        $this->listed(['acme/till', 'acme/shop']);
        $this->offer('acme/till', '1.2.0');
        $this->offer('acme/shop', '1.0.0');
        $this->mark('acme/till', self::commit('acme/till', '1.2.0'));
        self::command('adzercms:modules')->execute([]);
        $page = $this->client->request('GET', self::MODULES);
        $version = static fn (string $name): string => trim(self::cell($page->filter('#rows'), $name, self::phrase('admin.modules.version'))->text());

        self::assertSame(['1.2.0 '.self::phrase('admin.modules.verified'), '1.0.0'], [$version('acme/till'), $version('acme/shop')]);
        self::assertSame([null], self::confirmations($page, 'acme/till'));
        self::assertSame([self::phrase('admin.modules.unverified', ['package' => 'acme/shop'])], self::confirmations($page, 'acme/shop'));
    }

    #[Group('ACMS-CORE-0145')]
    #[Group('ACMS-US-0004')]
    public function testSiteUnderGitTakesNoOperations(): void
    {
        $this->answerCatalog();
        $this->listed(['acme/shop']);
        $this->offer('acme/shop', '1.0.0');
        self::command('adzercms:modules')->execute([]);

        self::assertSame([], self::actions($this->client->request('GET', self::MODULES), 'acme/shop'));
        $this->post('/admin/modules/operation/install?package=acme/shop');
        self::assertSame(self::phrase('core.module.refusal.versioned'), self::toast($this->arrive(self::MODULES), 'error'));
    }

    #[Group('ACMS-CORE-0935')]
    #[Group('ACMS-CORE-0936')]
    #[Group('ACMS-CORE-0937')]
    #[Group('ACMS-US-0004')]
    public function testCatalogRowFilesOperationOfItsActionsOnePerPackage(): void
    {
        $this->tree();
        $this->answerCatalog();
        $this->listed(['acme/shop', 'adzercms/admin']);
        $this->offer('acme/shop', '1.0.0');
        $this->branch('adzercms/admin', '1.0.x-dev');
        self::command('adzercms:modules')->execute([]);

        $rows = self::service(ModuleCatalog::class)->rows()['rows'];
        $idle = array_values(array_filter($rows, static fn (CatalogRow $row): bool => null !== $row->installed && !$row->selected))[0] ?? self::fail('the tree has no installed module outside the suite');
        $chosen = array_values(array_filter($rows, static fn (CatalogRow $row): bool => $row->selected && CoreKind::Admin !== $row->kind))[0] ?? self::fail('the suite selects no module beside the admin panel');
        $labels = static fn (ModuleAction ...$actions): array => array_map(static fn (ModuleAction $action): string => self::phrase($action->label()), $actions);
        $page = $this->client->request('GET', self::MODULES);
        self::assertSame($labels(ModuleAction::Install), self::actions($page, 'acme/shop'));
        self::assertSame($labels(ModuleAction::Update), self::actions($page, 'adzercms/admin'), 'the update goes with a newer version of the catalog only');
        self::assertSame($labels(ModuleAction::Disable), self::actions($page, $chosen->name));
        self::assertSame($labels(ModuleAction::Enable, ModuleAction::Remove), self::actions($page, $idle->name));

        $this->post('/admin/modules/operation/install?package=acme/shop');
        self::assertResponseRedirects(self::MODULES, message: 'the operation shows its progress in its row');
        $this->post('/admin/modules/operation/remove?package=acme/shop');
        $page = $this->arrive(self::MODULES);
        self::assertSame(self::phrase('core.module.refusal.action'), self::toast($page, 'error'));
        self::assertSame([], self::actions($page, 'acme/shop'), 'a row with an open operation takes no other one');
        self::assertSame(
            self::phrase(OperationState::Pending->label()).' '.self::phrase(ModuleAction::Install->label()),
            trim(self::cell($page->filter('#rows'), 'acme/shop', self::phrase('admin.modules.status'))->text()),
        );
        $this->post('/admin/modules/operation/enable?package='.$idle->name);
        $this->post('/admin/modules/operation/enable?package='.$idle->name);
        self::assertSame(self::phrase('core.module.refusal.open', ['package' => $idle->name]), self::toast($this->arrive(self::MODULES), 'error'));
        $page = $this->client->request('GET', self::OPERATIONS);
        self::assertSame(
            [[$idle->name, self::phrase(ModuleAction::Enable->label()), self::phrase(OperationState::Pending->label())], ['acme/shop', self::phrase(ModuleAction::Install->label()), self::phrase(OperationState::Pending->label())]],
            $page->filter('#operations tbody tr')->each(static fn (Crawler $row): array => \array_slice($row->filter('td')->each(static fn (Crawler $cell): string => trim($cell->text())), 0, 3)),
        );

        $running = self::service(ModuleOperationRepository::class)->inState(OperationState::Pending)[0] ?? self::fail('the operation is not filed');
        $running->start();
        $this->em->flush();
        self::command('adzercms:modules')->execute([]);
        $this->em->clear();
        $interrupted = self::service(ModuleOperationRepository::class)->find($running->getId()) ?? self::fail('the operation is gone');
        self::assertSame([OperationState::Failed, self::phrase('core.module.refusal.interrupted')], [$interrupted->getState(), $interrupted->getOutput()]);
    }

    #[Group('ACMS-CORE-0938')]
    #[Group('ACMS-CORE-0944')]
    #[Group('ACMS-CORE-0967')]
    #[Group('ACMS-US-0004')]
    #[DataProvider('refreshes')]
    public function testEnablingRefreshesSiteOnNewSelectionBeforeWritingIt(?string $refusal, OperationState $state, string $environment): void
    {
        $tree = $this->tree();
        file_put_contents($tree.'/.env.local', "APP_SECRET=kept\nSMS_MODULES=Stale\n");
        if (null !== $refusal) {
            file_put_contents($tree.'/refusal', $refusal);
        }
        $sources = self::service(Sources::class);
        $sources->add(self::REPOSITORY);
        $sources->grant(self::service(ModuleSourceRepository::class)->findOneBy(['url' => self::REPOSITORY]) ?? self::fail('the source is not kept'), '', 'secret');

        $this->post('/admin/modules/operation/enable?package=adzercms/sms-pilot');
        self::command('adzercms:modules')->execute([]);
        $this->em->clear();
        $operation = self::service(ModuleOperationRepository::class)->findOneBy(['package' => 'adzercms/sms-pilot']) ?? self::fail('the operation is not filed');
        $refreshed = file($tree.'/refreshed.env', \FILE_IGNORE_NEW_LINES) ?: [];

        self::assertContains('SMS_MODULES=SmsPilot', $refreshed, 'the refresh builds the new selection');
        self::assertContains('PHP_BINARY='.\PHP_BINARY, $refreshed);
        self::assertContains('ADZERCMS_ENTRY='.$tree, $refreshed, 'the refresh runs under the hold of the job');
        self::assertContains('COMPOSER_AUTH={"bearer":{"repo.acme.test":"secret"}}', $refreshed, 'Composer of the refresh takes credentials of the own sources');
        self::assertSame([], preg_grep('/^(APP_SECRET|SYMFONY_DOTENV_VARS)=/', $refreshed), 'the refresh reads the environment files itself');
        self::assertSame([$state, $refusal], [$operation->getState(), $operation->getOutput()]);
        self::assertSame($environment, file_get_contents($tree.'/.env.local'));
    }

    /** @return iterable<string, array{?string, OperationState, string}> */
    public static function refreshes(): iterable
    {
        yield 'refreshed' => [null, OperationState::Done, "APP_SECRET=kept\nSMS_MODULES=SmsPilot\n"];
        yield 'refresh failed' => ['migration failed', OperationState::Failed, "APP_SECRET=kept\nSMS_MODULES=Stale\n"];
    }

    /** @param list<string> $locked */
    #[Group('ACMS-CORE-0940')]
    #[Group('ACMS-CORE-0946')]
    #[Group('ACMS-US-0004')]
    #[DataProvider('compositions')]
    public function testComposerOperationRunsInStagedCopyCommittedOnlyOnSuccess(ModuleAction $action, string $package, OperationState $state, array $locked, bool $zipped = false): void
    {
        $tree = $this->tree(__DIR__.'/Fixture/site');
        $this->answerCatalog();
        $this->listed([$package]);
        ModuleAction::Update === $action ? $this->branch($package, '1.0.x-dev') : $this->offer($package, '1.0.0');
        if ($zipped) {
            $this->post('/admin/modules/sources/zip', [], ['archive' => $this->archive(['composer.json' => json_encode(self::manifest($package, '1.0.0'), \JSON_THROW_ON_ERROR)])]);
        }
        self::command('adzercms:modules')->execute([]);

        $this->post(\sprintf('/admin/modules/operation/%s?package=%s', $action->value, $package));
        self::command('adzercms:modules')->execute([]);
        $this->em->clear();
        $operation = self::service(ModuleOperationRepository::class)->findOneBy(['package' => $package]) ?? self::fail('the operation is not filed');
        $lock = json_decode((string) file_get_contents($tree.'/composer.lock'), true, flags: \JSON_THROW_ON_ERROR);
        $manifest = json_decode((string) file_get_contents($tree.'/composer.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame($state, $operation->getState(), (string) $operation->getOutput());
        self::assertEqualsCanonicalizing($locked, array_column($lock['packages'], 'name'));
        self::assertEqualsCanonicalizing($locked, array_keys($manifest['require']));
        self::assertSame($zipped, \in_array('adzercms-zip', array_column($manifest['repositories'], 'name'), true), 'the repositories carry the ZIP directory while it holds archives');
        self::assertDirectoryDoesNotExist($tree.'/var/stage');
        self::assertSame(OperationState::Done === $state, is_file($tree.'/refreshed.env'), 'the refresh follows a committed change only');
    }

    /** @return iterable<string, array{ModuleAction, string, OperationState, list<string>}> */
    public static function compositions(): iterable
    {
        yield 'install' => [ModuleAction::Install, 'acme/shop', OperationState::Done, ['acme/shop', 'adzercms/sms-pilot', 'adzercms/theme']];
        yield 'install refused by Composer' => [ModuleAction::Install, 'acme/absent', OperationState::Failed, ['adzercms/sms-pilot', 'adzercms/theme']];
        yield 'update of an engine package' => [ModuleAction::Update, 'adzercms/sms-pilot', OperationState::Done, ['adzercms/sms-pilot', 'adzercms/theme']];
        yield 'remove' => [ModuleAction::Remove, 'adzercms/sms-pilot', OperationState::Done, ['adzercms/theme']];
        yield 'install of an uploaded ZIP' => [ModuleAction::Install, 'acme/zipped', OperationState::Done, ['acme/zipped', 'adzercms/sms-pilot', 'adzercms/theme'], true];
    }

    #[Group('ACMS-CORE-0143')]
    #[Group('ACMS-CORE-0944')]
    #[Group('ACMS-US-0004')]
    public function testOwnSourcesShareCredentialsOfTheirHostAndShowUnavailability(): void
    {
        $base = $this->client->request('GET', self::SOURCES)->filter('#sources tbody tr');
        self::assertSame(Sources::BASE, $base->filter('td strong')->each(static fn (Crawler $source): string => trim($source->text())));
        self::assertCount(0, $base->filter('td.row-actions form'), 'a base source is not dropped');

        $this->connect(self::REPOSITORY.'/one');
        self::assertResponseRedirects(self::SOURCES, message: 'a source goes to its tab');
        $this->connect(self::REPOSITORY.'/two');
        $this->connect(self::REPOSITORY.'/one');
        self::assertSame(self::phrase('core.module.source.taken', ['url' => self::REPOSITORY.'/one']), self::toast($this->arrive(self::SOURCES), 'error'));
        $one = self::service(ModuleSourceRepository::class)->findOneBy(['url' => self::REPOSITORY.'/one']) ?? self::fail('the source is not kept');
        $dialog = $this->client->request('GET', '/admin/modules/sources/'.$one->getId().'/credentials');
        $this->client->submit($dialog->selectButton(self::phrase('core.action.save'))->form(['token' => 'secret']));
        $this->connect(self::REPOSITORY.'/three');

        $this->answerCatalog();
        $this->listed([]);
        $this->unavailable = [self::REPOSITORY];
        self::command('adzercms:modules')->execute([]);
        $sources = $this->client->request('GET', self::SOURCES)->filter('#sources');
        foreach (['one', 'two', 'three'] as $path) {
            self::assertSame(self::phrase('admin.setting.set'), trim(self::cell($sources, self::REPOSITORY.'/'.$path, self::phrase('admin.sources.access'))->text()), 'a source takes credentials of its host');
            self::assertSame(self::phrase('admin.sources.down'), trim(self::cell($sources, self::REPOSITORY.'/'.$path, self::phrase('admin.sources.source'))->filter('.badge')->text()));
        }

        $this->post('/admin/modules/sources/'.$one->getId().'/drop');
        self::assertSame(
            [...Sources::BASE, self::REPOSITORY.'/two', self::REPOSITORY.'/three'],
            $this->arrive(self::SOURCES)->filter('#sources tbody tr td strong')->each(static fn (Crawler $source): string => trim($source->text())),
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
        return self::buttons($page, $name)->each(static fn (Crawler $button): string => trim($button->text()));
    }

    /** @return list<?string> */
    private static function confirmations(Crawler $page, string $name): array
    {
        return self::buttons($page, $name)->each(static fn (Crawler $button): ?string => $button->attr('data-confirm'));
    }

    private static function buttons(Crawler $page, string $name): Crawler
    {
        return $page->filter('#rows tbody tr')->reduce(static fn (Crawler $row): bool => $name === trim($row->filter('td strong')->text()))->filter('td.row-actions button');
    }

    /** @return list<string> */
    private static function cards(Crawler $listing): array
    {
        return $listing->filter('#rows tbody tr[data-href]')->each(static fn (Crawler $row): string => (string) $row->attr('data-href'));
    }
}
