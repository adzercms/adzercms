<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Entity;

use AdzerCMS\Core\Ui\Money;
use AdzerCMS\Core\Ui\Placed;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop_product')]
#[ORM\Index(name: 'idx_shop_product_category', columns: ['category_id'])]
#[ORM\Index(name: 'idx_shop_product_active_position', columns: ['active', 'position'])]
class Product implements Placed
{
    public const int NAME = 190;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private int $position = 0;

    /** @var Collection<int, Variant> */
    #[ORM\OneToMany(targetEntity: Variant::class, mappedBy: 'product', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $variants;

    /** @var Collection<int, Photo> */
    #[ORM\OneToMany(targetEntity: Photo::class, mappedBy: 'product', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $photos;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Category $category,
        #[ORM\Column(length: self::NAME)]
        private string $name,
    ) {
        $this->variants = new ArrayCollection();
        $this->photos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /** @return list<Variant> */
    public function getVariants(): array
    {
        return array_values($this->variants->toArray());
    }

    public function variant(?int $id): ?Variant
    {
        return $this->variants->findFirst(static fn (int $key, Variant $variant): bool => $variant->getId() === $id);
    }

    /** @return list<Photo> */
    public function getPhotos(): array
    {
        return array_values($this->photos->toArray());
    }

    public function photo(): ?string
    {
        $photo = $this->photos->first();

        return false === $photo ? null : $photo->getFile();
    }

    public function lowest(): ?string
    {
        $lowest = null;
        foreach ($this->variants as $variant) {
            $lowest = null === $lowest ? $variant->getPrice() : Money::least($lowest, $variant->getPrice());
        }

        return $lowest ?? $this->price;
    }

    public function isPriceFrom(): bool
    {
        $lowest = $this->lowest();

        return null !== $this->variants->findFirst(static fn (int $key, Variant $variant): bool => 0 !== Money::compare($variant->getPrice(), (string) $lowest));
    }

    public function change(Category $category, string $name, ?string $description, bool $active): void
    {
        $this->category = $category;
        $this->name = $name;
        $this->description = $description;
        $this->active = $active;
    }

    public function place(int $position): void
    {
        $this->position = $position;
    }

    /** @param list<array{id: ?int, name: string, price: string}> $rows */
    public function vary(?string $price, array $rows): void
    {
        $kept = array_filter(array_column($rows, 'id'));
        foreach ($this->variants as $variant) {
            if (!\in_array($variant->getId(), $kept, true)) {
                $this->variants->removeElement($variant);
            }
        }
        foreach ($rows as $position => $row) {
            $variant = null === $row['id'] ? null : $this->variant($row['id']);
            if (null === $variant) {
                $this->variants->add(new Variant($this, $row['name'], $row['price'], $position + 1));
            } else {
                $variant->change($row['name'], $row['price'], $position + 1);
            }
        }
        $this->price = [] === $rows ? $price : null;
    }

    public function show(string $file): void
    {
        $last = $this->photos->last();
        $this->photos->add(new Photo($this, $file, false === $last ? 1 : $last->getPosition() + 1));
    }

    public function hide(Photo $photo): void
    {
        $this->photos->removeElement($photo);
    }
}
