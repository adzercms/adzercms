<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Repository;

use AdzerCMS\Core\Entity\ModuleRequest;
use AdzerCMS\Core\Module\RequestState;
use AdzerCMS\Core\Ui\Table;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ModuleRequest> */
class ModuleRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModuleRequest::class);
    }

    public function open(string $package): ?ModuleRequest
    {
        return $this->createQueryBuilder('r')
            ->where('r.package = :package')
            ->andWhere('r.state IN (:open)')
            ->setParameter('package', $package)
            ->setParameter('open', [RequestState::Pending->value, RequestState::Running->value])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<ModuleRequest> */
    public function inState(RequestState $state): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.state = :state')
            ->setParameter('state', $state->value)
            ->orderBy('r.id')
            ->getQuery()
            ->getResult();
    }

    /** @return list<ModuleRequest> */
    public function page(Table $table): array
    {
        $builder = $this->createQueryBuilder('r');
        $table->apply($builder)->total($this->count());

        return $builder
            ->setMaxResults($table->size)
            ->setFirstResult($table->offset())
            ->getQuery()
            ->getResult();
    }
}
