<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Repository;

use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Repository\UserRepository;
use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Support\Entity\Ticket;
use AdzerCMS\Support\TicketStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Ticket> */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly UserRepository $users)
    {
        parent::__construct($registry, Ticket::class);
    }

    /**
     * @param array<string, string> $filters
     *
     * @return list<Ticket>
     */
    public function page(?User $user, bool $archive, array $filters, Table $table): array
    {
        $builder = $this->listing($user, $archive, $filters);
        $table->apply($builder)->total($this->total($user, $archive, $filters));

        return $builder
            ->addOrderBy('t.id', \SortDirection::Descending)
            ->setMaxResults($table->size)
            ->setFirstResult($table->offset())
            ->getQuery()
            ->getResult();
    }

    /** @param array<string, string> $filters */
    public function total(?User $user, bool $archive, array $filters): int
    {
        return (int) $this->listing($user, $archive, $filters)
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array{active: int, archive: int} */
    public function counts(?User $user): array
    {
        $builder = $this->createQueryBuilder('t')
            ->select('COUNT(t.id) AS total')
            ->addSelect('SUM(CASE WHEN t.status = :closed THEN 1 ELSE 0 END) AS archive')
            ->setParameter('closed', TicketStatus::Closed);

        if (null !== $user) {
            $builder->where('t.user = :user')->setParameter('user', $user);
        }

        $row = $builder->getQuery()->getSingleResult();
        $archive = (int) $row['archive'];

        return ['active' => (int) $row['total'] - $archive, 'archive' => $archive];
    }

    /** @param list<TicketStatus> $statuses */
    public function countByStatus(?User $user, array $statuses): int
    {
        $builder = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.status IN (:statuses)')
            ->setParameter('statuses', $statuses);

        if (null !== $user) {
            $builder->andWhere('t.user = :user')->setParameter('user', $user);
        }

        return (int) $builder->getQuery()->getSingleScalarResult();
    }

    public function reachable(int $id, ?User $owner): ?Ticket
    {
        return $this->reach($id, $owner)->getQuery()->getOneOrNullResult();
    }

    public function conversation(int $id, ?User $owner): ?Ticket
    {
        return $this->reach($id, $owner)
            ->leftJoin('t.messages', 'm')
            ->addSelect('m')
            ->leftJoin('m.attachments', 'a')
            ->addSelect('a')
            ->addOrderBy('m.id', \SortDirection::Ascending)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Ticket> */
    public function staleWaiting(\DateTimeImmutable $deadline): array
    {
        return $this->createQueryBuilder('t')
            ->addSelect('u')
            ->join('t.user', 'u')
            ->where('t.status = :waiting')
            ->andWhere('t.lastMessageAt < :deadline')
            ->setParameter('waiting', TicketStatus::WaitingUser)
            ->setParameter('deadline', $deadline)
            ->orderBy('t.id', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Ticket> */
    public function awaitingStaff(bool $unnotifiedOnly): array
    {
        $builder = $this->createQueryBuilder('t')
            ->addSelect('u')
            ->join('t.user', 'u')
            ->where('t.status IN (:waiting)')
            ->setParameter('waiting', [TicketStatus::Open, TicketStatus::WaitingStaff])
            ->orderBy('t.lastMessageAt', \SortDirection::Ascending)
            ->addOrderBy('t.id', \SortDirection::Ascending);

        if ($unnotifiedOnly) {
            $builder->andWhere('t.staffNotifiedAt IS NULL');
        }

        return $builder->getQuery()->getResult();
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Ticket>
     */
    public function byIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /** @return list<string> */
    public function departments(?User $owner): array
    {
        $builder = $this->createQueryBuilder('t')
            ->select('DISTINCT t.department')
            ->orderBy('t.department', \SortDirection::Ascending);

        if (null !== $owner) {
            $builder->where('t.user = :owner')->setParameter('owner', $owner);
        }

        return array_column($builder->getQuery()->getScalarResult(), 'department');
    }

    private function reach(int $id, ?User $owner): QueryBuilder
    {
        $builder = $this->createQueryBuilder('t')
            ->leftJoin('t.user', 's')
            ->addSelect('s')
            ->where('t.id = :id')
            ->setParameter('id', $id);

        if (null !== $owner) {
            $builder->andWhere('t.user = :owner')->setParameter('owner', $owner);
        }

        return $builder;
    }

    /** @param array<string, string> $filters */
    private function listing(?User $user, bool $archive, array $filters): QueryBuilder
    {
        $builder = $this->createQueryBuilder('t')
            ->leftJoin('t.user', 's')
            ->addSelect('s');

        $builder
            ->where($archive ? 't.status = :closed' : 't.status != :closed')
            ->setParameter('closed', TicketStatus::Closed);

        if (null !== $user) {
            $builder->andWhere('t.user = :user')->setParameter('user', $user);
        }

        if (!empty($filters['department'])) {
            $builder->andWhere('t.department = :department')->setParameter('department', $filters['department']);
        }

        if (!empty($filters['status'])) {
            $status = TicketStatus::tryFrom((string) $filters['status']);
            if (null !== $status) {
                $builder->andWhere('t.status = :status')->setParameter('status', $status);
            }
        }

        $term = trim($filters['search'] ?? '');
        if ('' === $term) {
            return $builder;
        }

        $match = null === $user
            ? $this->users->matchTerm($builder, 's', $term, fields: ['t.subject'])
            : Filter::matching($builder, $term, ['t.subject']);

        $number = Filter::number($term);
        if (null !== $number) {
            $match->add('t.id = :number');
            $builder->setParameter('number', $number);
        }

        return $builder->andWhere($match);
    }
}
