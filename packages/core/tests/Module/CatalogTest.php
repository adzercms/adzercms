<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module;

use AdzerCMS\Contracts\Module\Category;
use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Core\Composer\Artifacts;
use AdzerCMS\Core\Composer\Repositories;
use AdzerCMS\Core\Composer\Source;
use AdzerCMS\Core\Module\Catalog;
use AdzerCMS\Core\Module\CatalogRow;
use AdzerCMS\Core\Module\Marks;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Module\Sources;
use AdzerCMS\Core\Repository\ModuleSourceRepository;
use AdzerCMS\Core\Tests\SchemaTestCase;
use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;

final class CatalogTest extends SchemaTestCase
{
    use CatalogAnswers;

    private const SEARCH = 'https://packagist.example.test/search.json?q=%query%&type=%type%';
    private const PAGE = 'https://packagist.example.test/search.json?q=&type='.Registry::TYPE;
    private const OWN = 'https://repo.acme.test';

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

    #[Group('ACMS-CORE-0960')]
    #[Group('ACMS-CORE-0934')]
    public function testMarkGoesToVersionWhoseCommitPassedAndOutlivesUnavailableRegistry(): void
    {
        self::mockTime('2026-10-20 12:00');
        $this->listed(['acme/till', 'acme/shop']);
        self::assertNull(self::service(Artifacts::class)->upload($this->archive(['composer.json' => json_encode(self::manifest('acme/kept', '1.0.0'), \JSON_THROW_ON_ERROR)])));
        $this->mark('acme/shop', self::commit('acme/shop', '1.0.0'));
        $this->mark('acme/till', self::commit('acme/till', '1.1.0'));
        $this->mark('acme/till', self::commit('acme/till', '1.2.0'), 'phpstan');
        $this->mark('acme/kept', self::commit('acme/kept', '1.0.0'));
        $this->mark('adzercms/admin', InstalledVersions::getReference('adzercms/admin') ?? self::fail('the installed module has no commit'));
        $marked = ['acme/kept' => [false, false], 'acme/shop' => [true, false], 'acme/till' => [false, false], 'adzercms/admin' => [false, true]];

        self::assertSame(Command::SUCCESS, self::command('adzercms:modules')->execute([]));
        self::assertSame($marked, self::marks());

        $this->registry = null;
        self::mockTime('2026-10-21 12:00');
        $job = self::command('adzercms:modules');

        self::assertSame(Command::FAILURE, $job->execute([]));
        self::assertStringContainsString(Marks::REGISTRY, $job->getDisplay());
        self::assertSame($marked, self::marks(), 'an unavailable registry keeps the held marks');
    }

    #[Group('ACMS-CORE-0946')]
    #[Group('ACMS-CORE-0944')]
    #[Group('ACMS-CORE-0934')]
    #[DataProvider('grants')]
    public function testOwnSourceGoesBeforeBaseRepositoriesWithItsCredentialsOnItsHostOnly(string $login, string $authorization): void
    {
        self::mockTime('2026-10-20 12:00');
        $this->listed(['acme/shop']);
        self::command('adzercms:modules')->execute([]);
        $this->host(self::OWN, 'acme/shop', '3.0.0');
        $this->host(self::OWN, 'acme/vault', '1.0.0');
        $sources = self::service(Sources::class);
        self::assertNull($sources->add(self::OWN.'/'));
        $sources->grant(self::service(ModuleSourceRepository::class)->findOneBy(['url' => self::OWN]) ?? self::fail('the source is not kept'), $login, 'secret');

        self::assertSame(Command::SUCCESS, self::command('adzercms:modules')->execute([]), 'a change of sources retakes the snapshot younger than a day');
        self::assertSame([
            'acme/shop' => [CoreKind::Feature, '3.0.0', null, self::OWN, [Category::Other]],
            'acme/vault' => [CoreKind::Feature, '1.0.0', null, self::OWN, [Category::Other]],
        ], self::offered());
        self::assertSame([Source::origin(Marks::REGISTRY) => null, Source::origin(Repositories::PACKAGIST) => null, Source::origin(self::OWN) => $authorization], $this->authorized);
    }

    /** @return iterable<string, array{string, string}> */
    public static function grants(): iterable
    {
        yield 'token' => ['', 'Authorization: Bearer secret'];
        yield 'login and token' => ['acme', 'Authorization: Basic '.base64_encode('acme:secret')];
    }

    #[Group('ACMS-CORE-0946')]
    #[Group('ACMS-CORE-0934')]
    public function testZipPackageGoesBeforeRepositoriesWithItsHighestVersion(): void
    {
        $this->listed(['acme/shop']);
        foreach (['1.2.0', '1.10.0'] as $version) {
            self::assertNull(self::service(Artifacts::class)->upload($this->archive(['composer.json' => json_encode(self::manifest('acme/shop', $version), \JSON_THROW_ON_ERROR)])));
        }

        self::command('adzercms:modules')->execute([]);

        self::assertSame(['acme/shop' => [CoreKind::Feature, '1.10.0', null, Artifacts::REPOSITORY, [Category::Other]]], self::offered());
    }

    /**
     * @param array<string, string>                    $entries
     * @param array{string, array<string, mixed>}|null $refusal
     */
    #[Group('ACMS-CORE-0945')]
    #[DataProvider('archives')]
    public function testZipUploadTakesComposerManifestOfStableVersionOnce(array $entries, ?array $refusal): void
    {
        $artifacts = self::service(Artifacts::class);
        self::assertNull($artifacts->upload($this->archive(['composer.json' => json_encode(self::manifest('acme/held', '1.0.0'), \JSON_THROW_ON_ERROR)])));

        $answer = $artifacts->upload($this->archive($entries));

        self::assertSame(null === $refusal ? null : self::phrase(...$refusal), null === $answer ? null : self::phrase($answer->reason));
        self::assertSame(null === $refusal ? ['acme/held', 'acme/shop'] : ['acme/held'], array_column($artifacts->archives(), 'name'));
    }

    /** @return iterable<string, array{array<string, string>, array{string, array<string, mixed>}|null}> */
    public static function archives(): iterable
    {
        $manifest = static fn (string $name, string $version): string => json_encode(self::manifest($name, $version), \JSON_THROW_ON_ERROR);

        yield 'manifest at the root' => [['composer.json' => $manifest('acme/shop', 'v1.0.0')], null];
        yield 'manifest in the only top-level folder' => [['shop-1.0.0/composer.json' => $manifest('acme/shop', '1.0.0'), 'shop-1.0.0/src/Shop.php' => '<?php'], null];
        yield 'manifest beside a second top-level folder' => [['shop/composer.json' => $manifest('acme/shop', '1.0.0'), 'docs/README.md' => ''], ['core.module.zip.unreadable', []]];
        yield 'unstable version' => [['composer.json' => $manifest('acme/shop', '1.0.0-beta1')], ['core.module.zip.manifest', []]];
        yield 'name outside Composer rules' => [['composer.json' => $manifest('Acme/Shop', '1.0.0')], ['core.module.zip.manifest', []]];
        yield 'uploaded version' => [['composer.json' => $manifest('acme/held', '1.0')], ['core.module.zip.taken', ['package' => 'acme/held', 'version' => '1.0']]];
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

    /** @return array<string, array{bool, bool}> */
    private static function marks(): array
    {
        $marks = [];
        foreach (self::service(Catalog::class)->rows()['rows'] as $row) {
            if (str_starts_with($row->name, 'acme/') || 'adzercms/admin' === $row->name) {
                $marks[$row->name] = [$row->verified, $row->installedVerified];
            }
        }
        ksort($marks);

        return $marks;
    }
}
