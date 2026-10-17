<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Entity;

use AdzerCMS\Core\Entity\User;
use AdzerCMS\Support\Repository\TicketMessageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketMessageRepository::class)]
#[ORM\Table(name: 'ticket_message')]
class TicketMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Ticket $ticket;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column]
    private bool $fromStaff;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $editedAt = null;

    /** @var Collection<int, TicketAttachment> */
    #[ORM\OneToMany(targetEntity: TicketAttachment::class, mappedBy: 'message', cascade: ['remove'])]
    #[ORM\OrderBy(['id' => \SortDirection::Ascending])]
    private Collection $attachments;

    public function __construct(Ticket $ticket, ?User $author, bool $fromStaff, string $body, \DateTimeImmutable $moment)
    {
        $this->ticket = $ticket;
        $this->author = $author;
        $this->fromStaff = $fromStaff;
        $this->body = $body;
        $this->createdAt = $moment;
        $this->attachments = new ArrayCollection();
        $ticket->getMessages()->add($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicket(): Ticket
    {
        return $this->ticket;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function isFromStaff(): bool
    {
        return $this->fromStaff;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function edit(string $body, \DateTimeImmutable $moment): self
    {
        $this->body = $body;
        $this->editedAt = $moment;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEditedAt(): ?\DateTimeImmutable
    {
        return $this->editedAt;
    }

    /** @return Collection<int, TicketAttachment> */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }
}
