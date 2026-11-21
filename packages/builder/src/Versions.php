<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectVersion;
use AdzerCMS\Builder\Repository\ProjectVersionRepository;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Photo\Photos;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function Symfony\Component\Translation\t;

final readonly class Versions
{
    public const NAME = 'name';
    public const COVER = 'cover';

    public function __construct(
        private ProjectVersionRepository $versions,
        private ProjectFile $file,
        private ProjectAlbums $albums,
        private EntityManagerInterface $em,
    ) {
    }

    /** @return ProjectVersion|array<string, Refusal> */
    public function submit(Project $project, string $name, ?UploadedFile $cover): ProjectVersion|array
    {
        $name = trim($name);
        $refusals = array_filter([
            self::NAME => '' === $name || mb_strlen($name) > Project::NAME ? new Refusal(t('builder.refusal.name', ['limit' => Project::NAME])) : null,
            self::COVER => match (true) {
                null !== $cover => Photos::refusal([$cover]),
                null === $project->getCover() => new Refusal(t('builder.refusal.cover')),
                default => null,
            },
        ]);
        if ([] !== $refusals) {
            return $refusals;
        }

        $album = $this->albums->of($project);
        $former = $project->getCover();
        $project->present($name, null === $cover ? (string) $former : $album->store($cover));
        if (null !== $former) {
            $album->drop([$former]);
        }
        $pending = $this->versions->pending($project);
        $stale = null === $pending ? null : $this->albums->ofVersion($pending);
        if (null !== $pending) {
            $this->em->remove($pending);
        }
        $version = new ProjectVersion($project, $name);
        $this->em->persist($version);
        $this->em->flush();
        $stale?->clear();

        $pages = $this->file->document($project);
        $copied = $this->albums->copy($album, $this->albums->ofVersion($version), [...ProjectFile::names($pages), (string) $project->getCover()]);
        $version->snapshot(ProjectFile::renamed($pages, $copied), $copied[(string) $project->getCover()] ?? '');
        $this->em->flush();

        return $version;
    }
}
