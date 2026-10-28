<?php

declare(strict_types=1);

namespace AdzerCMS\Payments\Entity;

use AdzerCMS\Payments\Acquiring\PaymentOffer;
use AdzerCMS\Payments\PaymentStatus;
use AdzerCMS\Payments\Repository\PaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function Symfony\Component\Clock\now;

/** @phpstan-type Item array{name: string, quantity: int, price: string} */
#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payment')]
#[ORM\Index(name: 'idx_payment_status_time', columns: ['status', 'created_at'])]
#[ORM\Index(name: 'idx_payment_payer', columns: ['payer'])]
#[ORM\Index(name: 'idx_payment_status_expiry', columns: ['status', 'expires_at'])]
class Payment
{
    public const BINDING = 'binding';
    public const SAVED = 'saved';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private string $number;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $payer;

    /** @var list<Item> */
    #[ORM\Column(type: Types::JSON)]
    private array $items;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amount;

    #[ORM\Column(length: 16)]
    private string $method;

    #[ORM\Column(length: 255)]
    private string $description;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $phone;

    #[ORM\Column(length: 254, nullable: true)]
    private ?string $email;

    #[ORM\Column]
    private bool $remembers = false;

    #[ORM\Column]
    private bool $advance = false;

    #[ORM\Column(length: 64, nullable: true, unique: true)]
    private ?string $operationId = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $payUrl = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $payCode = null;

    #[ORM\Column(length: 2048)]
    private string $back;

    #[ORM\Column(length: 16, enumType: PaymentStatus::class)]
    private PaymentStatus $status = PaymentStatus::Pending;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    /** @param list<Item> $items */
    public function __construct(
        array $items,
        string $amount,
        string $method,
        string $description,
        ?string $phone,
        ?string $email,
        ?string $payer,
        string $back,
        \DateInterval $lifetime,
    ) {
        $this->number = bin2hex(random_bytes(16));
        $this->items = $items;
        $this->amount = $amount;
        $this->method = $method;
        $this->description = $description;
        $this->phone = $phone;
        $this->email = $email;
        $this->payer = $payer;
        $this->back = $back;
        $this->createdAt = now();
        $this->expiresAt = $this->createdAt->add($lifetime);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getPayer(): ?string
    {
        return $this->payer;
    }

    /** @return list<Item> */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function isBinding(): bool
    {
        return self::BINDING === $this->method;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function remembers(): bool
    {
        return $this->remembers;
    }

    public function remember(): self
    {
        $this->remembers = true;

        return $this;
    }

    public function isAdvance(): bool
    {
        return $this->advance;
    }

    public function markAdvance(bool $advance): self
    {
        $this->advance = $advance;

        return $this;
    }

    public function getOperationId(): ?string
    {
        return $this->operationId;
    }

    public function setOperationId(?string $operationId): self
    {
        $this->operationId = $operationId;

        return $this;
    }

    public function offer(): ?PaymentOffer
    {
        return match (true) {
            null !== $this->payCode => PaymentOffer::qr($this->payCode),
            null !== $this->payUrl => PaymentOffer::redirect($this->payUrl),
            default => null,
        };
    }

    public function keep(PaymentOffer $offer): self
    {
        $this->payUrl = $offer->url;
        $this->payCode = $offer->qr;

        return $this;
    }

    public function getBack(): string
    {
        return $this->back;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function confirm(string $amount, ?string $operationId): self
    {
        $this->status = PaymentStatus::Confirmed;
        $this->amount = $amount;
        $this->operationId = $operationId ?? $this->operationId;
        $this->confirmedAt = now();

        return $this;
    }
}
