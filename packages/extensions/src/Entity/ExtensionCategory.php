<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Entity;

use AdzerCMS\Contracts\Module\Category;
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
    #[ORM\Column(length: 64, enumType: Category::class)]
    private Category $category;

    public function __construct(Extension $extension, Category $category)
    {
        $this->extension = $extension;
        $this->category = $category;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }
}
