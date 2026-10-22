<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Repository;

use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\Entity\Stage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Lead> */
class LeadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lead::class);
    }

    public function holds(Stage $stage): bool
    {
        return null !== $this->createQueryBuilder('l')
            ->select('l.id')
            ->where('l.stage = :stage')
            ->setParameter('stage', $stage)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
