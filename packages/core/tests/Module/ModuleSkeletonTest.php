<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Module;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class ModuleSkeletonTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/module-skeleton-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    /** @param list<string> $answers */
    #[Group('ACMS-US-0039')]
    #[Group('ACMS-CORE-0959')]
    #[DataProvider('answers')]
    public function testCreatedProjectIsModulePackageOfAuthorAnswers(array $answers, string $namespace, string $bundle, string $key): void
    {
        $composer = (new ExecutableFinder())->find('composer') ?? self::fail('composer is not in PATH');
        $repository = json_encode(['type' => 'path', 'url' => \dirname(__DIR__, 3).'/module-skeleton', 'options' => ['symlink' => false]], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        (new Process([\PHP_BINARY, $composer, 'create-project', 'adzercms/module-skeleton', $this->directory, '--repository='.$repository, '--stability=dev', '--no-install', '--no-scripts', '--no-interaction']))->mustRun();
        (new Process([\PHP_BINARY, 'setup.php'], $this->directory, input: implode("\n", $answers)."\n"))->mustRun();

        $prefix = strtolower($key);
        $files = array_map(static fn (SplFileInfo $file): string => strtr($file->getRelativePathname(), '\\', '/'), iterator_to_array((new Finder())->files()->ignoreDotFiles(false)->in($this->directory)->sortByName(), false));
        self::assertSame(['.github/workflows/ci.yml', '.gitignore', 'composer.json', "src/{$bundle}.php", "tests/{$key}Test.php", 'translations/messages+intl-icu.en.xlf', 'translations/messages+intl-icu.ru.xlf'], $files);

        $manifest = json_decode((string) file_get_contents($this->directory.'/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);
        self::assertSame(['name', 'type', 'license', 'require', 'autoload', 'extra'], array_keys($manifest));
        self::assertSame(['adzercms-module', ['psr-4' => [$namespace.'\\' => 'src/']], ['adzercms' => ['bundle' => $namespace.'\\'.$bundle]]], [$manifest['type'], $manifest['autoload'], $manifest['extra']]);

        foreach (["src/{$bundle}.php" => ["namespace {$namespace};", "final class {$bundle} extends Module"], "tests/{$key}Test.php" => ["namespace {$namespace}\\Tests;", "final class {$key}Test", "'{$key}'", "'{$prefix}.name'"]] as $file => $fragments) {
            (new Process([\PHP_BINARY, '-l', $file], $this->directory))->mustRun();
            foreach ($fragments as $fragment) {
                self::assertStringContainsString($fragment, (string) file_get_contents($this->directory.'/'.$file));
            }
        }
        foreach (['en', 'ru'] as $language) {
            self::assertStringContainsString("<unit id=\"{$prefix}.name\">", (string) file_get_contents($this->directory."/translations/messages+intl-icu.{$language}.xlf"));
        }
    }

    /** @return iterable<string, array{list<string>, string, string, string}> */
    public static function answers(): iterable
    {
        yield 'defaults from the package name' => [['acme/sms-pilot', '', ''], 'Acme\SmsPilot', 'SmsPilotBundle', 'SmsPilot'];
        yield 'own namespace and bundle after a wrong name' => [['Acme/Pigeon', 'acme/pigeon', 'Acme\Post\Pigeon', 'CarrierBundle'], 'Acme\Post\Pigeon', 'CarrierBundle', 'Carrier'];
    }
}
