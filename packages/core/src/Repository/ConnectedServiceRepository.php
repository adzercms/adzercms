<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Repository;

use AdzerCMS\Core\Entity\ConnectedService;
use AdzerCMS\Core\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ConnectedService> */
final class ConnectedServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConnectedService::class);
    }

    public function profile(string $service, string $profile): ?ConnectedService
    {
        return $this->findOneBy(['service' => $service, 'profile' => $profile]);
    }

    /** @return array<string, ConnectedService> */
    public function of(User $user): array
    {
        $connected = [];
        foreach ($this->findBy(['user' => $user]) as $service) {
            $connected[$service->getService()] = $service;
        }

        return $connected;
    }

    /**
     * @param list<User> $users
     *
     * @return array<int, string>
     */
    public function profiles(string $service, array $users): array
    {
        $saved = array_values(array_filter($users, static fn (User $user): bool => null !== $user->getId()));
        if ([] === $saved) {
            return [];
        }

        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.user) AS user, c.profile')
            ->where('c.service = :service AND c.user IN (:users)')
            ->setParameter('service', $service)
            ->setParameter('users', $saved)
            ->getQuery()
            ->getArrayResult();

        $profiles = [];
        foreach ($rows as $row) {
            $profiles[(int) $row['user']] = (string) $row['profile'];
        }

        return $profiles;
    }
}
