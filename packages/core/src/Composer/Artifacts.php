<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Composer;

use AdzerCMS\Contracts\Refusal;
use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function Symfony\Component\Translation\t;

final readonly class Artifacts
{
    public const REPOSITORY = 'artifact';

    private const MANIFEST = 'composer.json';
    private const ARCHIVE = '.zip';
    private const NAME = '{^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$}D';
    private const STABLE = 'stable';
    private const RESOURCES = '__MACOSX/';

    public function __construct(
        #[Autowire('%adzercms.files_dir%/modules')]
        public string $directory,
    ) {
    }

    /** @return list<array{name: string, version: string, normalized: string, package: Package, path: string}> */
    public function archives(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $archives = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $manifest = $file instanceof \SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), self::ARCHIVE) ? self::manifest($file->getPathname()) : null;
            $archive = null === $manifest ? null : self::described($manifest);
            if (null !== $archive) {
                $archives[] = [...$archive, 'package' => Package::manifest($manifest, $archive['version']), 'path' => $file->getPathname()];
            }
        }
        usort($archives, static fn (array $left, array $right): int => [$left['name'], $left['normalized']] <=> [$right['name'], $right['normalized']]);

        return $archives;
    }

    /** @return array<string, Package> */
    public function packages(): array
    {
        $latest = [];
        foreach ($this->archives() as $archive) {
            $held = $latest[$archive['name']] ?? null;
            if (null === $held || Comparator::greaterThan($archive['normalized'], $held['normalized'])) {
                $latest[$archive['name']] = $archive;
            }
        }

        return array_map(static fn (array $archive): Package => $archive['package'], $latest);
    }

    public function upload(UploadedFile $file): ?Refusal
    {
        $manifest = $file->isValid() ? self::manifest($file->getPathname()) : null;
        if (null === $manifest) {
            return new Refusal(t('core.module.zip.unreadable'));
        }
        $archive = self::described($manifest);
        if (null === $archive) {
            return new Refusal(t('core.module.zip.manifest'));
        }

        $path = \sprintf('%s/%s/%s%s', $this->directory, $archive['name'], $archive['normalized'], self::ARCHIVE);
        if (is_file($path)) {
            return new Refusal(t('core.module.zip.taken', ['package' => $archive['name'], 'version' => $archive['version']]));
        }
        (new Filesystem())->mkdir(\dirname($path));
        $file->move(\dirname($path), basename($path));

        return null;
    }

    public function drop(string $name, string $normalized): void
    {
        foreach ($this->archives() as $archive) {
            if ([$name, $normalized] === [$archive['name'], $archive['normalized']]) {
                (new Filesystem())->remove($archive['path']);
            }
        }
    }

    /**
     * @param array<mixed> $manifest
     *
     * @return array{name: string, version: string, normalized: string}|null
     */
    private static function described(array $manifest): ?array
    {
        $name = $manifest['name'] ?? null;
        $version = $manifest['version'] ?? null;
        if (!\is_string($name) || 1 !== preg_match(self::NAME, $name) || !\is_string($version)) {
            return null;
        }

        try {
            $normalized = (new VersionParser())->normalize($version);
        } catch (\UnexpectedValueException) {
            return null;
        }

        return self::STABLE === VersionParser::parseStability($normalized) ? ['name' => $name, 'version' => $version, 'normalized' => $normalized] : null;
    }

    /** @return array<mixed>|null */
    private static function manifest(string $path): ?array
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($path, \ZipArchive::RDONLY)) {
            return null;
        }

        $json = $zip->getFromName(self::MANIFEST);
        if (false === $json) {
            $tops = [];
            for ($index = 0; $index < $zip->numFiles; ++$index) {
                $entry = (string) $zip->getNameIndex($index);
                if (!str_starts_with($entry, self::RESOURCES)) {
                    $tops[explode('/', $entry, 2)[0]] = true;
                }
            }
            $json = 1 === \count($tops) ? $zip->getFromName(array_key_first($tops).'/'.self::MANIFEST) : false;
        }
        $zip->close();

        $manifest = false === $json ? null : json_decode($json, true);

        return \is_array($manifest) ? $manifest : null;
    }
}
