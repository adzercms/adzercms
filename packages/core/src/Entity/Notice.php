<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Entity;

use AdzerCMS\Core\Notice\Letter;
use AdzerCMS\Core\Notice\Status;
use AdzerCMS\Core\Repository\NoticeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function Symfony\Component\Clock\now;

#[ORM\Entity(repositoryClass: NoticeRepository::class)]
#[ORM\Table(name: 'notice')]
#[ORM\UniqueConstraint(name: 'uniq_notice_idempotency', columns: ['idempotency'])]
#[ORM\Index(name: 'idx_notice_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_notice_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_notice_channel_created_at', columns: ['channel', 'created_at'])]
#[ORM\Index(name: 'idx_notice_status', columns: ['status'])]
class Notice
{
    public const ERROR_LIMIT = 255;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16, enumType: Status::class)]
    private Status $status = Status::Sent;

    #[ORM\Column(length: self::ERROR_LIMIT, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $idempotency = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $html = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private ?User $user,
        #[ORM\Column(length: 64)]
        private string $event,
        #[ORM\Column(length: 32)]
        private string $channel,
        #[ORM\Column(length: 254)]
        private string $recipient,
        #[ORM\Column(type: Types::TEXT)]
        private string $body,
    ) {
        $this->createdAt = now();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function defer(Letter $letter): self
    {
        $this->status = Status::Deferred;
        $this->subject = $letter->subject;
        $this->html = $letter->html;

        return $this;
    }

    public function letter(): Letter
    {
        return new Letter((string) $this->subject, $this->body, (string) $this->html);
    }

    public function sent(): self
    {
        return $this->conclude(Status::Sent);
    }

    public function fail(string $error): self
    {
        $this->error = mb_substr($error, 0, self::ERROR_LIMIT);

        return $this->conclude(Status::Failed);
    }

    public function settle(?string $idempotency): self
    {
        $this->idempotency = $idempotency;

        return $this;
    }

    private function conclude(Status $status): self
    {
        $this->status = $status;
        $this->subject = null;
        $this->html = null;

        return $this;
    }
}
