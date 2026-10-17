<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Repository;

use AdzerCMS\Core\Confirmation\Kind;
use AdzerCMS\Core\Confirmation\Purpose;
use AdzerCMS\Core\Entity\Confirmation;
use AdzerCMS\Core\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Confirmation> */
final class ConfirmationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Confirmation::class);
    }

    public function last(Purpose $purpose, Kind $kind, User|string $addressee): ?Confirmation
    {
        return $this->findOneBy([$addressee instanceof User ? 'user' : 'contact' => $addressee, 'purpose' => $purpose, 'kind' => $kind], ['id' => 'DESC']);
    }

    public function link(Purpose $purpose, string $secretHash): ?Confirmation
    {
        return $this->findOneBy(['secretHash' => $secretHash, 'purpose' => $purpose, 'kind' => Kind::Link]);
    }

    public function bot(string $service, string $secretHash): ?Confirmation
    {
        return $this->findOneBy(['secretHash' => $secretHash, 'purpose' => Purpose::Messenger, 'kind' => Kind::Bot, 'service' => $service]);
    }

    public function sweep(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('c')
            ->delete()
            ->where('c.expiresAt < :before')
            ->setParameter('before', $before, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->execute();
    }
}
