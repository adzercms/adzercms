<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Repository;

use AdzerCMS\Core\Entity\Login;
use AdzerCMS\Core\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

use function Symfony\Component\Clock\now;

/** @extends ServiceEntityRepository<Login> */
final class LoginRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Login::class);
    }

    public function seen(string $handle): ?\DateTimeImmutable
    {
        $seen = $this->createQueryBuilder('l')
            ->select('l.seenAt')
            ->where('l.handle = :handle')
            ->setParameter('handle', $handle)
            ->getQuery()
            ->getOneOrNullResult();

        return $seen['seenAt'] ?? null;
    }

    public function see(string $handle): void
    {
        $this->createQueryBuilder('l')
            ->update()
            ->set('l.seenAt', ':now')
            ->where('l.handle = :handle')
            ->setParameter('now', now(), Types::DATETIME_IMMUTABLE)
            ->setParameter('handle', $handle)
            ->getQuery()
            ->execute();
    }

    public function remark(string $handle, User $user): void
    {
        $mark = $user->mark();
        $this->createQueryBuilder('l')
            ->update()
            ->set('l.mark', ':mark')
            ->set('l.email', ':email')
            ->where('l.handle = :handle')
            ->andWhere('l.user = :user')
            ->setParameter('mark', $mark->key)
            ->setParameter('email', $mark->email)
            ->setParameter('handle', $handle)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /** @return list<Login> */
    public function of(User $user): array
    {
        return $this->findBy(['user' => $user], ['seenAt' => 'DESC']);
    }

    /** @param list<string> $handles */
    public function drop(User $user, array $handles): int
    {
        if ([] === $handles) {
            return 0;
        }

        return $this->deletion()
            ->where('l.user = :user')
            ->andWhere('l.handle IN (:handles)')
            ->setParameter('user', $user)
            ->setParameter('handles', $handles, ArrayParameterType::STRING)
            ->getQuery()
            ->execute();
    }

    public function dropOthers(User $user, ?string $kept): int
    {
        $deletion = $this->deletion()->where('l.user = :user')->setParameter('user', $user);
        if (null !== $kept) {
            $deletion->andWhere('l.handle <> :kept')->setParameter('kept', $kept);
        }

        return $deletion->getQuery()->execute();
    }

    public function close(string $handle): void
    {
        $this->deletion()
            ->where('l.handle = :handle')
            ->setParameter('handle', $handle)
            ->getQuery()
            ->execute();
    }

    public function sweep(\DateTimeImmutable $before): int
    {
        return $this->deletion()
            ->where('l.seenAt < :before')
            ->setParameter('before', $before, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->execute();
    }

    private function deletion(): QueryBuilder
    {
        return $this->createQueryBuilder('l')->delete();
    }
}
