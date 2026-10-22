<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Entity;

use AdzerCMS\Core\Entity\User;
use AdzerCMS\Leads\Repository\LeadEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function Symfony\Component\Clock\now;

#[ORM\Entity(repositoryClass: LeadEntryRepository::class)]
#[ORM\Table(name: 'lead_entry')]
class LeadEntry
{
    public const NOTE = 5000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $written;

    /** @param list<array{field: string, was: ?string, now: ?string}>|null $changes */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Lead $lead,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?User $author,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $note = null,
        #[ORM\Column(type: Types::JSON, nullable: true)]
        private ?array $changes = null,
    ) {
        $this->written = now();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLead(): Lead
    {
        return $this->lead;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function getWritten(): \DateTimeImmutable
    {
        return $this->written;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    /** @return list<array{field: string, was: ?string, now: ?string}> */
    public function getChanges(): array
    {
        return $this->changes ?? [];
    }
}
