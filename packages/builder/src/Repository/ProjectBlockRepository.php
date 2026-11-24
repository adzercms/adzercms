<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Repository;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectBlock;
use AdzerCMS\Builder\Entity\ProjectPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ProjectBlock> */
class ProjectBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectBlock::class);
    }

    /** @return list<ProjectBlock> */
    public function of(ProjectPage $page): array
    {
        return $this->createQueryBuilder('b')->where('b.page = :page')->setParameter('page', $page)->orderBy('b.position')->addOrderBy('b.id')->getQuery()->getResult();
    }

    /** @return list<ProjectBlock> */
    public function ofProject(Project $project): array
    {
        return $this->createQueryBuilder('b')->join('b.page', 'p')->where('p.project = :project')->setParameter('project', $project)->orderBy('p.position')->addOrderBy('p.id')->addOrderBy('b.position')->addOrderBy('b.id')->getQuery()->getResult();
    }

    /**
     * @param list<int> $projects
     *
     * @return array<int, list<array{values: array<string, string>, style: array<string, array<string, array<string, string>>>}>>
     */
    public function held(array $projects): array
    {
        $held = [];
        foreach ($this->createQueryBuilder('b')->select('IDENTITY(p.project) AS project', 'b.values', 'b.style')->join('b.page', 'p')->where('p.project IN (:projects)')->setParameter('projects', $projects)->getQuery()->getArrayResult() as $row) {
            $held[(int) $row['project']][] = ['values' => $row['values'], 'style' => $row['style']];
        }

        return $held;
    }

    /** @return list<array{values: array<string, string>, style: array<string, array<string, array<string, string>>>}> */
    public function others(ProjectPage $page): array
    {
        return array_values($this->createQueryBuilder('b')->select('b.values', 'b.style')->join('b.page', 'p')->where('p.project = :project')->andWhere('p <> :page')->setParameter('project', $page->getProject())->setParameter('page', $page)->getQuery()->getArrayResult());
    }
}
