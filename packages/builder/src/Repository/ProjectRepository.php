<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Repository;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Ui\Table;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Project> */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /** @return list<Project> */
    public function owned(User $owner, Table $table): array
    {
        $builder = $this->createQueryBuilder('p')->andWhere('p.owner = :owner')->setParameter('owner', $owner);
        $table->apply($builder);

        return $table->paginate($builder->addOrderBy('p.id')->getQuery());
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_map(intval(...), array_column($this->createQueryBuilder('p')->select('p.id')->orderBy('p.id')->getQuery()->getScalarResult(), 'id'));
    }

    public function counted(User $owner): int
    {
        return $this->count(['owner' => $owner]);
    }
}
