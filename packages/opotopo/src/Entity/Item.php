<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Opotopo\SellerApi\Sync\ItemState;

#[ORM\Entity]
#[ORM\Table(name: 'opotopo_item')]
#[ORM\UniqueConstraint(name: 'uniq_opotopo_item_catalog_item_key', columns: ['catalog', 'item_key'])]
#[ORM\Index(name: 'idx_opotopo_item_error', columns: ['error'])]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $catalog;

    #[ORM\Column(length: 190)]
    private string $itemKey;

    #[ORM\Column(length: 255)]
    private string $sku;

    #[ORM\Column(type: Types::GUID, nullable: true)]
    private ?string $advertisementId;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $fingerprint;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $error;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $errorDetails;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $syncedAt;

    public function __construct(string $catalog, ItemState $state)
    {
        $this->catalog = $catalog;
        $this->itemKey = $state->key;
        $this->sku = $state->sku;
        $this->advertisementId = $state->advertisementId;
        $this->fingerprint = $state->fingerprint;
        $this->error = $state->error;
        $this->errorDetails = $state->errorDetails;
        $this->syncedAt = $state->syncedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCatalog(): string
    {
        return $this->catalog;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    /** @return array<string, mixed> */
    public function getErrorDetails(): array
    {
        return $this->errorDetails ?? [];
    }
}
