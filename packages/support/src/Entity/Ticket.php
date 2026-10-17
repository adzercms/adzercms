<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Entity;

use AdzerCMS\Core\Entity\User;
use AdzerCMS\Support\Repository\TicketRepository;
use AdzerCMS\Support\TicketStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\Table(name: 'ticket')]
#[ORM\Index(name: 'IDX_ticket_status', columns: ['status', 'last_message_at'])]
class Ticket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 190)]
    private string $subject;

    #[ORM\Column(length: 64)]
    private string $department;

    #[ORM\Column(length: 24, enumType: TicketStatus::class)]
    private TicketStatus $status = TicketStatus::Open;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lastMessageAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $staffNotifiedAt = null;

    /** @var Collection<int, TicketMessage> */
    #[ORM\OneToMany(targetEntity: TicketMessage::class, mappedBy: 'ticket', cascade: ['remove'])]
    #[ORM\OrderBy(['id' => \SortDirection::Ascending])]
    private Collection $messages;

    public function __construct(User $user, string $subject, string $department, \DateTimeImmutable $moment)
    {
        $this->user = $user;
        $this->subject = $subject;
        $this->department = $department;
        $this->createdAt = $moment;
        $this->lastMessageAt = $moment;
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getDepartment(): string
    {
        return $this->department;
    }

    public function getStatus(): TicketStatus
    {
        return $this->status;
    }

    public function setStatus(TicketStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStaffNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->staffNotifiedAt;
    }

    public function staffNotified(\DateTimeImmutable $moment): self
    {
        $this->staffNotifiedAt = $moment;

        return $this;
    }

    public function getLastMessageAt(): \DateTimeImmutable
    {
        return $this->lastMessageAt;
    }

    public function touch(\DateTimeImmutable $moment): self
    {
        $this->lastMessageAt = $moment;

        return $this;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function close(\DateTimeImmutable $moment): self
    {
        $this->status = TicketStatus::Closed;
        $this->closedAt = $moment;

        return $this;
    }

    /** @return Collection<int, TicketMessage> */
    public function getMessages(): Collection
    {
        return $this->messages;
    }
}
