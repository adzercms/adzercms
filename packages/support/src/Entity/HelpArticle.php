<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Entity;

use AdzerCMS\Core\Ui\Placed;
use AdzerCMS\Support\Repository\HelpArticleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HelpArticleRepository::class)]
#[ORM\Table(name: 'help_article')]
#[ORM\UniqueConstraint(name: 'uniq_help_article_slug', columns: ['category_id', 'slug'])]
#[ORM\Index(name: 'idx_help_article_category', columns: ['category_id', 'position'])]
class HelpArticle implements Placed
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $tags = [];

    #[ORM\Column(type: Types::TEXT)]
    private string $body = '';

    #[ORM\Column]
    private bool $published = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(length: HelpCategory::TITLE)]
    private string $heading;

    #[ORM\Column(type: Types::TEXT)]
    private string $search;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private HelpCategory $category,
        #[ORM\Column(length: HelpCategory::TITLE)]
        private string $title,
        #[ORM\Column(length: HelpCategory::SLUG)]
        private string $slug,
        \DateTimeImmutable $moment,
    ) {
        $this->updatedAt = $moment;
        $this->heading = self::fold($title);
        $this->search = $this->heading;
    }

    public static function fold(string $text): string
    {
        return str_replace('ё', 'е', mb_strtolower($text));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): HelpCategory
    {
        return $this->category;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /** @return list<string> */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function place(int $position): void
    {
        $this->position = $position;
    }

    /** @param list<string> $tags */
    public function edit(HelpCategory $category, string $title, string $slug, ?string $description, array $tags, string $body, bool $published, \DateTimeImmutable $moment): void
    {
        if ([$title, $description, $tags, $body] !== [$this->title, $this->description, $this->tags, $this->body]) {
            $this->updatedAt = $moment;
        }
        $this->category = $category;
        $this->title = $title;
        $this->slug = $slug;
        $this->description = $description;
        $this->tags = $tags;
        $this->body = $body;
        $this->published = $published;
        $this->heading = self::fold($title);
        $this->search = self::fold(implode(' ', [$title, $description ?? '', ...$tags, $body]));
    }
}
