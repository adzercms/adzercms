<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Tests;

use AdzerCMS\Contracts\Module\Category;
use AdzerCMS\Core\Setting\Catalog;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\HttpAnswers;
use AdzerCMS\Extensions\ExtensionsSettings;
use AdzerCMS\Extensions\ExtensionState;
use Composer\MetadataMinifier\MetadataMinifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MetadataTest extends CatalogTestCase
{
    use Configuration;

    private const PAID = 'https://paid.example.test';
    private const OPEN = 'https://open.example.test/composer';
    private const NAME = 'acme/shop';

    /** @var array<string, list<array<string, mixed>>> */
    private array $paid = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $open = [];

    private bool $down = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operate();
        $this->configure([ExtensionsSettings::REPOSITORIES => self::PAID.', '.self::OPEN]);

        self::service(HttpAnswers::class)->respond(fn (string $method, string $url): MockResponse => match (true) {
            self::PAID.'/packages.json' === $url => $this->down
                ? new MockResponse('', ['http_code' => 503])
                : new JsonMockResponse(['packages' => [], 'metadata-url' => '/p2/%package%.json']),
            self::OPEN.'/packages.json' === $url => new JsonMockResponse(['packages' => [], 'metadata-url' => self::OPEN.'/p2/%package%.json']),
            str_starts_with($url, self::PAID.'/p2/') => self::answer($this->paid, substr($url, \strlen(self::PAID.'/p2/'), -5)),
            str_starts_with($url, self::OPEN.'/p2/') => self::answer($this->open, substr($url, \strlen(self::OPEN.'/p2/'), -5)),
            default => self::fail(\sprintf('unexpected request %s', $url)),
        });
    }

    #[Group('ACMS-EXT-0003')]
    #[Group('ACMS-US-0035')]
    public function testSavedEntryTakesLatestStableVersionOfFirstRepositoryWithPackage(): void
    {
        $this->open[self::NAME] = [
            self::version(self::NAME, '2.0.0-RC1', 'Next shop'),
            self::version(self::NAME, '1.10.0', 'Shop for AdzerCMS'),
            self::version(self::NAME, '1.9.3', 'Old shop'),
        ];

        $this->post('/admin/extensions/new', ['name' => self::NAME, 'type' => 'module', 'price' => '', 'demo' => '']);

        $entry = self::extensions()->named(self::NAME) ?? self::fail('the entry is not in the catalog');
        self::assertSame(['Shop for AdzerCMS', '1.10.0', 'https://github.com/acme/shop.git', 'https://shop.example.test'],
            [$entry->getDescription(), $entry->getVersion(), $entry->getSource(), $entry->getHomepage()]);
        self::assertSame(ExtensionState::Published, $entry->state());

        $this->paid[self::NAME] = [self::version(self::NAME, '3.0.0', 'Paid shop')];
        $this->post('/admin/extensions/'.$entry->getId(), ['name' => self::NAME, 'type' => 'module', 'price' => '900', 'demo' => '']);

        $entry = $this->reread($entry);
        self::assertSame(['Paid shop', '3.0.0'], [$entry->getDescription(), $entry->getVersion()]);
    }

    /** @param list<Category> $categories */
    #[Group('ACMS-CORE-0147')]
    #[Group('ACMS-EXT-0003')]
    #[DataProvider('categorized')]
    public function testEntryTakesUpToThreeKnownCategoriesInListOrder(mixed $keys, array $categories): void
    {
        $this->open[self::NAME] = [self::version(self::NAME, '1.0.0', 'Shop', $keys)];

        $this->post('/admin/extensions/new', ['name' => self::NAME, 'type' => 'module', 'price' => '', 'demo' => '']);

        self::assertSame($categories, (self::extensions()->named(self::NAME) ?? self::fail('the entry is not in the catalog'))->getCategories());
    }

    /** @return iterable<string, array{mixed, list<Category>}> */
    public static function categorized(): iterable
    {
        yield 'list order' => [['commerce', 'payments'], [Category::Payments, Category::Commerce]];
        yield 'first three known' => [['seo', 'data', 1, 'commerce', 'other', 'payments', 'billing'], [Category::Payments, Category::Commerce, Category::Data]];
        yield 'none' => [[], [Category::Other]];
        yield 'not a list' => ['payments', [Category::Other]];
    }

    #[Group('ACMS-EXT-0003')]
    public function testPackageLinksOutsideWebAreNotKept(): void
    {
        $this->open[self::NAME] = [[
            'homepage' => 'javascript:alert(1)',
            'source' => ['url' => 'git@github.com:acme/shop.git', 'type' => 'git', 'reference' => 'a6b26c2'],
        ] + self::version(self::NAME, '1.0.0', 'Shop')];

        $this->post('/admin/extensions/new', ['name' => self::NAME, 'type' => 'module', 'price' => '', 'demo' => '']);

        $entry = self::extensions()->named(self::NAME) ?? self::fail('the entry is not in the catalog');
        self::assertSame(['1.0.0', null, null], [$entry->getVersion(), $entry->getSource(), $entry->getHomepage()]);
    }

    #[Group('ACMS-EXT-0003')]
    public function testEntryWithoutStableVersionTakesDefaultBranchByItsAlias(): void
    {
        $this->open[self::NAME] = [self::version(self::NAME, '1.0.0-beta2', 'Beta shop')];
        $this->open[self::NAME.'~dev'] = [
            self::version(self::NAME, 'dev-feature', 'Feature shop'),
            ['default-branch' => true, 'extra' => ['branch-alias' => ['dev-main' => '1.0.x-dev']]] + self::version(self::NAME, 'dev-main', 'Shop in development'),
        ];

        $this->post('/admin/extensions/new', ['name' => self::NAME, 'type' => 'module', 'price' => '', 'demo' => '']);

        $entry = self::extensions()->named(self::NAME) ?? self::fail('the entry is not in the catalog');
        self::assertSame(['Shop in development', '1.0.x-dev', ExtensionState::Published], [$entry->getDescription(), $entry->getVersion(), $entry->state()]);
    }

    #[Group('ACMS-EXT-0004')]
    #[Group('ACMS-US-0035')]
    public function testEntryWithoutStableVersionOrDefaultBranchStaysOffSiteWithStateNamed(): void
    {
        $this->open[self::NAME] = [self::version(self::NAME, '1.0.0-beta2', 'Shop')];

        $this->post('/admin/extensions/new', ['name' => self::NAME, 'type' => 'module', 'price' => '', 'demo' => '']);
        $page = $this->arrive('/admin/extensions');

        self::assertSame(self::phrase('extensions.flash.missing', ['name' => self::NAME]), self::toast($page, 'error'));
        self::assertSame(self::phrase(ExtensionState::Missing->label()), trim(self::cell($page, self::NAME, self::phrase('extensions.column.state'))->text()));
    }

    #[Group('ACMS-EXT-0003')]
    #[Group('ACMS-US-0035')]
    public function testDailyJobRefreshesEntriesAndKeepsThemWhenRepositoryIsDown(): void
    {
        $kept = $this->entry('acme/kept');
        $gone = $this->entry('acme/gone');
        $this->open['acme/kept'] = [self::version('acme/kept', '1.0.0', 'Kept', ['support', 'payments'])];
        $this->open['acme/gone'] = [self::version('acme/gone', '1.0.0', 'Gone')];
        self::assertSame(Command::SUCCESS, self::command('adzercms:extensions')->execute([]));

        $this->open['acme/kept'] = [self::version('acme/kept', '1.1.0', 'Kept better', ['content', 'support'])];
        unset($this->open['acme/gone']);
        $job = self::command('adzercms:extensions');

        self::assertSame(Command::SUCCESS, $job->execute([]));
        $kept = $this->reread($kept);
        self::assertSame(['1.1.0', [Category::Content, Category::Support]], [$kept->getVersion(), $kept->getCategories()]);
        self::assertNull($this->reread($gone)->getVersion());
        self::assertStringContainsString('acme/gone', $job->getDisplay());

        $this->down = true;
        $job = self::command('adzercms:extensions');

        self::assertSame(Command::FAILURE, $job->execute([]));
        self::assertSame('1.1.0', $this->reread($kept)->getVersion());
        self::assertStringContainsString(self::PAID, $job->getDisplay());
    }

    #[Group('ACMS-EXT-0003')]
    public function testRepositoryOutsideWebIsRefused(): void
    {
        $field = self::service(Catalog::class)->fields()[ExtensionsSettings::REPOSITORIES];

        $refusal = self::service(Settings::class)->save($field, self::OPEN.', ftp://mirror.example.test');

        self::assertSame(self::phrase('extensions.refusal.repository', ['repository' => 'ftp://mirror.example.test']), self::phrase($refusal->reason ?? self::fail('the repository is accepted')));
    }

    /** @param array<string, list<array<string, mixed>>> $packages */
    private static function answer(array $packages, string $name): MockResponse
    {
        return isset($packages[$name])
            ? new JsonMockResponse(['minified' => 'composer/2.0', 'packages' => [strtok($name, '~') => MetadataMinifier::minify($packages[$name])]])
            : new MockResponse('', ['http_code' => 404]);
    }

    /** @return array<string, mixed> */
    private static function version(string $name, string $version, string $description, mixed $categories = []): array
    {
        return [
            'name' => $name,
            'description' => $description,
            'version' => $version,
            'type' => 'adzercms-module',
            'homepage' => 'https://shop.example.test',
            'source' => ['url' => 'https://github.com/acme/shop.git', 'type' => 'git', 'reference' => 'a6b26c2'],
            'extra' => ['adzercms' => ['bundle' => 'Acme\\Shop\\ShopBundle', 'categories' => $categories]],
        ];
    }
}
