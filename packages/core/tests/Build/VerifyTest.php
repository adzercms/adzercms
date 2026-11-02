<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Build;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class VerifyTest extends TestCase
{
    private const PHP = __DIR__.'/Fixture/php';
    private const PHPUNIT = __DIR__.'/Fixture/phpunit';
    private const LOCK = '{"packages": []}';
    private const SUITES = '<phpunit><testsuites><testsuite name="First"/><testsuite name="Second"/></testsuites></phpunit>';
    private const PATCHED = 'acme/widget';

    private string $root;

    protected function setUp(): void
    {
        $root = sys_get_temp_dir().'/adzercms-verify-'.bin2hex(random_bytes(4));
        $files = new Filesystem();
        $files->mkdir($root.'/var');
        $this->root = realpath($root) ?: self::fail('the instance root is not made');
        $files->symlink(self::PHP, $this->root.'/bin/php');
        $files->symlink(self::PHPUNIT, $this->root.'/bin/phpunit');
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
    #[DataProvider('checks')]
    public function testRefreshInstallsChangedLockOnlyAfterCheckOfTreeCopy(?string $refusal): void
    {
        $calls = $this->refresh($refusal);

        self::assertStringContainsString('install --working-dir='.$this->root.'/var/verify --no-scripts', $calls[0]);
        self::assertSame('copy bin/console importmap:install --no-interaction --env=test', $calls[1], 'the copy takes the importmap packages of the test environment');
        self::assertSame(
            ['./.env.test.local', './bin', './bin/php', './bin/phpunit', './composer.lock', './phpunit.dist.xml', './public', './src', './src/Kernel.php', './var', './var/tests.lock', './vendor', './vendor/bin', './vendor/bin/adzercms-check'],
            file($this->root.'/copied', \FILE_IGNORE_NEW_LINES),
            'the copy is the tree without its environment, state and built static',
        );
        self::assertSame(
            null === $refusal ? ['copy phpunit --colors=never --testsuite First', 'copy phpunit --colors=never --testsuite Second'] : [],
            array_values(preg_grep('/^copy phpunit /', $calls) ?: []),
            'each suite of the copy runs in its own process after the static checks',
        );
        self::assertSame(null === $refusal, [] !== preg_grep('/ install --no-interaction /', $calls), 'the live tree installs only a checked lock');
    }

    #[Group('ACMS-CORE-1022')]
    #[DataProvider('checks')]
    public function testRefreshReinstallsPackageOfChangedPatchOnlyAfterCheckOfTreeCopyWithIt(?string $refusal): void
    {
        $files = new Filesystem();
        $files->dumpFile($this->root.'/patches.lock.json', json_encode(['patches' => [self::PATCHED => [['url' => 'patches/widget.patch']]]], \JSON_THROW_ON_ERROR));
        $files->mkdir($this->root.'/vendor/'.self::PATCHED);
        $files->dumpFile($this->root.'/var/composer.installed', hash('sha256', self::LOCK)."\n");

        $calls = $this->refresh($refusal);
        $reinstalled = static fn (string $place): int => \count(preg_grep('#reinstall '.preg_quote($place, '#').'--no-interaction --no-progress '.self::PATCHED.'$#', $calls) ?: []);

        self::assertSame(1, $reinstalled('--working-dir='.$this->root.'/var/verify '), 'the copy reinstalls the package of the changed patch for its check');
        self::assertSame(null === $refusal ? 1 : 0, $reinstalled(''), 'the live tree reinstalls it only after the check');
    }

    #[Group('ACMS-CORE-1022')]
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

    /** @return iterable<string, array{?string}> */
    public static function checks(): iterable
    {
        yield 'copy checked' => [null];
        yield 'copy refused' => ['container of the copy fails'];
    }

    /** @return list<string> */
    private function refresh(?string $refusal): array
    {
        if (null !== $refusal) {
            file_put_contents($this->root.'/var/refusal', $refusal);
        }

        $refresh = new Process([\dirname(__DIR__, 2).'/bin/adzercms-refresh'], $this->root, ['PHP_BINARY' => \PHP_BINARY]);
        $refresh->run();

        self::assertSame(null === $refusal, $refresh->isSuccessful(), $refresh->getErrorOutput());
        self::assertStringContainsString($refusal ?? '', $refresh->getOutput());
        self::assertDirectoryDoesNotExist($this->root.'/var/verify');

        return file($this->root.'/calls', \FILE_IGNORE_NEW_LINES) ?: [];
    }
}
