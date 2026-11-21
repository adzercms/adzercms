<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectBlock;
use AdzerCMS\Builder\Entity\ProjectPage;
use AdzerCMS\Builder\Repository\ProjectBlockRepository;
use AdzerCMS\Builder\Repository\ProjectPageRepository;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Photo\Photos;
use AdzerCMS\Pages\Album;
use AdzerCMS\Pages\BlockStyle;
use AdzerCMS\Pages\Entity\BlockRecord;
use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\PhotoAlbum;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

use function Symfony\Component\Translation\t;

final readonly class ProjectFile
{
    public const FORMAT = 1;
    public const DOCUMENT = 'project.json';
    public const PICTURES = 'pictures/';

    private const FORMATS = [self::FORMAT];
    private const DOCUMENT_MEGABYTES = 2 * Projects::SIZE;
    private const PICTURE_MEGABYTES = 10;

    public function __construct(
        private ProjectPageRepository $pages,
        private ProjectBlockRepository $blocks,
        private ProjectAlbums $albums,
    ) {
    }

    /** @return list<array{path: string, title: string, blocks: list<array{type: string, values: array<string, string>, style: array<string, array<string, array<string, string>>>}>}> */
    public function document(Project $project): array
    {
        $held = [];
        foreach ($this->blocks->ofProject($project) as $block) {
            $held[(int) $block->getPage()->getId()][] = $block;
        }

        return array_map(static fn (ProjectPage $page): array => [
            'path' => $page->getPath(),
            'title' => $page->getTitle(),
            'blocks' => array_map(static fn (ProjectBlock $block): array => ['type' => $block->getType(), 'values' => $block->getValues(), 'style' => $block->getStyle()], $held[(int) $page->getId()] ?? []),
        ], $this->pages->of($project));
    }

    /**
     * @param list<array{blocks: list<array{values: array<string, string>}>}> $pages
     *
     * @return list<string>
     */
    public static function names(array $pages): array
    {
        return array_values(array_unique(PhotoAlbum::names(array_merge([], ...array_map(static fn (array $page): array => array_merge([], ...array_map(static fn (array $block): array => array_values($block['values']), $page['blocks'])), $pages)))));
    }

    /**
     * @param list<array{path: string, title: string, blocks: list<array{type: string, values: array<string, string>, style: array<string, array<string, array<string, string>>>}>}> $pages
     * @param array<string, string>                                                                                                                                                  $names
     *
     * @return list<array{path: string, title: string, blocks: list<array{type: string, values: array<string, string>, style: array<string, array<string, array<string, string>>>}>}>
     */
    public static function renamed(array $pages, array $names): array
    {
        return array_map(static fn (array $page): array => [
            'path' => $page['path'],
            'title' => $page['title'],
            'blocks' => array_map(static fn (array $block): array => ['type' => $block['type'], 'values' => array_map(static fn (string $value): string => strtr($value, $names), $block['values']), 'style' => $block['style']], $page['blocks']),
        ], $pages);
    }

    public function write(Project $project): string
    {
        $pages = $this->document($project);
        $path = (new Filesystem())->tempnam(sys_get_temp_dir(), 'project');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString(self::DOCUMENT, json_encode(['format' => self::FORMAT, 'pages' => array_map(static fn (array $page): array => [
            'path' => $page['path'],
            'title' => $page['title'],
            'blocks' => array_map(static fn (array $block): array => ['type' => $block['type'], 'values' => (object) $block['values'], 'style' => (object) $block['style']], $page['blocks']),
        ], $pages)], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));
        $album = $this->albums->of($project);
        foreach (self::names($pages) as $name) {
            $file = $album->path($name);
            if (null !== $file) {
                $zip->addFile($file, self::PICTURES.$name);
            }
        }
        $zip->close();

        return $path;
    }

    /** @return array{list<array{path: string, title: string, blocks: list<array{type: string, values: array<string, string>, style: array<string, array<string, array<string, string>>>}>}>, list<string>}|Refusal */
    public function read(File $file): array|Refusal
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($file->getPathname(), \ZipArchive::RDONLY)) {
            return new Refusal(t('builder.refusal.file'));
        }
        try {
            $json = self::entry($zip, self::DOCUMENT, self::DOCUMENT_MEGABYTES);
            $document = null === $json ? null : json_decode($json, true);
            if (!\is_array($document) || !\is_int($document['format'] ?? null)) {
                return new Refusal(t('builder.refusal.file'));
            }
            if (!\in_array($document['format'], self::FORMATS, true)) {
                return new Refusal(t('builder.refusal.format', ['format' => $document['format']]));
            }
            $pages = self::pages($document['pages'] ?? null);
            if ($pages instanceof Refusal) {
                return $pages;
            }

            $inside = [];
            for ($at = 0; $at < $zip->numFiles; ++$at) {
                $entry = (string) $zip->getNameIndex($at);
                if (str_starts_with($entry, self::PICTURES)) {
                    $inside[substr($entry, \strlen(self::PICTURES))] = true;
                }
            }
            $values = array_merge([], ...array_map(static fn (array $page): array => array_merge([], ...array_map(static fn (array $block): array => array_values($block['values']), $page['blocks'])), $pages));
            $pictures = array_values(array_filter(array_unique(PhotoAlbum::names($values)), static fn (string $name): bool => isset($inside[$name])));

            return \count($pictures) > Projects::PICTURES ? new Refusal(t('builder.refusal.pictures', ['limit' => Projects::PICTURES])) : [$pages, $pictures];
        } finally {
            $zip->close();
        }
    }

    /**
     * @param list<string> $names
     *
     * @return array<string, string>|Refusal
     */
    public function pictures(File $file, array $names, Album $album): array|Refusal
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($file->getPathname(), \ZipArchive::RDONLY)) {
            return new Refusal(t('builder.refusal.file'));
        }
        $filesystem = new Filesystem();
        $path = $filesystem->tempnam(sys_get_temp_dir(), 'picture');
        $stored = [];
        try {
            foreach ($names as $name) {
                $content = self::entry($zip, self::PICTURES.$name, self::PICTURE_MEGABYTES);
                if (null !== $content) {
                    $filesystem->dumpFile($path, $content);
                }
                $picture = new File($path);
                if (null === $content || !\in_array($picture->getMimeType(), Photos::TYPES, true)) {
                    $album->drop(array_values($stored));

                    return new Refusal(t('builder.refusal.file'));
                }
                $stored[$name] = $album->store($picture);
            }

            return $stored;
        } finally {
            $zip->close();
            $filesystem->remove($path);
        }
    }

    private static function entry(\ZipArchive $zip, string $name, int $megabytes): ?string
    {
        $entry = $zip->statName($name);
        $content = false === $entry || $entry['size'] > $megabytes * ProjectDocument::MEGABYTE ? false : $zip->getFromName($name);

        return false === $content ? null : $content;
    }

    /** @return list<array{path: string, title: string, blocks: list<array{type: string, values: array<string, string>, style: array<string, array<string, array<string, string>>>}>}>|Refusal */
    private static function pages(mixed $pages): array|Refusal
    {
        if (!\is_array($pages) || [] === $pages || !array_is_list($pages)) {
            return new Refusal(t('builder.refusal.file'));
        }
        if (\count($pages) > Projects::PAGES) {
            return new Refusal(t('builder.refusal.pages', ['limit' => Projects::PAGES]));
        }
        $read = [];
        $size = 0;
        foreach ($pages as $page) {
            $path = \is_array($page) && \is_string($page['path'] ?? null) ? Page::parse($page['path']) : null;
            $blocks = \is_array($page) ? self::blocks($page['blocks'] ?? null) : null;
            if (null === $path || isset($read[$path]) || !\is_string($page['title'] ?? null) || null === $blocks) {
                return new Refusal(t('builder.refusal.file'));
            }
            if ($blocks instanceof Refusal) {
                return $blocks;
            }
            $read[$path] = ['path' => $path, 'title' => $page['title'], 'blocks' => $blocks];
            $size += array_sum(array_map(static fn (array $block): int => ProjectDocument::size($block['values'], $block['style']), $blocks));
        }

        return $size > Projects::SIZE * ProjectDocument::MEGABYTE ? new Refusal(t('builder.refusal.size', ['megabytes' => Projects::SIZE])) : array_values($read);
    }

    /** @return list<array{type: string, values: array<string, string>, style: array<string, array<string, array<string, string>>>}>|Refusal|null */
    private static function blocks(mixed $blocks): array|Refusal|null
    {
        if (!\is_array($blocks) || !array_is_list($blocks)) {
            return null;
        }
        $read = [];
        foreach ($blocks as $block) {
            $type = \is_array($block) ? $block['type'] ?? null : null;
            $values = \is_array($block) ? PageDocument::values($block['values'] ?? null) : null;
            $style = \is_array($block) && \is_array($block['style'] ?? null) ? BlockStyle::rules($block['style']) : null;
            if (!\is_string($type) || '' === $type || mb_strlen($type) > BlockRecord::TYPE || null === $values || null === $style) {
                return null;
            }
            if ($style instanceof Refusal) {
                return $style;
            }
            $read[] = ['type' => $type, 'values' => $values, 'style' => $style];
        }

        return $read;
    }
}
