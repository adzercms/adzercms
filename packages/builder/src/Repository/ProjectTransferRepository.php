<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Repository;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectTransfer;
use AdzerCMS\Core\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

use function Symfony\Component\Clock\now;

/** @extends ServiceEntityRepository<ProjectTransfer> */
class ProjectTransferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectTransfer::class);
    }

    public function pending(Project $project): ?ProjectTransfer
    {
        $transfer = $this->createQueryBuilder('t')->join('t.recipient', 'r')->addSelect('r')
            ->where('t.project = :project')->andWhere('t.expires > :now')
            ->setParameter('project', $project)->setParameter('now', now(), Types::DATETIME_IMMUTABLE)
            ->getQuery()->getOneOrNullResult();

        return $transfer instanceof ProjectTransfer ? $transfer : null;
    }

    /** @return list<ProjectTransfer> */
    public function offered(User $recipient): array
    {
        return $this->createQueryBuilder('t')->join('t.project', 'p')->addSelect('p')->join('p.owner', 'o')->addSelect('o')
            ->where('t.recipient = :recipient')->andWhere('t.expires > :now')
            ->setParameter('recipient', $recipient)->setParameter('now', now(), Types::DATETIME_IMMUTABLE)
            ->orderBy('t.expires')->addOrderBy('t.id')->getQuery()->getResult();
    }

    public function received(User $recipient, int $id): ?ProjectTransfer
    {
        return array_find($this->offered($recipient), static fn (ProjectTransfer $transfer): bool => $transfer->getId() === $id);
    }

    /** @return list<ProjectTransfer> */
    public function expired(): array
    {
        return $this->createQueryBuilder('t')->join('t.project', 'p')->addSelect('p')->join('p.owner', 'o')->addSelect('o')->join('t.recipient', 'r')->addSelect('r')
            ->where('t.expires <= :now')->setParameter('now', now(), Types::DATETIME_IMMUTABLE)
            ->orderBy('t.id')->getQuery()->getResult();
    }
}
