<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Repository;

use AdzerCMS\Contracts\Account\Identifier;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Account\Status;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Ui\Filter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** @extends ServiceEntityRepository<User> */
final class UserRepository extends ServiceEntityRepository
{
    /** @param iterable<Identifier> $identifiers */
    public function __construct(
        ManagerRegistry $registry,
        #[AutowireIterator(Identifier::class)]
        private readonly iterable $identifiers,
    ) {
        parent::__construct($registry, User::class);
    }

    /** @return list<User> */
    public function unarchived(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.status <> :archived')
            ->setParameter('archived', Status::Archived)
            ->orderBy('u.id', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /** @param list<int> $found */
    public function listing(string $term, bool $archive, ?Status $status = null, array $found = []): QueryBuilder
    {
        $builder = $this->createQueryBuilder('s')
            ->where($archive ? 's.status = :archived' : 's.status <> :archived')
            ->setParameter('archived', Status::Archived);

        if (null !== $status) {
            $builder->andWhere('s.status = :status')->setParameter('status', $status);
        }

        $term = trim($term);

        return '' === $term ? $builder : $builder->andWhere($this->matchTerm($builder, 's', $term, $found));
    }

    /**
     * @param list<int>    $found
     * @param list<string> $fields
     */
    public function matchTerm(QueryBuilder $builder, string $alias, string $term, array $found = [], array $fields = []): Orx
    {
        foreach (['email', 'comment', 'note', 'lastName', 'firstName', 'middleName'] as $field) {
            $fields[] = \sprintf('%s.%s', $alias, $field);
        }
        $match = Filter::matching($builder, $term, $fields);

        $digits = (string) preg_replace('/\D/', '', $term);
        if ('' !== $digits) {
            $match->add(\sprintf('%s.phone LIKE :digits', $alias));
            $builder->setParameter('digits', '%'.$digits.'%');
        }

        $ids = [Filter::number($term), ...$found];
        foreach ($this->identifiers as $identifier) {
            $ids[] = $identifier->user($term);
        }
        $ids = array_values(array_filter($ids, static fn (?int $id): bool => null !== $id));
        if ([] !== $ids) {
            $match->add(\sprintf('%s.id IN (:ids)', $alias));
            $builder->setParameter('ids', $ids);
        }

        return $match;
    }

    public function total(string $term, bool $archive, ?Status $status = null): int
    {
        return (int) $this->listing($term, $archive, $status)->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();
    }

    public function firstYear(\DateTimeImmutable $fallback): int
    {
        $first = $this->createQueryBuilder('s')->select('MIN(s.createdAt)')->getQuery()->getSingleScalarResult();

        return (int) (\is_string($first) ? new \DateTimeImmutable($first) : $fallback)->format('Y');
    }

    /** @return array{users: int, archive: int} */
    public function counts(): array
    {
        return ['users' => $this->total('', false), 'archive' => $this->total('', true)];
    }

    public function byLogin(string $login): ?User
    {
        $kind = ContactKind::of($login);
        $contact = $kind->normalize($login);
        if (null !== $contact) {
            return $this->findOneBy([match ($kind) {
                ContactKind::Phone => 'phone',
                ContactKind::Email => 'email',
            } => $contact]);
        }

        return $this->identified(trim($login));
    }

    public function identified(string $key): ?User
    {
        foreach ($this->identifiers as $identifier) {
            $id = $identifier->user($key);
            if (null !== $id) {
                return $this->find($id);
            }
        }

        return null;
    }
}
