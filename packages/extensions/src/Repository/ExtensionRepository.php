<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Repository;

use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Extensions\Entity\Extension;
use AdzerCMS\Extensions\ExtensionType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Extension> */
class ExtensionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Extension::class);
    }

    /** @return list<Extension> */
    public function page(Table $table): array
    {
        $builder = $this->listing($table);
        $table->apply($builder)->total((int) $this->listing($table)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult());

        return $builder
            ->setMaxResults($table->size)
            ->setFirstResult($table->offset())
            ->getQuery()
            ->getResult();
    }

    /** @return list<Extension> */
    public function ordered(): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.position')
            ->addOrderBy('e.id')
            ->getQuery()
            ->getResult();
    }

    public function last(): int
    {
        return (int) $this->createQueryBuilder('e')->select('MAX(e.position)')->getQuery()->getSingleScalarResult();
    }

    public function named(string $name): ?Extension
    {
        return $this->findOneBy(['name' => $name]);
    }

    private function listing(Table $table): QueryBuilder
    {
        $builder = $this->createQueryBuilder('e');

        if ('' !== $table->term()) {
            $builder->andWhere(Filter::matching($builder, $table->term(), ['e.name']));
        }

        $type = ExtensionType::tryFrom($table->value('type'));
        if (null !== $type) {
            $builder->andWhere('e.type = :type')->setParameter('type', $type);
        }

        return $builder;
    }
}
