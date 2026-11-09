<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Entity;

use AdzerCMS\Core\Attachment\AttachmentRecord;
use AdzerCMS\Core\Entity\StoredFile;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'lead_attachment')]
class LeadAttachment implements AttachmentRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Lead $lead;

    #[ORM\Embedded(columnPrefix: false)]
    private StoredFile $file;

    public function __construct(Lead $lead, StoredFile $file)
    {
        $this->lead = $lead;
        $this->file = $file;
        $lead->getAttachments()->add($this);
    }

    public static function folder(): string
    {
        return 'leads';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLead(): Lead
    {
        return $this->lead;
    }

    public function getFile(): StoredFile
    {
        return $this->file;
    }
}
