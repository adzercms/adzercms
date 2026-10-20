<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module;

use AdzerCMS\Contracts\Module\Category;
use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Core\Composer\Repositories;
use AdzerCMS\Core\Module\Catalog;
use AdzerCMS\Core\Module\CatalogRow;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Tests\SchemaTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;

final class CatalogTest extends SchemaTestCase
{
    use CatalogAnswers;

    private const SEARCH = 'https://packagist.example.test/search.json?q=%query%&type=%type%';
    private const PAGE = 'https://packagist.example.test/search.json?q=&type='.Registry::TYPE;

    protected function setUp(): void
    {
        parent::setUp();
        $this->answerCatalog();
        $this->offer('acme/till', '1.2.0', provide: ['adzercms/sms-implementation' => '1.0.0'], categories: ['messaging']);
        $this->offer('acme/kit', '2.0.0', 'library');
        $this->offer('acme/shop', '1.0.0');
    }

    /** @param array<string, array<string, mixed>> $pages */
    #[Group('ACMS-CORE-0934')]
    #[Group('ACMS-CORE-0931')]
    #[DataProvider('listings')]
    public function testJobTakesModulePackagesOfRepositoryListing(bool $search, array $pages): void
    {
        $this->listed(['acme/till', 'acme/kit', 'acme/shop']);
        if ($search) {
            $this->root = ['metadata-url' => '/p2/%package%.json', 'search' => self::SEARCH];
        }
        $this->pages = $pages;

        self::assertSame(Command::SUCCESS, self::command('adzercms:modules')->execute([]));
        self::assertSame([
            'acme/shop' => [CoreKind::Feature, '1.0.0', null, Repositories::PACKAGIST, [Category::Other]],
            'acme/till' => [CoreKind::Sms, '1.2.0', null, Repositories::PACKAGIST, [Category::Messaging]],
        ], self::offered());
    }

    /** @return iterable<string, array{bool, array<string, array<string, mixed>>}> */
    public static function listings(): iterable
    {
        yield 'search pages' => [true, [
            self::PAGE => ['results' => [['name' => 'acme/till'], ['name' => 'acme/kit']], 'next' => self::PAGE.'&page=2'],
            self::PAGE.'&page=2' => ['results' => [['name' => 'acme/shop']]],
        ]];
        yield 'available packages without search' => [false, []];
    }

    #[Group('ACMS-CORE-0934')]
    #[Group('ACMS-CORE-0937')]
    public function testUnlistedPackageLeavesCatalogAndUnavailableRepositoryKeepsHeldOnes(): void
    {
        self::mockTime('2026-10-20 12:00');
        $this->listed(['acme/till', 'acme/shop']);
        self::command('adzercms:modules')->execute([]);
        $this->listed(['acme/shop']);
        self::command('adzercms:modules')->execute([]);
        self::assertSame(['acme/shop', 'acme/till'], array_keys(self::offered()), 'the snapshot younger than a day stays');

        self::mockTime('2026-10-21 12:00');
        self::command('adzercms:modules')->execute([]);
        self::assertSame(['acme/shop'], array_keys(self::offered()));

        $this->root = null;
        self::mockTime('2026-10-22 12:00');
        $job = self::command('adzercms:modules');

        self::assertSame(Command::FAILURE, $job->execute([]));
        self::assertStringContainsString(Repositories::PACKAGIST, $job->getDisplay());
        self::assertSame(['acme/shop'], array_keys(self::offered()));
    }

    #[Group('ACMS-CORE-0934')]
    public function testInstalledModuleGoesWithItsVersionBesideOfferedOne(): void
    {
        $this->listed(['adzercms/admin']);
        $this->offer('adzercms/admin', '9.0.0', provide: ['adzercms/admin-implementation' => '9.0.0']);
        self::command('adzercms:modules')->execute([]);

        $rows = self::service(Catalog::class)->rows()['rows'];
        $admin = array_values(array_filter($rows, static fn (CatalogRow $row): bool => 'adzercms/admin' === $row->name))[0] ?? self::fail('the installed module is not in the catalog');
        $theme = array_values(array_filter($rows, static fn (CatalogRow $row): bool => 'adzercms/theme' === $row->name))[0] ?? self::fail('an installed module outside the snapshot is not in the catalog');

        self::assertSame([CoreKind::Admin, '9.0.0', Repositories::PACKAGIST], [$admin->kind, $admin->version, $admin->repository]);
        self::assertNotNull($admin->installed);
        self::assertSame([CoreKind::Theme, null, null], [$theme->kind, $theme->version, $theme->repository]);
        self::assertNotNull($theme->installed);
        self::assertNotSame('', $theme->package->description);
    }

    /** @return array<string, array{mixed, ?string, ?string, ?string, list<Category>}> */
    private static function offered(): array
    {
        $offered = [];
        foreach (self::service(Catalog::class)->rows()['rows'] as $row) {
            if (str_starts_with($row->name, 'acme/')) {
                $offered[$row->name] = [$row->kind, $row->version, $row->installed, $row->repository, $row->package->categories];
            }
        }

        return $offered;
    }
}
