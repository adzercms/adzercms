<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Ui\Palette;
use AdzerCMS\Core\Module\Installed;
use AdzerCMS\Core\Module\Registry;
use AdzerCMS\Core\Setting\Brand;
use App\Kernel;
use Composer\InstalledVersions;
use Twig\Environment;

final readonly class ThemeCompleteness
{
    public const ENGINE = 'adzercms/theme';

    private const ENGINE_VENDOR = 'adzercms/';

    public function __construct(private Registry $registry, private Installed $theme)
    {
    }

    /** @return list<string> */
    public function missing(): array
    {
        $engine = $this->registry->package(self::ENGINE) ?? throw new \RuntimeException(\sprintf('completeness of a theme is measured against %s: it is not installed', self::ENGINE));
        [$reference, $own] = [self::path(self::ENGINE), self::path($this->theme->package)];

        $missing = array_map(static fn (string $template): string => 'template '.$template, array_values(array_diff(self::files($reference.'/templates', ''), self::files($own.'/templates', ''))));

        $build = new ModuleBuild($this->registry);
        $companions = array_values(array_filter(ModuleSuite::taken($this->registry, [$engine]), static fn (Installed $module): bool => $module !== $engine));
        array_push($missing, ...array_values(array_diff(
            $build->within(ModuleSuite::taken($this->registry, [$engine]), null, self::offered(...)),
            $build->within(ModuleSuite::taken($this->registry, [$this->theme, ...$companions]), null, self::offered(...)),
        )));

        $used = [];
        $root = InstalledVersions::getRootPackage()['name'];
        foreach (array_unique(InstalledVersions::getInstalledPackages()) as $package) {
            $path = InstalledVersions::getInstallPath($package);
            if (null !== $path && str_starts_with($package, self::ENGINE_VENDOR) && !\in_array($package, [$root, self::ENGINE, $this->theme->package], true)) {
                $templates = $path.'/templates';
                foreach (self::files($templates, '.twig') as $template) {
                    array_push($used, ...self::used((string) file_get_contents($templates.'/'.$template)));
                }
            }
        }
        $required = array_intersect(self::classes($reference), $used);
        foreach (array_values(array_unique(array_diff($required, self::classes($own)))) as $class) {
            $missing[] = 'class '.$class;
        }

        return $missing;
    }

    /** @return list<string> */
    private static function offered(Kernel $kernel): array
    {
        $offered = [];
        foreach (ModuleBuild::service($kernel, 'twig', Environment::class)->getExtensions() as $extension) {
            foreach ($extension->getFunctions() as $function) {
                $offered[] = 'function '.$function->getName();
            }
            foreach ($extension->getFilters() as $filter) {
                $offered[] = 'filter '.$filter->getName();
            }
            foreach ($extension->getTests() as $test) {
                $offered[] = 'test '.$test->getName();
            }
        }
        if (array_any(ModuleBuild::service($kernel, Brand::class, Brand::class)->settingFields(), static fn (Field $field): bool => Brand::COLOR === $field->name)) {
            $offered[] = 'service '.Palette::class;
        }

        return $offered;
    }

    /** @return list<string> */
    private static function used(string $template): array
    {
        $tags = '~\{[{%].*?[%}]\}~s';
        $tokens = [];
        preg_match_all('~(?<![\w-])class=(?:"([^"]*)"|\'([^\']*)\')~', $template, $attributes, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL);
        foreach ($attributes as $attribute) {
            $value = $attribute[1] ?? $attribute[2] ?? '';
            preg_match_all($tags, $value, $expressions);
            foreach ($expressions[0] as $expression) {
                preg_match_all('~\'([^\']*)\'|"([^"]*)"~', $expression, $literals, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL);
                foreach ($literals as $literal) {
                    array_push($tokens, ...self::words($literal[1] ?? $literal[2] ?? ''));
                }
            }
            array_push($tokens, ...self::words((string) preg_replace($tags, ' ', $value)));
        }

        return $tokens;
    }

    /** @return list<string> */
    private static function classes(string $package): array
    {
        $classes = [];
        foreach (self::files($package.'/public', '.css') as $file) {
            $css = (string) preg_replace(['~/\*.*?\*/~s', '~"[^"]*"|\'[^\']*\'~', '~\[[^\]]*\]~'], ['', '""', '[]'], (string) file_get_contents($package.'/public/'.$file));
            $parts = preg_split('~([{};])~', $css, flags: \PREG_SPLIT_DELIM_CAPTURE) ?: [];
            foreach ($parts as $index => $part) {
                if ('{' === ($parts[$index + 1] ?? null) && !str_starts_with(ltrim($part), '@')) {
                    preg_match_all('~\.(-?[A-Za-z_][\w-]*)~', $part, $names);
                    array_push($classes, ...$names[1]);
                }
            }
        }

        return array_values(array_unique($classes));
    }

    /** @return list<string> */
    private static function words(string $text): array
    {
        return preg_split('~\s+~', $text, flags: \PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** @return list<string> */
    private static function files(string $directory, string $suffix): array
    {
        if (!is_dir($directory)) {
            return [];
        }
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
                $files[] = substr($file->getPathname(), \strlen($directory) + 1);
            }
        }
        sort($files);

        return $files;
    }

    private static function path(string $package): string
    {
        return InstalledVersions::getInstallPath($package) ?? throw new \RuntimeException(\sprintf('%s is not installed', $package));
    }
}
