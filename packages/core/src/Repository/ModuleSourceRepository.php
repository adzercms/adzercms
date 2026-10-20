<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Repository;

use AdzerCMS\Core\Entity\ModuleSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ModuleSource> */
class ModuleSourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModuleSource::class);
    }

    /** @return list<ModuleSource> */
    public function ordered(): array
    {
        return $this->findBy([], ['id' => 'ASC']);
    }
}
