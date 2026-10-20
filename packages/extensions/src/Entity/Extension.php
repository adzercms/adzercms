<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Entity;

use AdzerCMS\Core\Ui\Placed;
use AdzerCMS\Extensions\ExtensionState;
use AdzerCMS\Extensions\ExtensionType;
use AdzerCMS\Extensions\Package;
use AdzerCMS\Extensions\Repository\ExtensionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExtensionRepository::class)]
#[ORM\Table(name: 'extension')]
#[ORM\UniqueConstraint(name: 'uniq_extension_name', columns: ['name'])]
#[ORM\Index(name: 'idx_extension_position', columns: ['position'])]
class Extension implements Placed
{
    private const VERSION = 64;
    private const LINK = 255;
    private const CATEGORY = 64;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 190)]
    private string $name = '';

    #[ORM\Column(length: 24, enumType: ExtensionType::class)]
    private ExtensionType $type = ExtensionType::Module;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $demo = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $version = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $homepage = null;

    /** @var Collection<int, ExtensionCategory> */
    #[ORM\OneToMany(targetEntity: ExtensionCategory::class, mappedBy: 'extension', cascade: ['persist'], orphanRemoval: true)]
    private Collection $categories;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): ExtensionType
    {
        return $this->type;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function getDemo(): ?string
    {
        return $this->demo;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getHomepage(): ?string
    {
        return $this->homepage;
    }

    /** @return list<string> */
    public function getCategories(): array
    {
        return array_values($this->categories->map(static fn (ExtensionCategory $category): string => $category->getCategory())->toArray());
    }

    public function state(): ExtensionState
    {
        return null === $this->version ? ExtensionState::Missing : ExtensionState::Published;
    }

    public function describe(string $name, ExtensionType $type, ?string $price, ?string $demo): self
    {
        if ($name !== $this->name) {
            $this->know(null);
        }

        $this->name = $name;
        $this->type = $type;
        $this->price = $price;
        $this->demo = $demo;

        return $this;
    }

    public function know(?Package $package): self
    {
        $this->description = $package?->description;
        $this->version = self::fit($package?->version, self::VERSION);
        $this->source = self::fit($package?->source, self::LINK);
        $this->homepage = self::fit($package?->homepage, self::LINK);

        $categories = array_filter($package->categories ?? [], static fn (string $category): bool => null !== self::fit($category, self::CATEGORY));
        foreach ($this->categories as $held) {
            if (!\in_array($held->getCategory(), $categories, true)) {
                $this->categories->removeElement($held);
            }
        }
        foreach (array_diff(array_unique($categories), $this->getCategories()) as $category) {
            $this->categories->add(new ExtensionCategory($this, $category));
        }

        return $this;
    }

    public function place(int $position): void
    {
        $this->position = $position;
    }

    private static function fit(?string $value, int $length): ?string
    {
        return null !== $value && mb_strlen($value) <= $length ? $value : null;
    }
}
