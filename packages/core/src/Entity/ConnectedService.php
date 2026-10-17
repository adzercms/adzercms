<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Entity;

use AdzerCMS\Core\Repository\ConnectedServiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function Symfony\Component\Clock\now;

#[ORM\Entity(repositoryClass: ConnectedServiceRepository::class)]
#[ORM\Table(name: 'connected_service')]
#[ORM\UniqueConstraint(name: 'uniq_connected_service_profile', columns: ['service', 'profile'])]
#[ORM\UniqueConstraint(name: 'uniq_connected_service_user', columns: ['user_id', 'service'])]
#[ORM\Index(name: 'idx_connected_service_user', columns: ['user_id'])]
class ConnectedService
{
    public const ACCOUNT_LIMIT = 64;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: self::ACCOUNT_LIMIT, nullable: true)]
    private ?string $account = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(length: 32)]
        private string $service,
        #[ORM\Column(length: 64)]
        private string $profile,
        ?string $account,
    ) {
        $this->createdAt = now();
        $this->name($account);
    }

    public function name(?string $account): self
    {
        $account = trim((string) $account);
        if ('' !== $account) {
            $this->account = mb_substr($account, 0, self::ACCOUNT_LIMIT);
        }

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getService(): string
    {
        return $this->service;
    }

    public function getProfile(): string
    {
        return $this->profile;
    }

    public function getAccount(): ?string
    {
        return $this->account;
    }
}
