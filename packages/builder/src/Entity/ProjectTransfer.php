<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Entity;

use AdzerCMS\Builder\Repository\ProjectTransferRepository;
use AdzerCMS\Core\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectTransferRepository::class)]
#[ORM\Table(name: 'builder_project_transfers')]
#[ORM\UniqueConstraint(name: 'uniq_builder_project_transfers_project', columns: ['project_id'])]
#[ORM\Index(name: 'idx_builder_project_transfers_recipient', columns: ['recipient_id', 'expires'])]
#[ORM\Index(name: 'idx_builder_project_transfers_expires', columns: ['expires'])]
class ProjectTransfer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Project $project,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $recipient,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $expires,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getRecipient(): User
    {
        return $this->recipient;
    }

    public function getExpires(): \DateTimeImmutable
    {
        return $this->expires;
    }
}
