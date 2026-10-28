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

    private string $root;

    protected function setUp(): void
    {
        $root = sys_get_temp_dir().'/adzercms-verify-'.bin2hex(random_bytes(4));
        $files = new Filesystem();
        $files->mkdir($root.'/var');
        $this->root = realpath($root) ?: self::fail('the instance root is not made');
        $files->symlink(self::PHP, $this->root.'/bin/php');
        $files->dumpFile($this->root.'/.env.local', '');
        $files->dumpFile($this->root.'/.env.test.local', '');
        $files->dumpFile($this->root.'/src/Kernel.php', '');
        $files->dumpFile($this->root.'/public/assets/app.js', '');
        $files->dumpFile($this->root.'/composer.lock', '{"packages": []}');
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
        if (null !== $refusal) {
            file_put_contents($this->root.'/var/refusal', $refusal);
        }

        $refresh = new Process([\dirname(__DIR__, 2).'/bin/adzercms-refresh'], $this->root);
        $refresh->run();
        $calls = file($this->root.'/calls', \FILE_IGNORE_NEW_LINES) ?: [];

        self::assertSame(null === $refusal, $refresh->isSuccessful(), $refresh->getErrorOutput());
        self::assertStringContainsString('install --working-dir='.$this->root.'/var/verify --no-scripts', $calls[0]);
        self::assertSame(
            ['./.env.test.local', './bin', './bin/php', './composer.lock', './public', './src', './src/Kernel.php', './vendor', './vendor/bin', './vendor/bin/adzercms-check'],
            file($this->root.'/copied', \FILE_IGNORE_NEW_LINES),
            'the copy is the tree without its environment, state and built static',
        );
        self::assertSame(null === $refusal, \count($calls) > 1 && str_contains($calls[1], 'install --no-interaction'), 'the live tree installs only a checked lock');
        self::assertStringContainsString($refusal ?? '', $refresh->getOutput());
        self::assertDirectoryDoesNotExist($this->root.'/var/verify');
    }

    /** @return iterable<string, array{?string}> */
    public static function checks(): iterable
    {
        yield 'copy checked' => [null];
        yield 'copy refused' => ['container of the copy fails'];
    }
}
