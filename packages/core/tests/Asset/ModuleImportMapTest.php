<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Asset;

use AdzerCMS\Core\Asset\ModuleImportMap;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntries;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\RemotePackageStorage;
use Symfony\Component\Filesystem\Filesystem;

final class ModuleImportMapTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/adzercms-importmap-'.bin2hex(random_bytes(4));
        new Filesystem()->mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->dir);
    }

    #[Group('ACMS-UI-0070')]
    public function testInstanceMapTakesModuleDeclarationsAndWritesOnlyItsOwn(): void
    {
        $map = $this->map(['own' => ['version' => '1.0.0']], ['skin' => ['skin' => ['path' => 'bundles/skin/app.js', 'entrypoint' => true], 'lib' => ['version' => '2.0.0']]]);

        self::assertSame(['lib', 'own', 'skin'], self::names($map->getEntries()));

        $map->writeEntries($map->getEntries());
        self::assertSame(['own'], array_keys(include $this->dir.'/importmap.php'));
    }

    #[Group('ACMS-UI-0070')]
    public function testOneImportDeclaredDifferentlyIsRefused(): void
    {
        $map = $this->map([], ['one' => ['lib' => ['version' => '1.0.0']], 'two' => ['lib' => ['version' => '2.0.0']]]);

        try {
            $map->getEntries();
            self::fail('one import took two declarations');
        } catch (\LogicException $refusal) {
            self::assertStringContainsString('one and two', $refusal->getMessage());
        }
    }

    /**
     * @param array<string, array<string, mixed>>                $instance
     * @param array<string, array<string, array<string, mixed>>> $modules
     */
    private function map(array $instance, array $modules): ModuleImportMap
    {
        $files = [];
        foreach (['' => $instance] + $modules as $name => $config) {
            $file = $this->dir.'/'.('' === $name ? 'importmap' : $name).'.php';
            file_put_contents($file, '<?php return '.var_export($config, true).';');
            if ('' !== $name) {
                $files[$name] = $file;
            }
        }

        return new ModuleImportMap($this->dir.'/importmap.php', new RemotePackageStorage($this->dir.'/vendor'), $files);
    }

    /** @return list<string> */
    private static function names(ImportMapEntries $entries): array
    {
        $names = array_map(static fn (ImportMapEntry $entry): string => $entry->importName, iterator_to_array($entries, false));
        sort($names);

        return $names;
    }
}
