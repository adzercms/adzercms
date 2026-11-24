<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Builder\Controller\ProjectController;
use AdzerCMS\Builder\Controller\VersionController;
use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectVersion;
use AdzerCMS\Builder\Repository\ProjectBlockRepository;
use AdzerCMS\Builder\Repository\ProjectRepository;
use AdzerCMS\Builder\Repository\ProjectVersionRepository;
use AdzerCMS\Core\Photo\Photos;
use AdzerCMS\Pages\PhotoAlbum;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function Symfony\Component\Clock\now;

final readonly class ProjectAlbums
{
    private const PLACE = 'builder/';
    private const VERSIONS = 'builder/versions/';
    private const SIDE = 1920;
    private const TERM = '-1 day';
    private const CHUNK = 500;

    public function __construct(
        private Photos $photos,
        private UrlGeneratorInterface $urls,
        private ProjectBlockRepository $blocks,
        private ProjectRepository $projects,
        private ProjectVersionRepository $versions,
    ) {
    }

    public function of(Project $project): PhotoAlbum
    {
        $id = (int) $project->getId();

        return $this->album($id, fn (): array => $this->blocks->held([$id])[$id] ?? [], static fn (): array => array_filter([$project->getCover()]));
    }

    public function ofVersion(ProjectVersion $version): PhotoAlbum
    {
        $id = (int) $version->getId();

        return new PhotoAlbum(
            $this->photos,
            self::VERSIONS.$id,
            self::SIDE,
            fn (string $name): string => $this->urls->generate(VersionController::PICTURE, ['id' => $id, 'file' => $name]),
            static fn (): array => [...ProjectFile::names($version->getPages()), $version->getCover()],
        );
    }

    /** @return list<PhotoAlbum> */
    public function all(Project $project): array
    {
        return [$this->of($project), ...array_map($this->ofVersion(...), $this->versions->findBy(['project' => $project]))];
    }

    /**
     * @param list<string> $names
     *
     * @return array<string, string>
     */
    public function copy(PhotoAlbum $from, PhotoAlbum $to, array $names): array
    {
        $copied = [];
        foreach (array_unique($names) as $name) {
            $path = $from->path($name);
            if (null !== $path) {
                $copied[$name] = $to->store(new File($path));
            }
        }

        return $copied;
    }

    public function sweep(): int
    {
        $before = now()->modify(self::TERM)->getTimestamp();
        $swept = 0;
        foreach (array_chunk($this->projects->ids(), self::CHUNK) as $ids) {
            $held = $this->blocks->held($ids);
            $covers = $this->projects->covers($ids);
            foreach ($ids as $id) {
                $swept += $this->album($id, static fn (): array => $held[$id] ?? [], static fn (): array => array_filter([$covers[$id] ?? null]))->sweep($before);
            }
        }

        return $swept;
    }

    /**
     * @param \Closure(): list<array{values: array<string, string>, style: array<string, array<string, array<string, string>>>}> $blocks
     * @param \Closure(): array<string>                                                                                          $covers
     */
    private function album(int $id, \Closure $blocks, \Closure $covers): PhotoAlbum
    {
        return new PhotoAlbum(
            $this->photos,
            self::PLACE.$id,
            self::SIDE,
            fn (string $name): string => $this->urls->generate(ProjectController::PICTURE, ['id' => $id, 'file' => $name]),
            static fn (): array => [...PhotoAlbum::blocks($blocks()), ...array_values($covers())],
        );
    }
}
