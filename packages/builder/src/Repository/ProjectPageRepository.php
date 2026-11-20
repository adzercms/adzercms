<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Repository;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ProjectPage> */
class ProjectPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectPage::class);
    }

    /** @return list<ProjectPage> */
    public function of(Project $project): array
    {
        return $this->createQueryBuilder('p')->where('p.project = :project')->setParameter('project', $project)->orderBy('p.position')->addOrderBy('p.id')->getQuery()->getResult();
    }
}
