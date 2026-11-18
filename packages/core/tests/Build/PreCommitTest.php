<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Build;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class PreCommitTest extends TestCase
{
    private const SCRIPT = __DIR__.'/../../bin/adzercms-pre-commit';
    private const MANIFEST = 'modules/design/composer.json';

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/adzercms-pre-commit-'.bin2hex(random_bytes(4));
        $files = new Filesystem();
        $files->dumpFile($this->root.'/composer.json', json_encode([
            'repositories' => [['type' => 'path', 'url' => 'modules/*', 'options' => ['symlink' => true]], ['packagist.org' => false]],
            'require' => ['acme/design' => '*'],
            'minimum-stability' => 'dev',
        ], \JSON_THROW_ON_ERROR));
        $files->dumpFile($this->root.'/'.self::MANIFEST, '{"name": "acme/design"}');
        $files->symlink(__DIR__.'/Fixture/php', $this->root.'/bin/php');
        $this->git('init', '--quiet');
        $this->lock();
        $this->git('add', '.');
        $this->commit();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    #[Group('ACMS-CORE-1037')]
    #[DataProvider('locks')]
    public function testManifestOfAPathPackageCommitsWithTheLockCarryingIt(bool $locked, bool $passes): void
    {
        (new Filesystem())->dumpFile($this->root.'/'.self::MANIFEST, '{"name": "acme/design", "description": "Design"}');
        if ($locked) {
            $this->lock();
            $this->git('add', 'composer.lock');
            $this->commit();
        }
        $this->git('add', self::MANIFEST);

        $this->assertGate($passes, self::MANIFEST);
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function locks(): iterable
    {
        yield 'the lock carries the staged manifest' => [true, true];
        yield 'the lock lags behind the staged manifest' => [false, false];
    }

    #[Group('ACMS-CORE-1104')]
    #[DataProvider('migrations')]
    public function testMigrationChangesOnlyTheTablesOfItsModule(bool $neighbour, string $table, bool $passes): void
    {
        $files = new Filesystem();
        $migration = 'modules/design/migrations/Version20260101000000.php';
        $files->dumpFile($this->root.'/'.$migration, "<?php\n\nnamespace Acme\\Design\\Migrations;\n\nuse Doctrine\\DBAL\\Schema\\Schema;\nuse Doctrine\\Migrations\\AbstractMigration;\n\n"
            ."final class Version20260101000000 extends AbstractMigration\n{\n    public function up(Schema \$schema): void\n    {\n        \$this->addSql('UPDATE {$table} SET name = name');\n    }\n}\n");
        $files->symlink(__DIR__.'/Fixture/php', $this->root.'/vendor/bin/php-cs-fixer');
        if ($neighbour) {
            $files->dumpFile($this->root.'/modules/catalog/src/Entity/Product.php', "<?php\n\n#[ORM\\Table(name: 'products')]\nfinal class Product\n{\n}\n");
            $this->git('add', 'modules/catalog');
        }
        $this->git('add', $migration);

        $this->assertGate($passes, $migration);
    }

    /** @return iterable<string, array{bool, string, bool}> */
    public static function migrations(): iterable
    {
        yield 'a table of another module' => [true, 'products', false];
        yield 'a table of its own beside another module' => [true, 'banners', true];
        yield 'a tree without entities of other modules' => [false, 'banners', true];
    }

    private function assertGate(bool $passes, string $refused): void
    {
        $gate = new Process(['sh', self::SCRIPT], $this->root, ['PHP_BINARY' => \PHP_BINARY]);
        $gate->run();

        self::assertSame([$passes, !$passes], [$gate->isSuccessful(), str_contains($gate->getOutput(), $refused)], $gate->getOutput());
    }

    private function lock(): void
    {
        (new Process(['composer', 'update', '--no-install', '--no-interaction', '--quiet'], $this->root, ['COMPOSER_HOME' => $this->root.'/var/composer']))->mustRun();
    }

    private function commit(): void
    {
        $this->git('-c', 'user.name=test', '-c', 'user.email=test@example.com', 'commit', '--quiet', '-m', 'state');
    }

    private function git(string ...$arguments): void
    {
        (new Process(['git', ...$arguments], $this->root))->mustRun();
    }
}
