<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Repository;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectVersion;
use AdzerCMS\Core\Moderation\Decision;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ProjectVersion> */
class ProjectVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectVersion::class);
    }

    public function pending(Project $project): ?ProjectVersion
    {
        return $this->findOneBy(['project' => $project, 'decision' => Decision::Pending]);
    }

    /**
     * @param list<Project> $projects
     *
     * @return array<int, ProjectVersion>
     */
    public function latest(array $projects): array
    {
        if ([] === $projects) {
            return [];
        }
        $latest = $this->getEntityManager()->createQueryBuilder()->select('MAX(w.id)')->from(ProjectVersion::class, 'w')->where('w.project IN (:projects)')->groupBy('w.project');
        $versions = [];
        foreach ($this->createQueryBuilder('v')->where('v.id IN ('.$latest->getDQL().')')->setParameter('projects', $projects)->getQuery()->getResult() as $version) {
            $versions[(int) $version->getProject()->getId()] = $version;
        }

        return $versions;
    }
}
