<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'source_change')]
class SourceChange
{
    public const int SOURCE = 190;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $announced = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: self::SOURCE)]
        private string $source,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $moment,
    ) {
    }

    public function unannounced(): bool
    {
        return null === $this->announced || $this->announced < $this->moment;
    }

    public function announce(): void
    {
        $this->announced = $this->moment;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getMoment(): \DateTimeImmutable
    {
        return $this->moment;
    }

    public function touch(\DateTimeImmutable $moment): void
    {
        $this->moment = $moment;
    }
}
