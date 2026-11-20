<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Entity;

use AdzerCMS\Builder\Repository\ProjectBlockRepository;
use AdzerCMS\Pages\Entity\BlockRecord;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectBlockRepository::class)]
#[ORM\Table(name: 'builder_project_blocks')]
#[ORM\Index(name: 'idx_builder_project_blocks_page', columns: ['page_id', 'position'])]
class ProjectBlock extends BlockRecord
{
    /**
     * @param array<string, string> $values
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ProjectPage $page,
        string $type,
        array $values = [],
    ) {
        parent::__construct($type, $values);
    }

    public function getPage(): ProjectPage
    {
        return $this->page;
    }
}
