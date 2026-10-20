<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Entity;

use AdzerCMS\Core\Ui\Placed;
use AdzerCMS\Extensions\ExtensionType;
use AdzerCMS\Extensions\Repository\ExtensionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExtensionRepository::class)]
#[ORM\Table(name: 'extension')]
#[ORM\UniqueConstraint(name: 'uniq_extension_name', columns: ['name'])]
#[ORM\Index(name: 'idx_extension_position', columns: ['position'])]
class Extension implements Placed
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 190)]
    private string $name = '';

    #[ORM\Column(length: 24, enumType: ExtensionType::class)]
    private ExtensionType $type = ExtensionType::Module;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $demo = null;

    #[ORM\Column]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): ExtensionType
    {
        return $this->type;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function getDemo(): ?string
    {
        return $this->demo;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function describe(string $name, ExtensionType $type, ?string $price, ?string $demo): self
    {
        $this->name = $name;
        $this->type = $type;
        $this->price = $price;
        $this->demo = $demo;

        return $this;
    }

    public function place(int $position): void
    {
        $this->position = $position;
    }
}
