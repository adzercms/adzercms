<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Translation;

use AdzerCMS\Core\Composer\Localized;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Text\Texts;
use AdzerCMS\Core\Ui\State;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\TranslatableMessage;

#[AsCommand(name: 'adzercms:catalogs', description: 'Translation catalogs of a package: the same keys in every language, keys of its code and templates in the source catalogs of the package and of the selected modules, the description and the title of a module package in every engine language')]
final readonly class Catalogs
{
    private const SOURCE = 'en';
    private const COLUMN = 'core.column.';

    public function __construct(private KernelInterface $kernel)
    {
    }

    public function __invoke(OutputInterface $output, #[Argument('Package directory')] string $package): int
    {
        $own = [];
        foreach (glob($package.'/translations/*.xlf') ?: [] as $file) {
            $own[pathinfo(basename($file, '.xlf'), \PATHINFO_EXTENSION)][] = $file;
        }

        $refusals = [];
        $catalogue = Texts::load(self::SOURCE, $own[self::SOURCE] ?? []);
        $source = self::keys($catalogue);
        $container = $this->kernel->getContainer();
        $manifest = json_decode((string) file_get_contents($package.'/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
        if (\is_array($manifest) && null !== Registry::installed()->package(\is_string($manifest['name'] ?? null) ? $manifest['name'] : '')) {
            /** @var list<string> $locales */
            $locales = $container->getParameter('kernel.enabled_locales');
            foreach (['description' => Localized::description($manifest), 'title' => Localized::title($manifest)] as $name => $text) {
                if ('' === $text->source) {
                    $refusals[] = \sprintf('composer.json lacks the module %s', $name);
                }
                foreach (array_diff($locales, [self::SOURCE], array_keys($text->translations)) as $locale) {
                    $refusals[] = \sprintf('composer.json lacks the module %s extra.adzercms.%s.%s', $name, $name, $locale);
                }
            }
        }
        /** @var array<string, list<string>> $files */
        $files = $container->getParameter(Texts::FILES);
        foreach ($own as $locale => $owned) {
            $catalogue = Texts::load($locale, $owned);
            $keys = self::keys($catalogue);
            foreach (array_diff($source, $keys) as $key) {
                $refusals[] = \sprintf('%s catalog lacks %s of %s', $locale, $key, self::SOURCE);
            }
            foreach (array_diff($keys, $source) as $key) {
                $refusals[] = \sprintf('%s catalog has %s beyond %s', $locale, $key, self::SOURCE);
            }
            $words = self::vocabulary(Texts::load($locale, [...$files[$locale] ?? [], ...$owned]));
            foreach ($catalogue->all() as $messages) {
                foreach ($messages as $key => $text) {
                    $word = $words[self::word($text)] ?? (string) $key;
                    if ($word !== (string) $key) {
                        $refusals[] = \sprintf('%s %s repeats %s of the core vocabulary: the label goes by its key', $locale, $key, $word);
                    }
                }
            }
        }

        $services = $container->get('test.service_container');
        $extractor = $services instanceof ContainerInterface ? $services->get('translation.extractor') : null;
        if (!$extractor instanceof ExtractorInterface) {
            throw new \LogicException('translation extractor is in the test environment only');
        }
        $extracted = new MessageCatalogue(self::SOURCE);
        foreach (array_filter([$package.'/src', $package.'/templates'], is_dir(...)) as $directory) {
            $extractor->extract($directory, $extracted);
        }
        $defined = Texts::load(self::SOURCE, [...$files[self::SOURCE] ?? [], ...$own[self::SOURCE] ?? []]);
        foreach ($extracted->all() as $domain => $messages) {
            foreach (array_map(strval(...), array_keys($messages)) as $key) {
                if (!$defined->defines($key, $domain)) {
                    $refusals[] = \sprintf('%s %s of code or templates is missing in %s catalogs', $domain, $key, self::SOURCE);
                }
            }
        }
        foreach ($refusals as $refusal) {
            $output->writeln($package.': '.$refusal);
        }

        return [] === $refusals ? Command::SUCCESS : Command::FAILURE;
    }

    /** @return array<string, string> */
    private static function vocabulary(MessageCatalogue $catalogue): array
    {
        $keys = [];
        foreach (State::cases() as $state) {
            $label = $state->label();
            if ($label instanceof TranslatableMessage) {
                $keys[] = $label->getMessage();
            }
        }
        $words = [];
        foreach ($catalogue->all() as $messages) {
            foreach ($messages as $key => $text) {
                if (str_starts_with((string) $key, self::COLUMN) || \in_array((string) $key, $keys, true)) {
                    $words[self::word($text)] = (string) $key;
                }
            }
        }

        return $words;
    }

    private static function word(mixed $text): string
    {
        return mb_strtolower(trim(\is_scalar($text) ? (string) $text : ''));
    }

    /** @return list<string> */
    private static function keys(MessageCatalogue $catalogue): array
    {
        $keys = [];
        foreach ($catalogue->all() as $domain => $messages) {
            foreach (array_keys($messages) as $key) {
                $keys[] = $domain.' '.$key;
            }
        }

        return $keys;
    }
}
