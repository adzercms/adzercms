<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Repository;

use AdzerCMS\Core\Entity\ModuleOperation;
use AdzerCMS\Core\Module\OperationState;
use AdzerCMS\Core\Ui\Table;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ModuleOperation> */
class ModuleOperationRepository extends ServiceEntityRepository
{
    private const OPEN = [OperationState::Pending->value, OperationState::Running->value];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModuleOperation::class);
    }

    public function open(string $package): ?ModuleOperation
    {
        return $this->createQueryBuilder('o')
            ->where('o.package = :package')
            ->andWhere('o.state IN (:open)')
            ->setParameter('package', $package)
            ->setParameter('open', self::OPEN)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return array<string, ModuleOperation> */
    public function opened(): array
    {
        $opened = [];
        foreach ($this->findBy(['state' => self::OPEN]) as $operation) {
            $opened[$operation->getPackage()] = $operation;
        }

        return $opened;
    }

    public function next(): ?ModuleOperation
    {
        return $this->createQueryBuilder('o')
            ->where('o.state = :state')
            ->setParameter('state', OperationState::Pending->value)
            ->orderBy('o.id')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<ModuleOperation> */
    public function inState(OperationState $state): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.state = :state')
            ->setParameter('state', $state->value)
            ->orderBy('o.id')
            ->getQuery()
            ->getResult();
    }

    /** @return list<ModuleOperation> */
    public function page(Table $table): array
    {
        $builder = $this->createQueryBuilder('o');
        $table->apply($builder)->total($this->count());

        return $builder
            ->addOrderBy('o.id', \SortDirection::Descending)
            ->setMaxResults($table->size)
            ->setFirstResult($table->offset())
            ->getQuery()
            ->getResult();
    }
}
