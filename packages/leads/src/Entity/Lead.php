<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Entity;

use AdzerCMS\Core\Entity\User;
use AdzerCMS\Leads\Repository\LeadRepository;
use AdzerCMS\Leads\Slice;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function Symfony\Component\Clock\now;

#[ORM\Entity(repositoryClass: LeadRepository::class)]
#[ORM\Table(name: 'leads')]
#[ORM\Index(name: 'idx_leads_stage_arrived', columns: ['stage_id', 'arrived'])]
#[ORM\Index(name: 'idx_leads_phone', columns: ['phone'])]
#[ORM\Index(name: 'idx_leads_email', columns: ['email'])]
#[ORM\Index(name: 'idx_leads_due', columns: ['due'])]
#[ORM\Index(name: 'idx_leads_topic', columns: ['topic'])]
class Lead
{
    public const TOPIC = 190;
    public const NAME = 190;
    public const PHONE = 32;
    public const EMAIL = 254;
    public const MESSAGE = 5000;
    public const PAGE = 255;
    public const REASON = 255;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Stage $stage;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $manager = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $due = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $amount = null;

    #[ORM\Column(length: self::REASON, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $arrived;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $staged;

    public function __construct(
        Stage $stage,
        #[ORM\Column(length: self::TOPIC, nullable: true)]
        private ?string $topic,
        #[ORM\Column(length: self::NAME, nullable: true)]
        private ?string $name,
        #[ORM\Column(length: self::PHONE, nullable: true)]
        private ?string $phone,
        #[ORM\Column(length: self::EMAIL, nullable: true)]
        private ?string $email,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $message,
        #[ORM\Column(length: self::PAGE, nullable: true)]
        private ?string $page = null,
    ) {
        $this->stage = $stage;
        $this->arrived = now();
        $this->staged = $this->arrived;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStage(): Stage
    {
        return $this->stage;
    }

    public function getTopic(): ?string
    {
        return $this->topic;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getPage(): ?string
    {
        return $this->page;
    }

    public function getManager(): ?User
    {
        return $this->manager;
    }

    public function getDue(): ?\DateTimeImmutable
    {
        return $this->due;
    }

    public function isOverdue(): bool
    {
        return null !== $this->due && $this->due < now() && Slice::Closed !== $this->stage->getGroup()->slice();
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function price(?string $amount): void
    {
        $this->amount = $amount;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getArrived(): \DateTimeImmutable
    {
        return $this->arrived;
    }

    public function getStaged(): \DateTimeImmutable
    {
        return $this->staged;
    }
}
