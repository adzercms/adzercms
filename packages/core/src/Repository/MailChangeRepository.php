<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Repository;

use AdzerCMS\Core\Entity\MailChange;
use AdzerCMS\Core\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MailChange> */
final class MailChangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailChange::class);
    }

    public function of(User $user): ?MailChange
    {
        return $this->findOneBy(['user' => $user]);
    }

    /** @return list<MailChange> */
    public function due(\DateTimeImmutable $moment): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('u')
            ->join('m.user', 'u')
            ->where('m.dueAt <= :moment')
            ->setParameter('moment', $moment, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getResult();
    }
}
