<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Entity;

use AdzerCMS\Builder\Repository\ProjectRepository;
use AdzerCMS\Core\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function Symfony\Component\Clock\now;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'builder_projects')]
#[ORM\Index(name: 'idx_builder_projects_owner', columns: ['owner_id', 'edited'])]
class Project
{
    public const NAME = 120;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $edited;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $owner,
        #[ORM\Column(length: self::NAME)]
        private string $name,
    ) {
        $this->edited = now();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEdited(): \DateTimeImmutable
    {
        return $this->edited;
    }

    public function touch(): void
    {
        $this->edited = now();
    }

    public function handOver(User $owner): void
    {
        $this->owner = $owner;
        $this->touch();
    }
}
