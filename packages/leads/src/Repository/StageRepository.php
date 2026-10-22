<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Repository;

use AdzerCMS\Leads\Entity\Stage;
use AdzerCMS\Leads\StageGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Stage> */
class StageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stage::class);
    }

    /** @return list<Stage> */
    public function funnel(): array
    {
        $stages = $this->createQueryBuilder('s')->orderBy('s.position')->addOrderBy('s.id')->getQuery()->getResult();
        usort($stages, static fn (Stage $left, Stage $right): int => $left->getGroup()->rank() <=> $right->getGroup()->rank());

        return $stages;
    }

    /** @return list<Stage> */
    public function ofGroup(StageGroup $group): array
    {
        return $this->grouped($group)->getQuery()->getResult();
    }

    public function first(StageGroup $group): Stage
    {
        $stage = $this->grouped($group)->setMaxResults(1)->getQuery()->getOneOrNullResult();

        return $stage instanceof Stage ? $stage : throw new \LogicException(\sprintf('the group %s holds no stage', $group->value));
    }

    private function grouped(StageGroup $group): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->where('s.group = :group')
            ->setParameter('group', $group->value)
            ->orderBy('s.position')
            ->addOrderBy('s.id');
    }
}
