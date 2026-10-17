<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Entity;

use AdzerCMS\Core\Account\ConsentKind;
use AdzerCMS\Core\Repository\ConsentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function Symfony\Component\Clock\now;

#[ORM\Entity(repositoryClass: ConsentRepository::class)]
#[ORM\Table(name: 'consent')]
class Consent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 16, enumType: ConsentKind::class)]
    private ConsentKind $kind;

    #[ORM\Column(length: 32)]
    private string $revision;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $address;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $user,
        ConsentKind $kind,
        string $revision,
        ?string $address,
        ?\DateTimeImmutable $at = null,
    ) {
        $this->user = $user;
        $this->kind = $kind;
        $this->revision = $revision;
        $this->address = $address;
        $this->createdAt = $at ?? now();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getKind(): ConsentKind
    {
        return $this->kind;
    }

    public function getRevision(): string
    {
        return $this->revision;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
