<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'extension_category')]
#[ORM\Index(name: 'idx_extension_category_category', columns: ['category'])]
class ExtensionCategory
{
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'categories')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Extension $extension;

    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $category;

    public function __construct(Extension $extension, string $category)
    {
        $this->extension = $extension;
        $this->category = $category;
    }

    public function getExtension(): Extension
    {
        return $this->extension;
    }

    public function getCategory(): string
    {
        return $this->category;
    }
}
