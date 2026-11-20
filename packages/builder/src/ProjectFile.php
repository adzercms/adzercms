<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectBlock;
use AdzerCMS\Builder\Entity\ProjectPage;
use AdzerCMS\Builder\Repository\ProjectBlockRepository;
use AdzerCMS\Builder\Repository\ProjectPageRepository;
use AdzerCMS\Pages\PhotoAlbum;
use Symfony\Component\Filesystem\Filesystem;

final readonly class ProjectFile
{
    public const FORMAT = 1;
    public const DOCUMENT = 'project.json';
    public const PICTURES = 'pictures/';

    public function __construct(
        private ProjectPageRepository $pages,
        private ProjectBlockRepository $blocks,
        private ProjectAlbums $albums,
    ) {
    }

    public function write(Project $project): string
    {
        $held = [];
        foreach ($this->blocks->ofProject($project) as $block) {
            $held[(int) $block->getPage()->getId()][] = $block;
        }
        $pages = array_map(static fn (ProjectPage $page): array => [
            'path' => $page->getPath(),
            'title' => $page->getTitle(),
            'blocks' => array_map(static fn (ProjectBlock $block): array => ['type' => $block->getType(), 'values' => (object) $block->getValues(), 'style' => (object) $block->getStyle()], $held[(int) $page->getId()] ?? []),
        ], $this->pages->of($project));

        $path = (new Filesystem())->tempnam(sys_get_temp_dir(), 'project');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString(self::DOCUMENT, json_encode(['format' => self::FORMAT, 'pages' => $pages], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));
        $album = $this->albums->of($project);
        foreach (array_unique(PhotoAlbum::names(array_merge([], ...array_map(static fn (ProjectBlock $block): array => array_values($block->getValues()), array_merge([], ...array_values($held)))))) as $name) {
            $file = $album->path($name);
            if (null !== $file) {
                $zip->addFile($file, self::PICTURES.$name);
            }
        }
        $zip->close();

        return $path;
    }
}
