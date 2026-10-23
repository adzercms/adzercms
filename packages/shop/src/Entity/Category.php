<?php

declare(strict_types=1);

namespace AdzerCMS\Shop\Entity;

use AdzerCMS\Core\Ui\Placed;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop_category')]
#[ORM\Index(name: 'idx_shop_category_position', columns: ['position'])]
class Category implements Placed
{
    public const int NAME = 190;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: self::NAME)]
        private string $name,
        #[ORM\Column]
        private int $position = 0,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function change(string $name): void
    {
        $this->name = $name;
    }

    public function place(int $position): void
    {
        $this->position = $position;
    }
}
