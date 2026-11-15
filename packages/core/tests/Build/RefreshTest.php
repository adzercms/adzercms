<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Build;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class RefreshTest extends TestCase
{
    private const SCRIPTS = __DIR__.'/../../bin/';
    private const FIXTURE = __DIR__.'/Fixture/';
    private const LOCK = '{"packages": []}';
    private const SUITES = '<phpunit><testsuites><testsuite name="First"/><testsuite name="Second"/></testsuites></phpunit>';
    private const PATCHED = 'acme/widget';
    private const INSTALLED = '/vendor/installed';

    private string $root;

    protected function setUp(): void
    {
        $root = sys_get_temp_dir().'/adzercms-refresh-'.bin2hex(random_bytes(4));
        $files = new Filesystem();
        $files->mkdir($root.'/var');
        $this->root = realpath($root) ?: self::fail('the instance root is not made');
        $files->symlink(self::FIXTURE.'php', $this->root.'/bin/php');
        $files->symlink(self::FIXTURE.'phpunit', $this->root.'/bin/phpunit');
        foreach (['adzercms-adopt' => self::SCRIPTS, 'adzercms-build' => self::SCRIPTS, 'adzercms-suites' => self::SCRIPTS, 'adzercms-verify' => self::FIXTURE.'verify', 'adzercms-check' => self::FIXTURE.'check'] as $script => $target) {
            $files->symlink(str_ends_with($target, '/') ? $target.$script : $target, $this->root.'/vendor/bin/'.$script);
        }
        $files->dumpFile($this->root.'/phpunit.dist.xml', self::SUITES);
        $files->dumpFile($this->root.'/.env.local', '');
        $files->dumpFile($this->root.'/.env.test.local', '');
        $files->dumpFile($this->root.'/src/Kernel.php', '');
        $files->dumpFile($this->root.'/public/assets/app.js', '');
        $files->dumpFile($this->root.'/composer.lock', self::LOCK);
        $files->dumpFile($this->root.'/var/composer.installed', hash('sha256', '{}')."\n");
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    #[Group('ACMS-CORE-1022')]
    #[Group('ACMS-CORE-1031')]
    #[Group('ACMS-CORE-1176')]
    #[DataProvider('checks')]
    public function testChangedLockReachesLiveTreeOnlyAfterCheckOfCopy(?string $refusal): void
    {
        $calls = $this->refresh($refusal);

        self::assertStringContainsString('install --working-dir='.$this->root.'/var/next --no-interaction --no-progress --no-scripts', $calls[0]);
        self::assertSame('copy verify '.$this->root.'/var/next', $calls[1], 'the copy is checked by the check script of its own core');
        self::assertSame(
            ['copy bin/console cache:warmup --env=test', 'copy bin/console importmap:install --no-interaction --env=test'],
            \array_slice($calls, 2, 2),
            'the copy warms its cache and takes the importmap packages of the test environment',
        );
        self::assertSame(
            ['./.env.local', './.env.test.local', './bin', './bin/php', './bin/phpunit', './composer.lock', './phpunit.dist.xml', './public', './src', './src/Kernel.php', './var', './var/tests.lock', './vendor', './vendor/bin', './vendor/bin/adzercms-adopt', './vendor/bin/adzercms-build', './vendor/bin/adzercms-check', './vendor/bin/adzercms-suites', './vendor/bin/adzercms-verify', './vendor/installed'],
            file($this->root.'/copied', \FILE_IGNORE_NEW_LINES),
            'the copy is the tree with its environment, without its state and built static',
        );
        self::assertSame(
            null === $refusal ? ['copy phpunit --colors=never --testsuite First', 'copy phpunit --colors=never --testsuite Second'] : [],
            array_values(preg_grep('/^copy phpunit /', $calls) ?: []),
            'each suite of the copy runs in its own process after the static checks',
        );
        self::assertSame(null === $refusal, is_file($this->root.self::INSTALLED), 'the live tree takes the dependencies of the copy only after its check');
        self::assertSame([], null === $refusal ? [] : array_values(preg_grep('/^bin\/console /', $calls) ?: []), 'a refused copy leaves the live tree untouched');
    }

    #[Group('ACMS-CORE-1022')]
    #[DataProvider('checks')]
    public function testCopyReinstallsPackageOfChangedPatchForItsCheck(?string $refusal): void
    {
        $files = new Filesystem();
        $files->dumpFile($this->root.'/patches.lock.json', json_encode(['patches' => [self::PATCHED => [['url' => 'patches/widget.patch']]]], \JSON_THROW_ON_ERROR));
        $files->mkdir($this->root.'/vendor/'.self::PATCHED);
        $files->dumpFile($this->root.'/var/composer.installed', hash('sha256', self::LOCK)."\n");

        $calls = $this->refresh($refusal);

        self::assertSame(
            [$this->root.'/var/next'],
            array_values(array_map(static fn (string $call): string => preg_replace('#.* reinstall --working-dir=(\S+) --no-interaction --no-progress '.preg_quote(self::PATCHED, '#').'$#', '$1', $call) ?? '', preg_grep('/ reinstall /', $calls) ?: [])),
            'only the copy reinstalls the package of the changed patch',
        );
        self::assertSame(null === $refusal, is_file($this->root.self::INSTALLED), 'the live tree takes it with the vendor of the copy after the check');
        self::assertSame(null === $refusal, is_file($this->root.'/var/patches.installed'), 'the live tree marks the patches installed with the vendor');
    }

    #[Group('ACMS-TEST-0023')]
    public function testSuitesOfCopyShareLockOfLiveTreeRuns(): void
    {
        $run = fopen($this->root.'/var/tests.lock', 'c') ?: self::fail('the run of the live tree takes no lock');
        flock($run, \LOCK_EX);

        try {
            $calls = $this->refresh(null);
        } finally {
            fclose($run);
        }

        self::assertContains('copy lock busy', $calls, 'the suites of the copy run past the lock of the live tree');
    }

    #[Group('ACMS-CORE-1130')]
    #[Group('ACMS-CORE-1177')]
    #[DataProvider('locks')]
    public function testBuildOfCopyMovesIntoLiveTreeBeforeMigrationsAndSwitch(bool $changed): void
    {
        if (!$changed) {
            (new Filesystem())->dumpFile($this->root.'/var/composer.installed', hash('sha256', self::LOCK)."\n");
        }
        $vendor = fileinode($this->root.'/vendor');

        $calls = $this->refresh(null);
        $build = trim((string) file_get_contents($this->root.'/public/assets/set/manifest.json'));

        self::assertSame(
            ['copy bin/console cache:warmup', 'copy bin/console importmap:install --no-interaction', 'copy bin/console asset-map:compile --no-interaction', 'bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration', 'bin/console adzercms:cache', 'bin/console doctrine:schema:validate'],
            array_values(array_filter($calls, static fn (string $call): bool => !str_contains($call, '--env=test') && 1 === preg_match('/^(copy )?bin\/console /', $call))),
            'the copy builds the cache and the static, the live tree migrates and switches on them',
        );
        self::assertDirectoryExists($this->root.'/var/cache/prod_set/'.$build, 'the build of the copy moves into the builds of the live tree');
        self::assertFileExists($this->root.'/assets/vendor/library.js', 'the libraries of the copy reach a live tree without its own');
        self::assertSame([$changed, $changed], [is_file($this->root.self::INSTALLED), $vendor !== fileinode($this->root.'/vendor')], 'the vendor of the copy replaces the live one only on a changed lock');
        self::assertSame(hash('sha256', self::LOCK)."\n", file_get_contents($this->root.'/var/composer.installed'));
    }

    /** @return iterable<string, array{?string}> */
    public static function checks(): iterable
    {
        yield 'copy checked' => [null];
        yield 'copy refused' => ['container of the copy fails'];
    }

    /** @return iterable<string, array{bool}> */
    public static function locks(): iterable
    {
        yield 'lock changed' => [true];
        yield 'lock kept' => [false];
    }

    /** @return list<string> */
    private function refresh(?string $refusal): array
    {
        if (null !== $refusal) {
            file_put_contents($this->root.'/var/refusal', $refusal);
        }

        $refresh = new Process([self::SCRIPTS.'adzercms-refresh'], $this->root, ['PHP_BINARY' => \PHP_BINARY]);
        $refresh->run();

        self::assertSame(null === $refusal, $refresh->isSuccessful(), $refresh->getErrorOutput());
        self::assertStringContainsString($refusal ?? '', $refresh->getOutput());
        self::assertDirectoryDoesNotExist($this->root.'/var/next');

        return file($this->root.'/calls', \FILE_IGNORE_NEW_LINES) ?: [];
    }
}
