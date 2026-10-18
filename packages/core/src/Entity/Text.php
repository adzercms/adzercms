<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
class Text
{
    public const TABLE = 'texts';
    public const LOCALE = 16;
    public const DOMAIN = 64;
    public const MESSAGE = 255;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: self::LOCALE)]
        public readonly string $locale,
        #[ORM\Id]
        #[ORM\Column(length: self::DOMAIN)]
        public readonly string $domain,
        #[ORM\Id]
        #[ORM\Column(length: self::MESSAGE)]
        public readonly string $message,
        #[ORM\Column(type: Types::TEXT)]
        private string $value,
    ) {
    }

    public function value(): string
    {
        return $this->value;
    }

    public function change(string $value): void
    {
        $this->value = $value;
    }
}
