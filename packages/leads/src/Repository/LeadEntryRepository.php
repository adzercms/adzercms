<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Repository;

use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\Entity\LeadEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<LeadEntry> */
class LeadEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeadEntry::class);
    }

    /** @return list<LeadEntry> */
    public function feed(Lead $lead): array
    {
        /** @var list<LeadEntry> $entries */
        $entries = $this->createQueryBuilder('e')
            ->addSelect('a')
            ->leftJoin('e.author', 'a')
            ->where('e.lead = :lead')
            ->setParameter('lead', $lead)
            ->orderBy('e.id')
            ->getQuery()
            ->getResult();

        return $entries;
    }
}
