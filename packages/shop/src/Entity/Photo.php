<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop_photo')]
#[ORM\Index(name: 'idx_shop_photo_product', columns: ['product_id'])]
class Photo
{
    public const int FILE = 64;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'photos')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Product $product,
        #[ORM\Column(length: self::FILE)]
        private string $file,
        #[ORM\Column]
        private int $position,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
