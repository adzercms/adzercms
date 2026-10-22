<?php

declare(strict_types=1);

namespace AdzerCMS\Payments\Repository;

use AdzerCMS\Payments\Entity\Payment;
use AdzerCMS\Payments\PaymentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Payment> */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function findByNumber(string $number): ?Payment
    {
        return $this->findOneBy(['number' => $number]);
    }

    public function findByOperation(string $operationId): ?Payment
    {
        return $this->findOneBy(['operationId' => $operationId]);
    }

    /** @param list<string> $operations */
    public function confirmedOf(array $operations): ?Payment
    {
        return [] === $operations ? null : $this->createQueryBuilder('p')
            ->where('p.operationId IN (:operations)')
            ->andWhere('p.status = :confirmed')
            ->setParameter('operations', $operations)
            ->setParameter('confirmed', PaymentStatus::Confirmed->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Payment> */
    public function started(\DateTimeImmutable $after): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :pending')
            ->andWhere('p.createdAt >= :after')
            ->andWhere('p.operationId IS NOT NULL')
            ->setParameter('pending', PaymentStatus::Pending->value)
            ->setParameter('after', $after)
            ->orderBy('p.createdAt', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    public function cancelStale(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('p')
            ->update()
            ->set('p.status', ':cancelled')
            ->where('p.status = :pending')
            ->andWhere('p.createdAt < :before')
            ->setParameter('cancelled', PaymentStatus::Cancelled->value)
            ->setParameter('pending', PaymentStatus::Pending->value)
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
