<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Build;

use AdzerCMS\Core\Module\Tree;
use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class HoldTest extends TestCase
{
    private const ENTRY = <<<'PHP'
        <?php
        require_once dirname(__DIR__).'/config/hold.php';
        echo "entered\n";
        $root = dirname(__DIR__);
        if ('leave' === ($argv[1] ?? null)) {
            usleep(300000);
            touch($root.'/left');
        }
        if ('spawn' === ($argv[1] ?? null)) {
            while (!is_file($root.'/closed')) {
                usleep(10000);
            }
            require $argv[2];
            echo (new Symfony\Component\Process\Process([PHP_BINARY, __FILE__]))->mustRun()->getOutput();
        }
        PHP;
    private const STEP = __DIR__.'/Fixture/step';

    private string $root;
    /** @var resource|null */
    private $hold;
    private ?Process $server = null;

    protected function setUp(): void
    {
        $root = sys_get_temp_dir().'/adzercms-hold-'.bin2hex(random_bytes(4));
        $files = new Filesystem();
        $files->mkdir($root.'/var/lock');
        $this->root = realpath($root) ?: self::fail('the instance root is not made');
        $files->copy(\dirname(__DIR__, 3).'/skeleton/config/hold.php', $this->root.'/config/hold.php');
        $files->dumpFile($this->root.'/public/index.php', self::ENTRY);
        $files->dumpFile($this->root.'/bin/console', self::ENTRY);
    }

    protected function tearDown(): void
    {
        $this->server?->stop(0);
        $this->open();
        (new Filesystem())->remove($this->root);
    }

    #[Group('ACMS-CORE-0964')]
    #[Group('ACMS-CORE-0966')]
    public function testClosedEntryAnswersRequestWith503AndHoldsJobUntilOpened(): void
    {
        $address = $this->serve();
        $this->close();

        self::assertSame([503, true, ''], $this->get($address));
        $job = $this->console();
        $job->start();
        usleep(300000);
        self::assertSame([true, ''], [$job->isRunning(), $job->getOutput()], 'a job waits for the end of the hold');

        $this->open();
        $job->wait();

        self::assertSame("entered\n", $job->getOutput());
        self::assertSame([200, false, "entered\n"], $this->get($address));
    }

    #[Group('ACMS-CORE-0965')]
    #[Group('ACMS-CORE-0967')]
    #[DataProvider('holders')]
    public function testHolderGoesOnOnceEnteredProcessesLeaveAndCoversItsSteps(bool $job): void
    {
        $files = new Filesystem();
        $files->symlink(self::STEP, $this->root.'/bin/php');
        $files->dumpFile($this->root.'/.env.local', '');
        $files->dumpFile($this->root.'/composer.lock', '{}');
        $files->dumpFile($this->root.'/var/composer.installed', hash('sha256', '{}')."\n");
        $entered = $this->console('leave');
        $entered->start();
        $entered->waitUntil(static fn (string $type, string $output): bool => str_contains($output, 'entered'));

        if ($job) {
            self::assertNull((new Tree($this->root, self::STEP))->refresh([]));
        } else {
            (new Process([\dirname(__DIR__, 2).'/bin/adzercms-refresh'], $this->root))->mustRun();
        }

        $steps = file($this->root.'/steps', \FILE_IGNORE_NEW_LINES) ?: [];
        self::assertNotSame([], $steps);
        self::assertSame([$this->root.' left'], array_values(array_unique($steps)), 'steps go after the entered process left, covered by the hold');
    }

    /** @return iterable<string, array{bool}> */
    public static function holders(): iterable
    {
        yield 'refresh script' => [false];
        yield 'modules job' => [true];
    }

    #[Group('ACMS-CORE-0967')]
    public function testDescendantsOfEnteredProcessAndCoveredConsolePassClosedEntry(): void
    {
        $parent = $this->console('spawn', \dirname((string) (new \ReflectionClass(ClassLoader::class))->getFileName(), 2).'/autoload.php');
        $parent->start();
        $parent->waitUntil(static fn (string $type, string $output): bool => str_contains($output, 'entered'));
        $this->close();
        touch($this->root.'/closed');
        $parent->wait();

        self::assertSame("entered\nentered\n", $parent->getOutput(), 'a child of an entered process enters');
        self::assertSame("entered\n", (new Process([\PHP_BINARY, $this->root.'/bin/console'], env: ['ADZERCMS_ENTRY' => $this->root], timeout: 10))->mustRun()->getOutput());
    }

    private function console(string ...$arguments): Process
    {
        return new Process([\PHP_BINARY, $this->root.'/bin/console', ...$arguments], timeout: 10);
    }

    private function serve(): string
    {
        $this->server = new Process([\PHP_BINARY, '-S', '127.0.0.1:0', $this->root.'/public/index.php'], $this->root);
        $this->server->start();
        $address = '';
        $this->server->waitUntil(static function (string $type, string $output) use (&$address): bool {
            $address = preg_match('~http://127\.0\.0\.1:\d+~', $output, $match) ? $match[0] : '';

            return '' !== $address;
        });

        return $address;
    }

    /** @return array{int, bool, string} */
    private function get(string $address): array
    {
        $body = (string) file_get_contents($address.'/', false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]));
        $headers = http_get_last_response_headers() ?? [];

        return [(int) (explode(' ', $headers[0] ?? '')[1] ?? 0), [] !== preg_grep('/^Retry-After: \d+$/i', $headers), $body];
    }

    private function close(): void
    {
        $this->hold = fopen($this->root.'/var/lock/refresh', 'ce') ?: self::fail('the hold lock is not opened');
        flock($this->hold, \LOCK_EX);
    }

    private function open(): void
    {
        null === $this->hold || fclose($this->hold);
        $this->hold = null;
    }
}
