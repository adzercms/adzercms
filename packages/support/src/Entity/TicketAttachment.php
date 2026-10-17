<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Entity;

use AdzerCMS\Support\Repository\TicketAttachmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketAttachmentRepository::class)]
#[ORM\Table(name: 'ticket_attachment')]
class TicketAttachment
{
    private const PICTURES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TicketMessage $message;

    #[ORM\Column(length: 190)]
    private string $name;

    #[ORM\Column(length: 64)]
    private string $storedName;

    #[ORM\Column(length: 128)]
    private string $mime;

    #[ORM\Column]
    private int $size;

    public function __construct(TicketMessage $message, string $name, string $storedName, string $mime, int $size)
    {
        $this->message = $message;
        $this->name = $name;
        $this->storedName = $storedName;
        $this->mime = $mime;
        $this->size = $size;
        $message->getAttachments()->add($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): TicketMessage
    {
        return $this->message;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStoredName(): string
    {
        return $this->storedName;
    }

    public function getMime(): string
    {
        return $this->mime;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function isPicture(): bool
    {
        return \in_array($this->mime, self::PICTURES, true);
    }

    public function isInline(): bool
    {
        return $this->isPicture() || 'application/pdf' === $this->mime;
    }
}
