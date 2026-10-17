<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Translation;

use AdzerCMS\Core\Tests\Container;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\MessageCatalogue;

final class CatalogsTest extends KernelTestCase
{
    use Container;

    private const SOURCE = 'en';

    #[Group('ACMS-TEST-0068')]
    public function testPackageHasSameKeysInEveryLanguage(): void
    {
        foreach (self::packages('translations') as $directory) {
            $keys = [];
            foreach (glob($directory.'/*.xlf') ?: [] as $file) {
                $catalogue = self::catalogue($file);
                foreach ($catalogue->all() as $domain => $messages) {
                    foreach (array_keys($messages) as $key) {
                        $keys[$catalogue->getLocale()][] = $domain.' '.$key;
                    }
                }
            }

            $source = $keys[self::SOURCE] ?? self::fail(\sprintf('%s has no %s catalog', $directory, self::SOURCE));
            sort($source);
            foreach ($keys as $locale => $translated) {
                sort($translated);
                self::assertSame($source, $translated, \sprintf('%s: keys of %s differ from %s', $directory, $locale, self::SOURCE));
            }
        }
    }

    #[Group('ACMS-TEST-0068')]
    public function testKeyOfCodeAndTemplatesIsInSourceCatalog(): void
    {
        $extracted = new MessageCatalogue(self::SOURCE);
        foreach ([...self::packages('src'), ...self::packages('templates')] as $directory) {
            self::service(ExtractorInterface::class, 'translation.extractor')->extract($directory, $extracted);
        }
        $source = new MessageCatalogue(self::SOURCE);
        foreach (self::packages('translations') as $directory) {
            foreach (glob($directory.'/*.'.self::SOURCE.'.xlf') ?: [] as $file) {
                $source->addCatalogue(self::catalogue($file));
            }
        }

        $missing = [];
        foreach ($extracted->all() as $domain => $messages) {
            foreach (array_map(strval(...), array_keys($messages)) as $key) {
                if (!$source->defines($key, $domain)) {
                    $missing[] = $domain.' '.$key;
                }
            }
        }

        self::assertNotSame([], $extracted->all(), 'extractor found no keys in packages');
        self::assertSame([], $missing);
    }

    /** @return list<string> */
    private static function packages(string $directory): array
    {
        return glob(self::parameter('kernel.project_dir').'/packages/*/'.$directory, \GLOB_ONLYDIR) ?: [];
    }

    private static function catalogue(string $file): MessageCatalogue
    {
        [$domain, $locale] = explode('.', basename($file, '.xlf'), 2);

        return (new XliffFileLoader())->load($file, $locale, $domain);
    }
}
