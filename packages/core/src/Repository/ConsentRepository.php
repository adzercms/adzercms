<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Repository;

use AdzerCMS\Core\Entity\Consent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Consent> */
class ConsentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consent::class);
    }
}
