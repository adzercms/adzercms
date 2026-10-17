<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Repository;

use AdzerCMS\Support\Entity\TicketAttachment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TicketAttachment> */
class TicketAttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TicketAttachment::class);
    }
}
