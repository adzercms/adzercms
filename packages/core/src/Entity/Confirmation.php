<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Entity;

use AdzerCMS\Core\Confirmation\Kind;
use AdzerCMS\Core\Confirmation\Purpose;
use AdzerCMS\Core\Repository\ConfirmationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function Symfony\Component\Clock\now;

#[ORM\Entity(repositoryClass: ConfirmationRepository::class)]
#[ORM\Table(name: 'confirmation')]
#[ORM\Index(name: 'idx_confirmation_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_confirmation_contact', columns: ['contact'])]
#[ORM\Index(name: 'idx_confirmation_secret_hash', columns: ['secret_hash'])]
class Confirmation
{
    public const ATTEMPTS = 5;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $attempts = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $service = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private ?User $user,
        #[ORM\Column(length: 254, nullable: true)]
        private ?string $contact,
        #[ORM\Column(length: 16, enumType: Purpose::class)]
        private Purpose $purpose,
        #[ORM\Column(length: 16, enumType: Kind::class)]
        private Kind $kind,
        #[ORM\Column(length: 255)]
        private string $secretHash,
    ) {
        $this->createdAt = now();
        $this->expiresAt = $this->createdAt->modify(\sprintf('+%d minutes', $kind->minutes()));
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function requireUser(): User
    {
        return $this->user ?? throw new \LogicException('confirmation has no user');
    }

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function getSecretHash(): string
    {
        return $this->secretHash;
    }

    public function forService(string $service): self
    {
        $this->service = $service;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function usable(): bool
    {
        return null === $this->usedAt && $this->attempts < self::ATTEMPTS && $this->expiresAt >= now();
    }

    public function attempt(): self
    {
        ++$this->attempts;

        return $this;
    }

    public function use(): self
    {
        $this->usedAt = now();

        return $this;
    }
}
