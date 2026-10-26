<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Entity;

use AdzerCMS\Core\Repository\MailChangeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MailChangeRepository::class)]
#[ORM\Table(name: 'mail_change')]
#[ORM\UniqueConstraint(name: 'uniq_mail_change_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_mail_change_due', columns: ['due_at'])]
class MailChange
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 254)]
    private string $address;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dueAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        string $address,
        \DateTimeImmutable $dueAt,
    ) {
        $this->await($address, $dueAt);
    }

    public function await(string $address, \DateTimeImmutable $dueAt): self
    {
        $this->address = $address;
        $this->dueAt = $dueAt;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getDueAt(): \DateTimeImmutable
    {
        return $this->dueAt;
    }
}
