<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
class Setting
{
    public const TABLE = 'setting';

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 64)]
        public readonly string $name,
        #[ORM\Column(type: Types::TEXT)]
        private string $value,
    ) {
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }
}
