<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Entity;

use AdzerCMS\Core\Attachment\AttachmentRecord;
use AdzerCMS\Core\Entity\StoredFile;
use AdzerCMS\Support\Repository\TicketAttachmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketAttachmentRepository::class)]
#[ORM\Table(name: 'ticket_attachment')]
class TicketAttachment implements AttachmentRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TicketMessage $message;

    #[ORM\Embedded(columnPrefix: false)]
    private StoredFile $file;

    public function __construct(TicketMessage $message, StoredFile $file)
    {
        $this->message = $message;
        $this->file = $file;
        $message->getAttachments()->add($this);
    }

    public static function folder(): string
    {
        return 'tickets';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): TicketMessage
    {
        return $this->message;
    }

    public function getFile(): StoredFile
    {
        return $this->file;
    }
}
