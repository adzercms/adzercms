<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Repository;

use AdzerCMS\Support\Entity\HelpArticle;
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

    /** @return list<HelpCategory> */
    public function shown(): array
    {
        return $this->createQueryBuilder('c')
            ->where(\sprintf('EXISTS (SELECT a.id FROM %s a WHERE a.category = c AND a.published = true)', HelpArticle::class))
            ->orderBy('c.position')->addOrderBy('c.id')->getQuery()->getResult();
    }
}
