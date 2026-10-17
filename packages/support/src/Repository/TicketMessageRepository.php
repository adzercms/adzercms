<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Repository;

use AdzerCMS\Support\Entity\TicketMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TicketMessage> */
class TicketMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TicketMessage::class);
    }
}
