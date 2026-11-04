<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Repository;

use AdzerCMS\Contracts\Account\Identifier;
use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Account\Role;
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

    /** @return list<User> */
    public function admins(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.role = :admin')
            ->andWhere('u.status <> :archived')
            ->setParameter('admin', Role::Admin)
            ->setParameter('archived', Status::Archived)
            ->orderBy('u.id', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /** @param list<int> $found */
    public function listing(string $term, bool $archive, array $found = []): QueryBuilder
    {
        $builder = $this->createQueryBuilder('s')
            ->where($archive ? 's.status = :archived' : 's.status <> :archived')
            ->setParameter('archived', Status::Archived);

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

        $phone = Filter::phone($builder, $term, $alias.'.phone');
        if (null !== $phone) {
            $match->add($phone);
        }

        $ids = [Filter::number($term), ...$found];
        foreach ($this->identifiers as $identifier) {
            $ids[] = $identifier->users([$term])[$term] ?? null;
        }
        $ids = array_values(array_filter($ids, static fn (?int $id): bool => null !== $id));
        if ([] !== $ids) {
            $match->add(\sprintf('%s.id IN (:ids)', $alias));
            $builder->setParameter('ids', $ids);
        }

        return $match;
    }

    public function firstYear(\DateTimeImmutable $fallback): int
    {
        $first = $this->createQueryBuilder('s')->select('MIN(s.createdAt)')->getQuery()->getSingleScalarResult();

        return (int) (\is_string($first) ? new \DateTimeImmutable($first) : $fallback)->format('Y');
    }

    /** @return array{users: int, archive: int} */
    public function counts(): array
    {
        $row = $this->createQueryBuilder('s')
            ->select('COUNT(s.id) AS total')
            ->addSelect('SUM(CASE WHEN s.status = :archived THEN 1 ELSE 0 END) AS archive')
            ->setParameter('archived', Status::Archived)
            ->getQuery()
            ->getSingleResult();
        $archive = (int) $row['archive'];

        return ['users' => (int) $row['total'] - $archive, 'archive' => $archive];
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
        return $this->identifiedMany([$key])[$key] ?? null;
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, User>
     */
    public function identifiedMany(array $keys): array
    {
        $ids = [];
        foreach ($this->identifiers as $identifier) {
            $rest = array_values(array_diff($keys, array_map(strval(...), array_keys($ids))));
            if ([] === $rest) {
                break;
            }
            $ids += $identifier->users($rest);
        }
        if ([] === $ids) {
            return [];
        }

        $users = [];
        foreach ($this->findBy(['id' => array_values(array_unique($ids))]) as $user) {
            $users[(int) $user->getId()] = $user;
        }

        return array_filter(array_map(static fn (int $id): ?User => $users[$id] ?? null, $ids));
    }
}
