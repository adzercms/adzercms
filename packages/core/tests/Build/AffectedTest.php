<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Build;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class AffectedTest extends TestCase
{
    private const SCRIPT = __DIR__.'/../../bin/adzercms-affected';
    private const SUITES = '<phpunit><testsuites>'
        .'<testsuite name="Base"><directory>packages/base/tests</directory></testsuite>'
        .'<testsuite name="Shop"><directory>packages/shop/tests</directory></testsuite>'
        .'<testsuite name="Theme"><directory>packages/theme/tests</directory></testsuite>'
        .'<testsuite name="Cart"><directory>modules/cart/tests</directory></testsuite>'
        .'</testsuites></phpunit>';
    private const PACKAGES = [
        'packages/base' => ['name' => 'acme/base'],
        'packages/shop' => ['name' => 'acme/shop', 'require' => ['acme/base' => '*']],
        'packages/theme' => ['name' => 'acme/theme'],
        'modules/cart' => ['name' => 'acme/cart', 'require-dev' => ['acme/shop' => '*']],
    ];

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/adzercms-affected-'.bin2hex(random_bytes(4));
        $files = new Filesystem();
        $files->dumpFile($this->root.'/phpunit.dist.xml', self::SUITES);
        $files->dumpFile($this->root.'/composer.lock', '{}');
        $files->dumpFile($this->root.'/docs/guide.md', '');
        foreach (self::PACKAGES as $place => $manifest) {
            $files->dumpFile($this->root.'/'.$place.'/composer.json', json_encode($manifest, \JSON_THROW_ON_ERROR));
            $files->dumpFile($this->root.'/'.$place.'/src/Code.php', '<?php');
        }
        $this->git('init', '--quiet');
        $this->git('add', '.');
        $this->git('-c', 'user.name=test', '-c', 'user.email=test@example.com', 'commit', '--quiet', '-m', 'base');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    /** @param list<string> $suites */
    #[Group('ACMS-CORE-1075')]
    #[DataProvider('changes')]
    public function testSuitesFollowTheChangedPackagesAndTheirConsumers(string $path, string $base, array $suites): void
    {
        (new Filesystem())->appendToFile($this->root.'/'.$path, "\n");
        $affected = new Process([\PHP_BINARY, self::SCRIPT, $base], $this->root);
        $affected->mustRun();

        self::assertSame($suites, array_values(array_filter(explode("\n", $affected->getOutput()))));
    }

    /** @return iterable<string, array{string, string, list<string>}> */
    public static function changes(): iterable
    {
        yield 'a package takes its consumers and theirs through development requirements' => ['packages/base/src/Code.php', 'HEAD', ['Base', 'Shop', 'Cart']];
        yield 'a consumer leaves the package it requires' => ['packages/shop/src/Code.php', 'HEAD', ['Shop', 'Cart']];
        yield 'documentation takes no suite' => ['docs/guide.md', 'HEAD', []];
        yield 'the tree outside the packages takes every suite' => ['composer.lock', 'HEAD', ['Base', 'Shop', 'Theme', 'Cart']];
        yield 'an unknown base takes every suite' => ['packages/theme/src/Code.php', '0000000000000000000000000000000000000000', ['Base', 'Shop', 'Theme', 'Cart']];
    }

    private function git(string ...$arguments): void
    {
        (new Process(['git', ...$arguments], $this->root))->mustRun();
    }
}
