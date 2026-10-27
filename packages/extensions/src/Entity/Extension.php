<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Entity;

use AdzerCMS\Contracts\Module\Category;
use AdzerCMS\Contracts\Module\ExtensionType;
use AdzerCMS\Core\Composer\Description;
use AdzerCMS\Core\Composer\Package;
use AdzerCMS\Core\Ui\Placed;
use AdzerCMS\Extensions\ExtensionDecision;
use AdzerCMS\Extensions\ExtensionState;
use AdzerCMS\Extensions\Repository\ExtensionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExtensionRepository::class)]
#[ORM\Table(name: 'extension')]
#[ORM\UniqueConstraint(name: 'uniq_extension_name', columns: ['name'])]
#[ORM\Index(name: 'idx_extension_position', columns: ['position'])]
#[ORM\Index(name: 'idx_extension_decision', columns: ['decision', 'position'])]
class Extension implements Placed
{
    private const VERSION = 64;
    private const LINK = 255;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'user_id', onDelete: 'CASCADE')]
    private ?ExtensionAuthor $author;

    #[ORM\Column(length: 16, enumType: ExtensionDecision::class)]
    private ExtensionDecision $decision;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(length: ExtensionAuthor::KEY, nullable: true)]
    private ?string $authorKey = null;

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

    /** @var array<string, string>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $descriptionTranslations = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $version = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $homepage = null;

    /** @var Collection<int, ExtensionCategory> */
    #[ORM\OneToMany(targetEntity: ExtensionCategory::class, mappedBy: 'extension', cascade: ['persist'], orphanRemoval: true)]
    private Collection $categories;

    public function __construct(?ExtensionAuthor $author = null)
    {
        $this->author = $author;
        $this->decision = null === $author ? ExtensionDecision::Published : ExtensionDecision::Pending;
        $this->categories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuthor(): ?ExtensionAuthor
    {
        return $this->author;
    }

    public function getDecision(): ExtensionDecision
    {
        return $this->decision;
    }

    public function getReason(): ?string
    {
        return $this->reason;
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

    public function getDescription(): ?Description
    {
        return null === $this->description ? null : new Description($this->description, $this->descriptionTranslations ?? []);
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

    /** @return list<Category> */
    public function getCategories(): array
    {
        return Category::ordered($this->categories->map(static fn (ExtensionCategory $held): Category => $held->getCategory())->toArray());
    }

    public function state(): ExtensionState
    {
        return match (true) {
            ExtensionDecision::Published !== $this->decision => $this->decision->state(),
            null === $this->version => ExtensionState::Missing,
            null !== $this->author && $this->authorKey !== $this->author->getKey() => ExtensionState::Unclaimed,
            default => ExtensionState::Published,
        };
    }

    public function decide(ExtensionDecision $decision, ?string $reason = null): void
    {
        $this->decision = $decision;
        $this->reason = $reason;
    }

    public function revise(): void
    {
        if (ExtensionDecision::Rejected === $this->decision || ExtensionDecision::Withdrawn === $this->decision) {
            $this->decide(ExtensionDecision::Pending);
        }
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
        $this->description = $package?->description->source;
        $this->descriptionTranslations = $package?->description->translations;
        $this->version = self::fit($package?->version, self::VERSION);
        $this->source = self::fit($package?->source, self::LINK);
        $this->homepage = self::fit($package?->homepage, self::LINK);
        $this->authorKey = self::fit($package?->author, ExtensionAuthor::KEY);

        $categories = $package->categories ?? [];
        foreach ($this->categories as $held) {
            if (!\in_array($held->getCategory(), $categories, true)) {
                $this->categories->removeElement($held);
            }
        }
        foreach ($categories as $category) {
            if (!$this->categories->exists(static fn (int $key, ExtensionCategory $held): bool => $category === $held->getCategory())) {
                $this->categories->add(new ExtensionCategory($this, $category));
            }
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
