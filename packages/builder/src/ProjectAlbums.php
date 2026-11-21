<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Builder\Controller\ProjectController;
use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Repository\ProjectBlockRepository;
use AdzerCMS\Builder\Repository\ProjectRepository;
use AdzerCMS\Core\Photo\Photos;
use AdzerCMS\Pages\PhotoAlbum;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function Symfony\Component\Clock\now;

final readonly class ProjectAlbums
{
    private const PLACE = 'builder/';
    private const SIDE = 1920;
    private const TERM = '-1 day';
    private const CHUNK = 500;

    public function __construct(
        private Photos $photos,
        private UrlGeneratorInterface $urls,
        private ProjectBlockRepository $blocks,
        private ProjectRepository $projects,
    ) {
    }

    public function of(Project $project): PhotoAlbum
    {
        $id = (int) $project->getId();

        return $this->album($id, fn (): array => $this->blocks->values([$id])[$id] ?? []);
    }

    public function sweep(): int
    {
        $before = now()->modify(self::TERM)->getTimestamp();
        $swept = 0;
        foreach (array_chunk($this->projects->ids(), self::CHUNK) as $ids) {
            $values = $this->blocks->values($ids);
            foreach ($ids as $id) {
                $swept += $this->album($id, static fn (): array => $values[$id] ?? [])->sweep($before);
            }
        }

        return $swept;
    }

    /** @param \Closure(): list<array<string, string>> $values */
    private function album(int $id, \Closure $values): PhotoAlbum
    {
        return new PhotoAlbum(
            $this->photos,
            self::PLACE.$id,
            self::SIDE,
            fn (string $name): string => $this->urls->generate(ProjectController::PICTURE, ['id' => $id, 'file' => $name]),
            static fn (): array => PhotoAlbum::names(array_merge([], ...array_map(array_values(...), $values()))),
        );
    }
}
