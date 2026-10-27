<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Tests;

use AdzerCMS\Admin\AssembledModules;
use AdzerCMS\Admin\ModuleScope;
use AdzerCMS\Admin\ModuleState;
use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\ExtensionType;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Contracts\Setting\Tab;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Build\Preview;
use AdzerCMS\Core\Kernel;
use AdzerCMS\Core\Module\Catalog as ModuleCatalog;
use AdzerCMS\Core\Module\CatalogRow;
use AdzerCMS\Core\Module\ModuleAction;
use AdzerCMS\Core\Module\OperationState;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Module\Sources;
use AdzerCMS\Core\Module\Store;
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
use AdzerCMS\Core\Ui\Back;
use AdzerCMS\Core\Ui\Entries;
use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Process\Process;

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
    public function testSectionBuildsOnlyItsActiveTabOpeningOnAllExtensions(): void
    {
        $modules = self::service(AssembledModules::class)->modules();
        $listing = $this->client->request('GET', self::MODULES);
        $tabs = $listing->filter('nav.tabs a.tab[href*="tab="]')->each(static fn (Crawler $tab): string => trim($tab->text()));

        self::assertSame(
            [self::phrase(ExtensionType::all()), self::phrase('admin.sources.title'), self::phrase('admin.operations.title')],
            [$tabs[0] ?? null, ...\array_slice($tabs, -2)],
        );
        foreach (['' => 'rows', '?tab=theme' => 'rows', '?tab=sources' => 'sources', '?tab=operations' => 'operations', '?tab=absent' => 'rows'] as $query => $table) {
            self::assertSame(
                [$table],
                $this->client->request('GET', self::MODULES.$query)->filter('#rows, #sources, #operations')->each(static fn (Crawler $built): string => (string) $built->attr('id')),
                'only the active tab is built',
            );
        }
        self::assertSame(
            [...array_map(self::phrase(...), ['admin.modules.name', 'admin.modules.version', 'admin.modules.repository', 'admin.modules.status']), ''],
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

        self::assertSame(self::phrase($modules['admin']['description']), self::tile($this->client->request('GET', '/admin/modules/admin'), self::phrase('admin.modules.description')));
        self::assertCount(1, $this->client->request('GET', '/admin/modules/relay')->filter('form#settings'));

        $this->client->request('GET', '/admin/modules/absent');
        self::assertResponseStatusCodeSame(404);
    }

    #[Group('ACMS-CORE-0023')]
    #[Group('ACMS-CORE-0999')]
    #[Group('ACMS-US-0004')]
    public function testTypesOfCatalogRowsGoAsTabsInCatalogOrderWithThemesAsCards(): void
    {
        $this->tree();
        $this->answerCatalog();
        $this->listed(['acme/look', 'acme/site', 'acme/till']);
        $this->offer('acme/look', '1.0.0', provide: ['adzercms/theme-implementation' => '1.0.0']);
        $this->offer('acme/site', '1.0.0');
        $this->offer('acme/till', '1.0.0', provide: ['adzercms/sms-implementation' => '1.0.0']);
        $this->stores[Store::DEFAULT] = [
            ['name' => 'acme/site', 'type' => ExtensionType::Template->value, 'demo' => 'https://site.example.test'],
            ['name' => 'acme/look', 'type' => ExtensionType::Theme->value, 'demo' => 'https://look.example.test'],
        ];
        self::command('adzercms:modules')->execute([]);
        $page = $this->client->request('GET', self::MODULES);
        $text = static fn (Crawler $node): string => trim($node->text());

        self::assertSame(
            array_map(self::phrase(...), [ExtensionType::all(), ExtensionType::Module->tab(), ExtensionType::Integration->tab(), ExtensionType::Theme->tab(), ExtensionType::Template->tab(), 'admin.sources.title', 'admin.operations.title']),
            $page->filter('nav.tabs a.tab')->each($text),
            'a type goes as a tab while the catalog has its rows',
        );
        self::assertSame(['acme/site', 'acme/look'], \array_slice($page->filter('#rows tbody tr td:first-child strong')->each($text), 0, 2), 'the rows go in the order of the extension catalog');

        $templates = $this->client->request('GET', self::MODULES.'?tab='.ExtensionType::Template->value)->filter('#rows tbody tr');
        self::assertSame(['acme/site'], $templates->filter('td:first-child strong')->each($text));
        self::assertSame(['https://site.example.test'], $templates->filter('a.demo')->each(static fn (Crawler $link): string => (string) $link->attr('href')));

        $cards = $this->client->request('GET', self::MODULES.'?tab='.ExtensionType::Theme->value)->filter('#rows');
        self::assertCount(0, $cards->filter('table'), 'themes go as cards');
        $themes = array_map(static fn (CatalogRow $row): string => $row->name, array_filter(self::service(ModuleCatalog::class)->rows()['rows'], static fn (CatalogRow $row): bool => ExtensionType::Theme === $row->type));
        self::assertSame(array_values($themes), $cards->filter('article.offer-card')->each(static fn (Crawler $card): string => (string) $card->attr('data-package')));
        $look = $cards->filter('article.offer-card[data-package="acme/look"]');
        self::assertSame('https://look.example.test', $look->filter('a.demo')->attr('href'));
        self::assertSame([self::phrase(ModuleAction::Install->label())], $look->filter('.offer-actions button')->each($text));

        $this->post((string) $look->filter('.offer-actions form')->attr('action'));
        self::assertResponseRedirects(self::MODULES.'?tab='.ExtensionType::Theme->value, message: 'an action of a card returns to its tab');
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

    #[Group('ACMS-CORE-1003')]
    #[Group('ACMS-US-0004')]
    public function testCardOfExternalServiceGuidesToItsCredentials(): void
    {
        $steps = $this->client->request('GET', '/admin/modules/relay')->filter('ol li');

        self::assertSame(['Open '.RelayGateway::KEYS, 'Issue a token and copy it into Relay token'], $steps->each(static fn (Crawler $step): string => trim($step->text())));
        self::assertSame(RelayGateway::KEYS, $steps->filter('a')->attr('href'), 'the address of a step leads to the service');
        self::assertCount(0, $this->client->request('GET', '/admin/modules/admin')->filter('ol li'), 'a module without a service has no steps');
    }

    #[Group('ACMS-CORE-0934')]
    #[Group('ACMS-CORE-0147')]
    #[Group('ACMS-CORE-0981')]
    #[Group('ACMS-US-0004')]
    public function testModulesListOfferedAndInstalledPackagesOfCatalogSnapshotByStatusAndCategory(): void
    {
        $this->answerCatalog();
        $this->listed(['acme/till', 'acme/shop', 'adzercms/admin']);
        $this->offer('acme/till', '1.2.0', provide: ['adzercms/sms-implementation' => '1.0.0'], categories: ['messaging'], translations: ['ru' => 'Касса acme']);
        $this->offer('acme/shop', '1.0.0', categories: ['commerce']);
        $this->branch('adzercms/admin', '1.0.x-dev');
        self::command('adzercms:modules')->execute([]);
        $idle = array_values(array_filter(self::service(ModuleCatalog::class)->rows()['rows'], static fn (CatalogRow $row): bool => null !== $row->installed && !$row->selected))[0] ?? self::fail('the tree has no installed module outside the suite');
        $listing = $this->client->request('GET', self::MODULES)->filter('#rows');
        $cells = static fn (string $name, string ...$columns): array => array_map(static fn (string $column): string => trim(self::cell($listing, $name, self::phrase($column))->text()), $columns);

        self::assertSame(
            ['1.2.0', 'repo.packagist.org', self::phrase(ModuleState::Available->label())],
            $cells('acme/till', 'admin.modules.version', 'admin.modules.repository', 'admin.modules.status'),
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
        self::assertEqualsCanonicalizing(['acme/till', 'acme/shop'], $names('status='.ModuleScope::Available->value));
        self::assertSame(['adzercms/admin'], $names('status='.ModuleScope::Updatable->value));
        $installed = $names('status='.ModuleScope::Installed->value);
        self::assertContains('adzercms/admin', $installed);
        self::assertContains($idle->name, $installed);
        self::assertNotContains('acme/till', $installed);

        $this->client->loginUser($this->record('79990000002')->setRole(Role::Admin)->setLanguage('ru'));
        $this->em->flush();
        $russian = $this->client->request('GET', self::MODULES)->filter('#rows');
        $described = static fn (string $name): string => trim(self::cell($russian, $name, self::phrase('admin.modules.name', locale: 'ru'))->filter('.muted')->text());
        self::assertSame(
            ['Касса acme', 'Package acme/shop', Registry::installed()->package($idle->name)->description->translations['ru'] ?? self::fail('the installed module is not described in Russian')],
            [$described('acme/till'), $described('acme/shop'), $described($idle->name)],
            'a module tells what it does in the language of the reader, installed or not, without a translation — in the source',
        );
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

    #[Group('ACMS-CORE-0936')]
    #[Group('ACMS-US-0004')]
    #[DataProvider('replacements')]
    public function testInstallingModuleOfSingleKindConfirmsReplacingTheSelectedOneInOneWindow(bool $verified, string $unverified): void
    {
        $this->answerCatalog();
        $this->listed(['acme/look']);
        $this->offer('acme/look', '1.0.0', provide: ['adzercms/theme-implementation' => '1.0.0']);
        if ($verified) {
            $this->mark('acme/look', self::commit('acme/look', '1.0.0'));
        }
        self::command('adzercms:modules')->execute([]);
        $replaced = array_values(array_filter(self::service(ModuleCatalog::class)->rows()['rows'], static fn (CatalogRow $row): bool => $row->selected && CoreKind::Theme === $row->kind))[0] ?? self::fail('the suite selects no theme');

        self::assertSame(
            [self::phrase('admin.modules.replace', ['action' => ModuleAction::Install->value, 'package' => 'acme/look', 'replaced' => $replaced->name, 'unverified' => $unverified])],
            self::confirmations($this->client->request('GET', self::MODULES), 'acme/look'),
        );
    }

    /** @return iterable<string, array{bool, string}> */
    public static function replacements(): iterable
    {
        yield 'verified version' => [true, 'no'];
        yield 'version without the mark' => [false, 'yes'];
    }

    /** @param list<string> $committed */
    #[Group('ACMS-CORE-0145')]
    #[Group('ACMS-CORE-0991')]
    #[Group('ACMS-US-0004')]
    #[DataProvider('commits')]
    public function testOperationOnSiteUnderGitCommitsItsPathsAfterRefresh(ModuleAction $action, string $package, ?string $site, array $committed): void
    {
        $tree = $this->repository($this->tree($site));
        if (ModuleAction::Install === $action) {
            $this->answerCatalog();
            $this->listed([$package]);
            $this->offer($package, '1.0.0');
            self::command('adzercms:modules')->execute([]);
        }

        $this->post(\sprintf('/admin/modules/operation/%s?package=%s', $action->value, $package));
        self::command('adzercms:modules')->execute([]);
        $this->em->clear();
        $operation = self::service(ModuleOperationRepository::class)->findOneBy(['package' => $package]) ?? self::fail('the operation is not filed');
        $git = static fn (string ...$arguments): string => trim((new Process(['git', ...$arguments], $tree))->mustRun()->getOutput());

        self::assertSame(OperationState::Done, $operation->getState(), (string) $operation->getOutput());
        self::assertSame([\sprintf('%s module %s', ucfirst($action->value), $package), ...$committed], array_values(array_filter(explode("\n", $git('show', '--name-only', '--format=%s', 'HEAD')))));
        self::assertSame('', $git('status', '--porcelain'), 'the commit takes every change of the operation');
        self::assertContains('ADZERCMS_REFRESHED='.realpath($tree), file($tree.'/committed.env', \FILE_IGNORE_NEW_LINES) ?: []);
        self::assertSame(0, (new Process([\dirname(__DIR__, 2).'/core/bin/adzercms-refresh'], $tree, ['ADZERCMS_REFRESHED' => realpath($tree)]))->run(), 'the refresh of the commit hook leaves the site refreshed by the operation');
    }

    /** @return iterable<string, array{ModuleAction, string, ?string, list<string>}> */
    public static function commits(): iterable
    {
        yield 'enable' => [ModuleAction::Enable, 'adzercms/sms-pilot', null, ['.env']];
        yield 'install' => [ModuleAction::Install, 'acme/shop', __DIR__.'/Fixture/site', ['.env', 'composer.json', 'composer.lock']];
    }

    /** @param array<string, string> $parameters */
    #[Group('ACMS-CORE-0938')]
    #[Group('ACMS-CORE-0990')]
    #[Group('ACMS-US-0004')]
    #[DataProvider('dirt')]
    public function testSiteUnderGitRefusesOperationOverChangesOutsideHistory(string $file, string $content, string $refusal, array $parameters): void
    {
        $tree = $this->repository($this->tree());
        file_put_contents($tree.'/'.$file, $content);

        $this->post('/admin/modules/operation/enable?package=adzercms/sms-pilot');
        $refused = self::service(Tree::class)->refresh('Enable module adzercms/sms-pilot', [], ['SMS_MODULES' => 'SmsPilot'], static fn () => self::fail('a refused operation transfers nothing'));

        self::assertSame(self::phrase($refusal, $parameters), self::toast($this->arrive(self::MODULES), 'error'));
        self::assertNull(self::service(ModuleOperationRepository::class)->findOneBy(['package' => 'adzercms/sms-pilot']));
        self::assertSame(self::phrase($refusal, $parameters), $refused instanceof Refusal ? self::phrase($refused->reason) : self::fail('the hold does not refuse'));
        self::assertFileDoesNotExist($tree.'/refreshed.env');
    }

    /** @return iterable<string, array{string, string, string, array<string, string>}> */
    public static function dirt(): iterable
    {
        yield 'uncommitted change' => ['notes.txt', 'draft', 'core.module.refusal.uncommitted', ['paths' => 'notes.txt']];
        yield 'selection in .env.local' => ['.env.local', "SMS_MODULES=Stale\n", 'core.module.refusal.local', ['names' => 'SMS_MODULES']];
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
        $idle = array_values(array_filter($rows, static fn (CatalogRow $row): bool => null !== $row->installed && !$row->selected && CoreKind::Theme !== $row->kind))[0] ?? self::fail('the tree has no installed module outside the suite');
        $chosen = array_values(array_filter($rows, static fn (CatalogRow $row): bool => $row->selected && CoreKind::Admin !== $row->kind))[0] ?? self::fail('the suite selects no module beside the admin panel');
        $labels = static fn (ModuleAction ...$actions): array => array_map(static fn (ModuleAction $action): string => self::phrase($action->label()), $actions);
        $page = $this->client->request('GET', self::MODULES);
        self::assertSame($labels(ModuleAction::Install), self::actions($page, 'acme/shop'));
        self::assertSame($labels(ModuleAction::Update), self::actions($page, 'adzercms/admin'), 'the update goes with a newer version of the catalog only');
        self::assertSame($labels(ModuleAction::Disable, ModuleAction::Remove), self::actions($page, $chosen->name));
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

    #[Group('ACMS-CORE-0936')]
    #[Group('ACMS-CORE-1000')]
    #[Group('ACMS-CORE-1002')]
    #[Group('ACMS-US-0004')]
    public function testThemePreviewBuildsAsideAndMarksOperatorUntilExit(): void
    {
        $tree = $this->repository($this->tree());
        file_put_contents($tree.'/notes.txt', 'draft');
        $this->answerCatalog();
        $this->listed([]);
        self::command('adzercms:modules')->execute([]);
        $labels = static fn (string|ModuleAction ...$actions): array => array_map(static fn (string|ModuleAction $action): string => self::phrase(\is_string($action) ? $action : $action->label()), $actions);
        $preview = self::service(Kernel::class, 'kernel')->preview();

        self::assertSame($labels(ModuleAction::Enable, ModuleAction::Preview, ModuleAction::Remove), self::actions($this->client->request('GET', self::MODULES), 'adzercms/threadbare'));
        $this->post('/admin/modules/operation/preview?package=adzercms/underlay');
        self::assertSame(self::phrase('core.module.refusal.migrations'), self::toast($this->arrive(self::MODULES), 'error'));

        $this->post('/admin/modules/operation/preview?package=adzercms/threadbare');
        self::command('adzercms:modules')->execute([]);
        $this->em->clear();
        $operation = self::service(ModuleOperationRepository::class)->findOneBy(['package' => 'adzercms/threadbare']) ?? self::fail('the operation is not filed');
        $built = file($tree.'/built.env', \FILE_IGNORE_NEW_LINES) ?: [];

        self::assertSame([OperationState::Done, null], [$operation->getState(), $operation->getOutput()], 'a change of the tree does not refuse the preview');
        self::assertContains('THEME_MODULE=Threadbare', $built, 'the preview builds the selection with the theme');
        self::assertContains('ARGUMENTS=--preview', $built);
        self::assertContains('ADZERCMS_ENTRY='.$tree, $built, 'the preview build runs under the hold of the job');
        self::assertFileDoesNotExist($tree.'/refreshed.env', 'the preview neither migrates nor switches the instance');
        self::assertSame("?? notes.txt\n", (new Process(['git', 'status', '--porcelain'], $tree))->mustRun()->getOutput(), 'the preview leaves the tree and the selection as they are');

        $preview->keep('Threadbare', ['THEME_MODULE' => 'Threadbare']);
        try {
            self::assertSame($labels('admin.modules.preview', ModuleAction::Enable, ModuleAction::Remove), self::actions($this->client->request('GET', self::MODULES), 'adzercms/threadbare'));
            $this->post('/admin/modules/theme/preview?package=adzercms/underlay');
            self::assertSame(self::phrase('core.module.refusal.action'), self::toast($this->arrive(self::MODULES), 'error'), 'a theme without a ready preview takes no mark');

            $this->post('/admin/modules/theme/preview?package=adzercms/threadbare');
            self::assertResponseRedirects(self::service(Entries::class)->home());
            $mark = self::cookie(Preview::COOKIE) ?? self::fail('the preview sets no mark');
            $secret = self::getContainer()->getParameter('kernel.secret');
            self::assertIsString($secret);
            self::assertSame('Threadbare', Preview::marked((string) $mark->getValue(), time(), $secret));
            self::assertSame([true, Cookie::SAMESITE_LAX], [$mark->isHttpOnly(), $mark->getSameSite()]);

            $this->post('/preview/exit', [Back::FIELD => self::MODULES.'?tab=theme']);
            self::assertResponseRedirects(self::MODULES.'?tab=theme', message: 'the exit goes back to the page');
            self::assertTrue(self::cookie(Preview::COOKIE)?->isCleared(), 'the exit drops the mark');
        } finally {
            $preview->drop();
        }
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

    /** @param list<string> $locked */
    #[Group('ACMS-CORE-0993')]
    #[Group('ACMS-US-0004')]
    #[DataProvider('installations')]
    public function testInstallingEnablesModuleBySelectionOfStagedCopy(?string $refusal, OperationState $state, array $locked, ?string $selection): void
    {
        $tree = $this->tree(__DIR__.'/Fixture/site');
        if (null !== $refusal) {
            file_put_contents($tree.'/selection-refusal', $refusal);
        }
        $this->answerCatalog();
        $this->listed(['acme/shop']);
        $this->offer('acme/shop', '1.0.0');
        self::command('adzercms:modules')->execute([]);

        $this->post('/admin/modules/operation/install?package=acme/shop');
        self::command('adzercms:modules')->execute([]);
        $this->em->clear();
        $operation = self::service(ModuleOperationRepository::class)->findOneBy(['package' => 'acme/shop']) ?? self::fail('the operation is not filed');
        $lock = json_decode((string) file_get_contents($tree.'/composer.lock'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame([$state, $refusal], [$operation->getState(), $operation->getOutput()]);
        self::assertEqualsCanonicalizing($locked, array_column($lock['packages'], 'name'));
        self::assertSame(null === $selection ? [] : [$selection], array_values(preg_grep('/^MODULES=/', is_file($tree.'/refreshed.env') ? file($tree.'/refreshed.env', \FILE_IGNORE_NEW_LINES) ?: [] : []) ?: []), 'the refresh builds the selection with the installed module');
        self::assertSame(null === $selection ? '' : $selection."\n", is_file($tree.'/.env.local') ? file_get_contents($tree.'/.env.local') : '');
    }

    /** @return iterable<string, array{?string, OperationState, list<string>, ?string}> */
    public static function installations(): iterable
    {
        yield 'enabled' => [null, OperationState::Done, ['acme/shop', 'adzercms/sms-pilot', 'adzercms/theme'], 'MODULES=Shop'];
        yield 'selection refused' => ['module "Shop" requires module "Till" selected in MODULES', OperationState::Failed, ['adzercms/sms-pilot', 'adzercms/theme'], null];
    }

    /** @param list<string> $locked */
    #[Group('ACMS-CORE-0992')]
    #[Group('ACMS-US-0004')]
    #[DataProvider('removals')]
    public function testRemovingEnabledModuleDisablesItInTheSameOperation(string $package, OperationState $state, ?string $selection, array $locked): void
    {
        $tree = $this->tree(__DIR__.'/Fixture/site');
        $this->answerCatalog();
        $this->listed([]);
        self::command('adzercms:modules')->execute([]);
        $kept = [$_SERVER['SMS_MODULES'] ?? null, $_ENV['SMS_MODULES'] ?? null];
        $_SERVER['SMS_MODULES'] = $_ENV['SMS_MODULES'] = 'SmsPilot';

        try {
            $this->post(\sprintf('/admin/modules/operation/remove?package=%s', $package));
            self::command('adzercms:modules')->execute([]);
        } finally {
            [$_SERVER['SMS_MODULES'], $_ENV['SMS_MODULES']] = $kept;
        }
        $this->em->clear();
        $operation = self::service(ModuleOperationRepository::class)->findOneBy(['package' => $package]) ?? self::fail('the operation is not filed');
        $lock = json_decode((string) file_get_contents($tree.'/composer.lock'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame($state, $operation->getState(), (string) $operation->getOutput());
        self::assertEqualsCanonicalizing($locked, array_column($lock['packages'], 'name'));
        self::assertSame(null === $selection ? [] : [$selection], array_values(preg_grep('/^SMS_MODULES=/', is_file($tree.'/refreshed.env') ? file($tree.'/refreshed.env', \FILE_IGNORE_NEW_LINES) ?: [] : []) ?: []), 'the refresh builds the selection without the removed module');
        self::assertSame(null === $selection ? '' : $selection."\n", is_file($tree.'/.env.local') ? file_get_contents($tree.'/.env.local') : '');
    }

    /** @return iterable<string, array{string, OperationState, ?string, list<string>}> */
    public static function removals(): iterable
    {
        yield 'enabled module' => ['adzercms/sms-pilot', OperationState::Done, 'SMS_MODULES=', ['adzercms/theme']];
        yield 'module an enabled one depends on' => ['adzercms/theme', OperationState::Failed, null, ['adzercms/sms-pilot', 'adzercms/theme']];
    }

    #[Group('ACMS-CORE-0143')]
    #[Group('ACMS-CORE-0944')]
    #[Group('ACMS-US-0004')]
    public function testOwnSourcesShareCredentialsOfTheirHostAndShowUnavailability(): void
    {
        $base = $this->client->request('GET', self::SOURCES)->filter('#sources tbody tr');
        self::assertSame([Store::DEFAULT, ...Sources::BASE], $base->filter('td strong')->each(static fn (Crawler $source): string => trim($source->text())));
        self::assertCount(0, $base->filter('td.row-actions form'), 'a base source is not dropped');
        self::assertSame(['/admin/settings?tab='.Tab::General->value], $base->filter('td.row-actions a')->each(static fn (Crawler $link): string => (string) $link->attr('href')), 'the extension catalog leads to its setting');

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
        $this->stores[Store::DEFAULT] = null;
        self::command('adzercms:modules')->execute([]);
        $sources = $this->client->request('GET', self::SOURCES)->filter('#sources');
        foreach (['one', 'two', 'three'] as $path) {
            self::assertSame(self::phrase('admin.setting.set'), trim(self::cell($sources, self::REPOSITORY.'/'.$path, self::phrase('admin.sources.access'))->text()), 'a source takes credentials of its host');
            self::assertSame(self::phrase('admin.sources.down'), trim(self::cell($sources, self::REPOSITORY.'/'.$path, self::phrase('admin.sources.source'))->filter('.badge')->text()));
        }
        self::assertSame(self::phrase('admin.sources.down'), trim(self::cell($sources, Store::DEFAULT, self::phrase('admin.sources.source'))->filter('.badge')->text()));

        $this->post('/admin/modules/sources/'.$one->getId().'/drop');
        self::assertSame(
            [Store::DEFAULT, ...Sources::BASE, self::REPOSITORY.'/two', self::REPOSITORY.'/three'],
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
        self::getContainer()->set(Tree::class, new Tree($this->tree, __DIR__.'/Fixture/refresh', __DIR__.'/Fixture/selection', __DIR__.'/Fixture/build'));

        return $this->tree;
    }

    private function repository(string $tree): string
    {
        file_put_contents($tree.'/.gitignore', "/.env.local\n/var/\n/vendor/\n/refreshed.env\n/committed.env\n/built.env\n");
        file_put_contents($tree.'/.env.local', "APP_SECRET=kept\n");
        foreach ([['init', '--quiet'], ['config', 'user.name', 'Site'], ['config', 'user.email', 'site@acme.test'], ['config', 'core.hooksPath', __DIR__.'/Fixture/hooks'], ['add', '--all'], ['commit', '--quiet', '--message', 'Site']] as $arguments) {
            (new Process(['git', ...$arguments], $tree))->mustRun();
        }

        return $tree;
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

    private function cookie(string $name): ?Cookie
    {
        return array_find($this->client->getResponse()->headers->getCookies(), static fn (Cookie $cookie): bool => $name === $cookie->getName());
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
