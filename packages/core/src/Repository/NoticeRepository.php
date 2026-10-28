<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Repository;

use AdzerCMS\Core\Entity\Notice;
use AdzerCMS\Core\Notice\Status;
use AdzerCMS\Core\Ui\Table;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Notice> */
final class NoticeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly UserRepository $users)
    {
        parent::__construct($registry, Notice::class);
    }

    public function settled(string $idempotency): bool
    {
        return null !== $this->createQueryBuilder('n')
            ->select('1')
            ->where('n.idempotency = :idempotency')
            ->setParameter('idempotency', $idempotency)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Notice> */
    public function deferred(): array
    {
        return $this->findBy(['status' => Status::Deferred], ['id' => 'ASC']);
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Notice>
     */
    public function numbered(array $ids): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.user', 'u')
            ->addSelect('u')
            ->where('n.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    public function sweep(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.createdAt < :before')
            ->andWhere('n.status <> :deferred')
            ->setParameter('before', $before, Types::DATETIME_IMMUTABLE)
            ->setParameter('deferred', Status::Deferred)
            ->getQuery()
            ->execute();
    }

    /** @return list<string> */
    public function events(): array
    {
        return array_column($this->createQueryBuilder('n')
            ->select('DISTINCT n.event')
            ->orderBy('n.event', \SortDirection::Ascending)
            ->getQuery()
            ->getScalarResult(), 'event');
    }

    /** @return list<Notice> */
    public function journal(string $channel, Table $table, ?Status $status): array
    {
        $builder = $this->createQueryBuilder('n')
            ->leftJoin('n.user', 'u')
            ->addSelect('u')
            ->where('n.channel = :channel')
            ->setParameter('channel', $channel);

        if ('' !== $table->value('event')) {
            $builder->andWhere('n.event = :event')->setParameter('event', $table->value('event'));
        }
        if (null !== $status) {
            $builder->andWhere('n.status = :status')->setParameter('status', $status);
        }
        if ('' !== $table->term()) {
            $builder->andWhere($this->users->matchTerm($builder, 'u', $table->term(), fields: ['n.recipient', 'n.body']));
        }

        $table->total((int) (clone $builder)->select('COUNT(n.id)')->getQuery()->getSingleScalarResult());

        $table->apply($builder);

        return $builder
            ->addOrderBy('n.id', \SortDirection::Descending)
            ->setMaxResults($table->size)
            ->setFirstResult($table->offset())
            ->getQuery()
            ->getResult();
    }
}
