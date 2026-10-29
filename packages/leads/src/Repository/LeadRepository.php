<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Repository;

use AdzerCMS\Core\Ui\Filter;
use AdzerCMS\Leads\Entity\Lead;
use AdzerCMS\Leads\Entity\Stage;
use AdzerCMS\Leads\Slice;
use AdzerCMS\Leads\StageGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

use function Symfony\Component\Clock\now;

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

    public function total(Slice $slice): int
    {
        return (int) self::sliced($this->createQueryBuilder('l')->select('COUNT(l.id)')->join('l.stage', 's'), $slice)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Lead> */
    public function overdue(): array
    {
        /** @var list<Lead> $leads */
        $leads = self::sliced($this->createQueryBuilder('l')->join('l.stage', 's'), Slice::Overdue)
            ->orderBy('l.due', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();

        return $leads;
    }

    /** @return list<string> */
    public function topics(): array
    {
        return array_values(array_map(strval(...), $this->createQueryBuilder('l')
            ->select('DISTINCT l.topic')
            ->where('l.topic IS NOT NULL')
            ->orderBy('l.topic')
            ->getQuery()
            ->getSingleColumnResult()));
    }

    /** @return list<Lead> */
    public function sameClient(Lead $lead): array
    {
        $builder = $this->createQueryBuilder('l')
            ->addSelect('s')
            ->join('l.stage', 's')
            ->where('l.id <> :lead')
            ->setParameter('lead', $lead->getId())
            ->orderBy('l.id', \SortDirection::Descending);

        $match = $builder->expr()->orX();
        foreach (['phone' => $lead->getPhone(), 'email' => $lead->getEmail()] as $field => $contact) {
            if (null !== $contact) {
                $match->add('l.'.$field.' = :'.$field);
                $builder->setParameter($field, $contact);
            }
        }
        if (0 === $match->count()) {
            return [];
        }

        /** @var list<Lead> $leads */
        $leads = $builder->andWhere($match)->getQuery()->getResult();

        return $leads;
    }

    /** @param list<int> $found */
    public function listing(Slice $slice, string $term, array $found): QueryBuilder
    {
        $builder = self::sliced($this->createQueryBuilder('l')->join('l.stage', 's')->leftJoin('l.manager', 'm'), $slice);

        $term = trim($term);
        if ('' === $term) {
            return $builder;
        }

        $match = Filter::matching($builder, $term, ['l.name', 'l.email', 'l.message']);
        $phone = Filter::phone($builder, $term, 'l.phone');
        if (null !== $phone) {
            $match->add($phone);
        }
        $ids = array_values(array_filter([Filter::number($term), ...$found], static fn (?int $id): bool => null !== $id));
        if ([] !== $ids) {
            $match->add('l.id IN (:ids)');
            $builder->setParameter('ids', $ids);
        }

        return $builder->andWhere($match);
    }

    private static function sliced(QueryBuilder $builder, Slice $slice): QueryBuilder
    {
        $builder->andWhere('s.group IN (:groups)')
            ->setParameter('groups', array_map(static fn (StageGroup $group): string => $group->value, $slice->groups()));

        $since = $slice->since();
        if (null !== $since) {
            $builder->andWhere('l.arrived >= :since')->setParameter('since', $since);
        }
        if (Slice::Overdue === $slice) {
            $builder->andWhere('l.due < :now')->setParameter('now', now());
        }

        return $builder;
    }
}
