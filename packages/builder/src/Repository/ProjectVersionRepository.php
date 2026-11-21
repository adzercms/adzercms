<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Repository;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectVersion;
use AdzerCMS\Builder\ProjectAccess;
use AdzerCMS\Core\Moderation\Decision;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Table;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ProjectVersion> */
class ProjectVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectVersion::class);
    }

    /** @return list<ProjectVersion> */
    public function page(Table $table): array
    {
        $builder = $this->createQueryBuilder('v')->join('v.project', 'p')->join('p.owner', 'u')->addSelect('p', 'u')
            ->addSelect(\sprintf("CASE WHEN v.decision = '%s' THEN 0 ELSE 1 END AS HIDDEN waiting", Decision::Pending->value))->orderBy('waiting');
        if ('' !== $table->term()) {
            $builder->andWhere(Filter::matching($builder, $table->term(), ['v.name']));
        }
        $table->apply($builder);

        return $table->paginate($builder->addOrderBy('v.id')->getQuery());
    }

    public function pending(Project $project): ?ProjectVersion
    {
        return $this->findOneBy(['project' => $project, 'decision' => Decision::Pending]);
    }

    public function shown(Project $project): ?ProjectVersion
    {
        return $this->shownOnes()->andWhere('v.project = :project')->setParameter('project', $project)->getQuery()->getOneOrNullResult();
    }

    /** @return list<ProjectVersion> */
    public function catalog(Table $table): array
    {
        return $table->paginate($this->shownOnes()->andWhere('p.access = :open')->setParameter('open', ProjectAccess::Open->value)->orderBy('v.id', 'DESC')->getQuery());
    }

    private function shownOnes(): QueryBuilder
    {
        return $this->createQueryBuilder('v')->join('v.project', 'p')->addSelect('p')
            ->where('v.decision = :published')->andWhere('p.access <> :personal')
            ->andWhere(\sprintf('v.id = (SELECT MAX(w.id) FROM %s w WHERE w.project = p AND w.decision IN (:decided))', ProjectVersion::class))
            ->setParameter('published', Decision::Published->value)->setParameter('personal', ProjectAccess::Personal->value)
            ->setParameter('decided', [Decision::Published->value, Decision::Withdrawn->value]);
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
