<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Translation;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Owner;
use AdzerCMS\Core\Composer\Description;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Setting\Catalog;
use AdzerCMS\Core\Text\Texts;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\TranslatableMessage;

#[AsCommand(name: 'adzercms:catalogs', description: 'Translation catalogs of a package: the same keys in every language, keys of its code and templates and labels of the settings tabs of its owners in the source catalogs of the package and of the selected modules, the description of a module package in every engine language')]
final readonly class Catalogs
{
    private const SOURCE = 'en';
    private const DOMAIN = 'messages';

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
            $description = Description::of($manifest);
            if ('' === $description->source) {
                $refusals[] = 'composer.json lacks the module description';
            }
            /** @var list<string> $locales */
            $locales = $container->getParameter('kernel.enabled_locales');
            foreach (array_diff($locales, [self::SOURCE], array_keys($description->translations)) as $locale) {
                $refusals[] = \sprintf('composer.json lacks the module description extra.adzercms.description.%s', $locale);
            }
        }
        foreach ($own as $locale => $files) {
            $keys = self::keys(Texts::load($locale, $files));
            foreach (array_diff($source, $keys) as $key) {
                $refusals[] = \sprintf('%s catalog lacks %s of %s', $locale, $key, self::SOURCE);
            }
            foreach (array_diff($keys, $source) as $key) {
                $refusals[] = \sprintf('%s catalog has %s beyond %s', $locale, $key, self::SOURCE);
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
        /** @var array<string, list<string>> $files */
        $files = $container->getParameter(Texts::FILES);
        $defined = Texts::load(self::SOURCE, [...$files[self::SOURCE] ?? [], ...$own[self::SOURCE] ?? []]);
        foreach ($extracted->all() as $domain => $messages) {
            foreach (array_map(strval(...), array_keys($messages)) as $key) {
                if (!$defined->defines($key, $domain)) {
                    $refusals[] = \sprintf('%s %s of code or templates is missing in %s catalogs', $domain, $key, self::SOURCE);
                }
            }
        }
        $settings = $services instanceof ContainerInterface ? $services->get(Catalog::class) : null;
        $fields = $services instanceof ContainerInterface ? self::fields($services, $package, $manifest) : [];
        foreach ($settings instanceof Catalog ? $settings->groups() : [] as $group) {
            $label = $group['label'];
            if ($label instanceof TranslatableMessage && array_any($group['fields'], static fn (Field $field): bool => isset($fields[$field->name])) && !$defined->defines($label->getMessage(), $label->getDomain() ?? self::DOMAIN)) {
                $refusals[] = \sprintf('settings tab label %s is missing in %s catalogs', $label->getMessage(), self::SOURCE);
            }
        }

        foreach ($refusals as $refusal) {
            $output->writeln($package.': '.$refusal);
        }

        return [] === $refusals ? Command::SUCCESS : Command::FAILURE;
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

    /** @return array<string, true> */
    private static function fields(ContainerInterface $services, string $package, mixed $manifest): array
    {
        $autoload = \is_array($manifest) && \is_array($manifest['autoload'] ?? null) ? $manifest['autoload'] : [];
        $fields = [];
        foreach (\is_array($autoload['psr-4'] ?? null) ? $autoload['psr-4'] : [] as $prefix => $directories) {
            foreach (array_filter(array_map(static fn (mixed $directory): string => $package.'/'.(\is_string($directory) ? $directory : ''), (array) $directories), is_dir(...)) as $directory) {
                foreach (Finder::create()->files()->in($directory)->name('*.php') as $file) {
                    $class = $prefix.strtr(substr($file->getRelativePathname(), 0, -\strlen('.php')), '/', '\\');
                    $owner = is_subclass_of($class, Owner::class) && $services->has($class) ? $services->get($class) : null;
                    foreach ($owner instanceof Owner ? $owner->settingFields() : [] as $field) {
                        $fields[$field->name] = true;
                    }
                }
            }
        }

        return $fields;
    }
}
