<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Entity;

use AdzerCMS\Core\Ui\Placed;
use AdzerCMS\Pages\Repository\MenuEntryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuEntryRepository::class)]
#[ORM\Table(name: 'page_menu')]
#[ORM\UniqueConstraint(name: 'uniq_page_menu_page', columns: ['page_id'])]
#[ORM\UniqueConstraint(name: 'uniq_page_menu_section', columns: ['section'])]
class MenuEntry implements Placed
{
    public const SECTION = 190;
    public const ADDRESS = 2048;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Page $page = null;

    #[ORM\Column(length: self::SECTION, nullable: true)]
    private ?string $section = null;

    #[ORM\Column(length: Page::MENU, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(length: self::ADDRESS, nullable: true)]
    private ?string $address = null;

    private function __construct()
    {
    }

    public static function ofPage(Page $page): self
    {
        return (new self())->toPage($page);
    }

    public static function ofSection(string $route): self
    {
        return (new self())->toSection($route);
    }

    public static function ofLink(string $label, string $address): self
    {
        return (new self())->toLink($label, $address);
    }

    public function toPage(Page $page): self
    {
        [$this->page, $this->section, $this->label, $this->address] = [$page, null, null, null];

        return $this;
    }

    public function toSection(string $route): self
    {
        [$this->page, $this->section, $this->label, $this->address] = [null, $route, null, null];

        return $this;
    }

    public function toLink(string $label, string $address): self
    {
        [$this->page, $this->section, $this->label, $this->address] = [null, null, $label, $address];

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPage(): ?Page
    {
        return $this->page;
    }

    public function getSection(): ?string
    {
        return $this->section;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function place(int $position): void
    {
        $this->position = $position;
    }
}
