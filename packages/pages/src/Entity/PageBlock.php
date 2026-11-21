<?php

declare(strict_types=1);

namespace AdzerCMS\Pages\Entity;

use AdzerCMS\Pages\Repository\PageBlockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PageBlockRepository::class)]
#[ORM\Table(name: 'page_blocks')]
#[ORM\Index(name: 'idx_page_blocks_page', columns: ['page_id', 'position'])]
class PageBlock extends BlockRecord
{
    /**
     * @param array<string, string> $values
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Page $page,
        string $type,
        array $values = [],
    ) {
        parent::__construct($type, $values);
    }

    public function getPage(): Page
    {
        return $this->page;
    }
}
