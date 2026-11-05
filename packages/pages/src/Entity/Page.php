<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Entity;

use AdzerCMS\Core\Ui\Placed;
use AdzerCMS\Pages\Repository\PageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PageRepository::class)]
#[ORM\Table(name: 'pages')]
#[ORM\UniqueConstraint(name: 'uniq_pages_path', columns: ['path'])]
#[ORM\Index(name: 'idx_pages_position', columns: ['position'])]
class Page implements Placed
{
    public const ROOT = '/';
    public const SEGMENTS = '[\p{Ll}\p{Nd}-]+(?:/[\p{Ll}\p{Nd}-]+)*';
    public const PATH = 190;
    public const TITLE = 190;
    public const MENU = 64;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $published = false;

    #[ORM\Column]
    private bool $menu = false;

    #[ORM\Column(length: self::MENU, nullable: true)]
    private ?string $menuLabel = null;

    public function __construct(
        #[ORM\Column(length: self::TITLE)]
        private string $title,
        #[ORM\Column(length: self::PATH)]
        private string $path,
    ) {
    }

    public static function parse(string $input): ?string
    {
        $path = mb_strtolower(trim($input));
        if ('' === $path) {
            return null;
        }
        $path = self::ROOT.trim($path, '/');

        return mb_strlen($path) <= self::PATH && (self::ROOT === $path || 1 === preg_match('~^/'.self::SEGMENTS.'$~u', $path)) ? $path : null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function inMenu(): bool
    {
        return $this->menu;
    }

    public function menuLabel(): string
    {
        return $this->menuLabel ?? $this->title;
    }

    public function getMenuLabel(): ?string
    {
        return $this->menuLabel;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function place(int $position): void
    {
        $this->position = $position;
    }

    public function edit(string $title, string $path, ?string $description, bool $published, bool $menu, ?string $menuLabel): void
    {
        $this->title = $title;
        $this->path = $path;
        $this->description = $description;
        $this->published = $published;
        $this->menu = $menu;
        $this->menuLabel = $menuLabel;
    }
}
