<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Entity;

use AdzerCMS\Pages\Repository\PagePathRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PagePathRepository::class)]
#[ORM\Table(name: 'page_paths')]
#[ORM\UniqueConstraint(name: 'uniq_page_paths_path', columns: ['path'])]
class PagePath
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Page $page,
        #[ORM\Column(length: Page::PATH)]
        private string $path,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPage(): Page
    {
        return $this->page;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
