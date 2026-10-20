<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Build;

use AdzerCMS\Core\Build\Builds;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class BuildsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/adzercms-builds-'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->root);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    #[Group('ACMS-CORE-0045')]
    public function testCacheWithoutBuildPointerStaysFlatAndUntouched(): void
    {
        $builds = new Builds($this->root);
        touch($this->root.'/App_KernelProdContainer.php');

        self::assertNull($builds->hold(null));
        self::assertSame([], $builds->prune());
        self::assertFileExists($this->root.'/App_KernelProdContainer.php');
    }

    #[Group('ACMS-CORE-0045')]
    public function testRunningProcessKeepsItsBuildWhileFreeOnesAreRemoved(): void
    {
        $builds = new Builds($this->root);
        touch($this->root.'/App_KernelProdContainer.php');
        (new Filesystem())->dumpFile($this->root.'/ContainerAbc/getRouterService.php', '<?php');

        $builds->switchTo('b1');
        (new Filesystem())->mkdir($this->root.'/b1');
        $running = $builds->hold(null);
        self::assertSame('b1', $running?->name);

        foreach (['b2', 'b3'] as $older) {
            (new Filesystem())->mkdir($this->root.'/'.$older);
        }
        $job = $this->holding('b2');

        $builds->switchTo('b4');
        (new Filesystem())->mkdir($this->root.'/b4');

        self::assertSame(['App_KernelProdContainer.php', 'ContainerAbc', 'b3'], $builds->prune());
        self::assertDirectoryExists($this->root.'/b1');
        self::assertDirectoryExists($this->root.'/b2');
        self::assertDirectoryExists($this->root.'/b4');

        $job->stop(0);

        self::assertSame(['b2'], $builds->prune());
        self::assertSame('b4', $builds->hold(null)?->name);

        $running->release();

        self::assertSame(['b1'], $builds->prune());
    }

    #[Group('ACMS-CORE-0939')]
    public function testFormerModuleSetGoesBehindLiveOneOnceItsBuildsAreFree(): void
    {
        $live = $this->set('prod_live', 'b2', 0);
        $former = $this->set('prod_former', 'b1', -60);
        $later = $this->set('prod_later', 'b3', 60);

        self::assertSame([], $later->retire($live), 'a set switched after the live one stays');

        $job = $this->holding('prod_former/b1');

        self::assertSame([], $former->retire($live));
        self::assertDirectoryExists($this->root.'/prod_former/b1');

        $job->stop(0);

        self::assertSame(['b1'], $former->retire($live));
        self::assertDirectoryDoesNotExist($this->root.'/prod_former');
        self::assertDirectoryExists($this->root.'/prod_live/b2');
    }

    #[Group('ACMS-CORE-0045')]
    public function testBuildNameIsChecked(): void
    {
        $this->expectExceptionObject(new \LogicException('cache build name "../prod" is invalid'));

        (new Builds($this->root))->switchTo('../prod');
    }

    private function set(string $root, string $build, int $switched): Builds
    {
        $builds = new Builds($this->root.'/'.$root);
        $builds->switchTo($build);
        (new Filesystem())->mkdir($this->root.'/'.$root.'/'.$build);
        touch($this->root.'/'.$root.'/build', time() + $switched);

        return $builds;
    }

    private function holding(string $build): Process
    {
        $process = new Process([\PHP_BINARY, '-r', sprintf(
            '$l = fopen(%s, "c"); flock($l, LOCK_SH); echo "held\n"; sleep(60);',
            var_export($this->root.'/'.$build.'.lock', true),
        )]);
        $process->start();
        $process->waitUntil(static fn (string $type, string $output): bool => str_contains($output, 'held'));

        return $process;
    }
}
