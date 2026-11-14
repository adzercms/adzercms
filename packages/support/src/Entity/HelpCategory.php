<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Entity;

use AdzerCMS\Core\Ui\Placed;
use AdzerCMS\Support\Repository\HelpCategoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HelpCategoryRepository::class)]
#[ORM\Table(name: 'help_category')]
#[ORM\UniqueConstraint(name: 'uniq_help_category_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_help_category_position', columns: ['position'])]
class HelpCategory implements Placed
{
    public const SLUG = 64;
    public const TITLE = 190;
    public const SEGMENT = '[\p{Ll}\p{Nd}-]+';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function __construct(
        #[ORM\Column(length: self::TITLE)]
        private string $title,
        #[ORM\Column(length: self::SLUG)]
        private string $slug,
    ) {
    }

    public static function parse(string $input): ?string
    {
        $slug = mb_strtolower(trim($input));

        return mb_strlen($slug) <= self::SLUG && 1 === preg_match('~^'.self::SEGMENT.'$~u', $slug) ? $slug : null;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function place(int $position): void
    {
        $this->position = $position;
    }

    public function edit(string $title, string $slug, ?string $description): void
    {
        $this->title = $title;
        $this->slug = $slug;
        $this->description = $description;
    }
}
