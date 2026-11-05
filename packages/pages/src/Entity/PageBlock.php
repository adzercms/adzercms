<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Entity;

use AdzerCMS\Core\Ui\Placed;
use AdzerCMS\Pages\Repository\PageBlockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PageBlockRepository::class)]
#[ORM\Table(name: 'page_blocks')]
#[ORM\Index(name: 'idx_page_blocks_page', columns: ['page_id', 'position'])]
class PageBlock implements Placed
{
    public const TYPE = 64;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $position = 0;

    /**
     * @param array<string, string> $values
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Page $page,
        #[ORM\Column(length: self::TYPE)]
        private string $type,
        #[ORM\Column(name: 'field_values', type: 'json')]
        private array $values = [],
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

    public function getType(): string
    {
        return $this->type;
    }

    /** @return array<string, string> */
    public function getValues(): array
    {
        return $this->values;
    }

    /** @param array<string, string> $values */
    public function fill(array $values): void
    {
        $this->values = $values;
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
