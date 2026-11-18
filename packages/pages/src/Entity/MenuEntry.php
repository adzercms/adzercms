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
        $entry = new self();
        $entry->toPage($page);

        return $entry;
    }

    public static function ofSection(string $route): self
    {
        $entry = new self();
        $entry->toSection($route);

        return $entry;
    }

    public static function ofLink(string $label, string $address): self
    {
        $entry = new self();
        $entry->toLink($label, $address);

        return $entry;
    }

    public function toPage(Page $page): void
    {
        [$this->page, $this->section, $this->label, $this->address] = [$page, null, null, null];
    }

    public function toSection(string $route): void
    {
        [$this->page, $this->section, $this->label, $this->address] = [null, $route, null, null];
    }

    public function toLink(string $label, string $address): void
    {
        [$this->page, $this->section, $this->label, $this->address] = [null, null, $label, $address];
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
