<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Repository;

use AdzerCMS\Support\Entity\HelpCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<HelpCategory> */
class HelpCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HelpCategory::class);
    }

    /** @return list<HelpCategory> */
    public function ordered(): array
    {
        return $this->createQueryBuilder('c')->orderBy('c.position')->addOrderBy('c.id')->getQuery()->getResult();
    }
}
