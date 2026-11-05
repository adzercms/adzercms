<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Repository;

use AdzerCMS\Pages\Entity\Page;
use AdzerCMS\Pages\Entity\PagePath;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PagePath> */
class PagePathRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PagePath::class);
    }

    /** @return list<PagePath> */
    public function of(Page $page): array
    {
        return $this->createQueryBuilder('f')->where('f.page = :page')->setParameter('page', $page)->orderBy('f.id')->getQuery()->getResult();
    }

    /**
     * @param list<string> $paths
     *
     * @return list<PagePath>
     */
    public function held(array $paths): array
    {
        return [] === $paths ? [] : $this->createQueryBuilder('f')->addSelect('p')->innerJoin('f.page', 'p')->where('f.path IN (:paths)')->setParameter('paths', $paths)->getQuery()->getResult();
    }
}
