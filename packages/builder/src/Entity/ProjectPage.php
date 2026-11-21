<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Entity;

use AdzerCMS\Builder\Repository\ProjectPageRepository;
use AdzerCMS\Core\Ui\Placed;
use AdzerCMS\Pages\Entity\Page;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectPageRepository::class)]
#[ORM\Table(name: 'builder_project_pages')]
#[ORM\UniqueConstraint(name: 'uniq_builder_project_pages_path', columns: ['project_id', 'path'])]
#[ORM\Index(name: 'idx_builder_project_pages_project', columns: ['project_id', 'position'])]
class ProjectPage implements Placed
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $position = 0;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Project $project,
        #[ORM\Column(length: Page::TITLE)]
        private string $title,
        #[ORM\Column(length: Page::PATH)]
        private string $path,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function place(int $position): void
    {
        $this->position = $position;
    }
}
