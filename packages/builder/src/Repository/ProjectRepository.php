<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Repository;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectVersion;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Ui\Table;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Project> */
class ProjectRepository extends ServiceEntityRepository
{
    public const DECISION = 'version_decision';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /** @return list<Project> */
    public function owned(User $owner, Table $table): array
    {
        $builder = $this->createQueryBuilder('p')->andWhere('p.owner = :owner')->setParameter('owner', $owner)
            ->addSelect(\sprintf('(SELECT w.decision FROM %1$s w WHERE w.project = p AND w.id = (SELECT MAX(x.id) FROM %1$s x WHERE x.project = p)) AS HIDDEN %2$s', ProjectVersion::class, self::DECISION));
        $table->apply($builder);

        return $table->paginate($builder->addOrderBy('p.id')->getQuery());
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_map(intval(...), array_column($this->createQueryBuilder('p')->select('p.id')->orderBy('p.id')->getQuery()->getScalarResult(), 'id'));
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, string>
     */
    public function covers(array $ids): array
    {
        $covers = [];
        foreach ($this->createQueryBuilder('p')->select('p.id', 'p.cover')->where('p.id IN (:ids)')->andWhere('p.cover IS NOT NULL')->setParameter('ids', $ids)->getQuery()->getArrayResult() as $row) {
            $covers[(int) $row['id']] = (string) $row['cover'];
        }

        return $covers;
    }

    public function counted(User $owner): int
    {
        return $this->count(['owner' => $owner]);
    }
}
