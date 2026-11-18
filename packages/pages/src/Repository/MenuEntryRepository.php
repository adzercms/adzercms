<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Repository;

use AdzerCMS\Pages\Entity\MenuEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MenuEntry> */
class MenuEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuEntry::class);
    }

    /** @return list<MenuEntry> */
    public function ordered(): array
    {
        return $this->createQueryBuilder('m')->leftJoin('m.page', 'p')->addSelect('p')->orderBy('m.position')->addOrderBy('m.id')->getQuery()->getResult();
    }
}
