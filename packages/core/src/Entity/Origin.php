<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class Origin
{
    public const LENGTH = 64;
    public const TERM_LENGTH = 128;

    #[ORM\Column(length: self::LENGTH, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(length: self::LENGTH, nullable: true)]
    private ?string $medium = null;

    #[ORM\Column(length: self::LENGTH, nullable: true)]
    private ?string $campaign = null;

    #[ORM\Column(length: self::TERM_LENGTH, nullable: true)]
    private ?string $term = null;

    #[ORM\Column(length: self::LENGTH, nullable: true)]
    private ?string $click = null;

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getMedium(): ?string
    {
        return $this->medium;
    }

    public function getCampaign(): ?string
    {
        return $this->campaign;
    }

    public function getTerm(): ?string
    {
        return $this->term;
    }

    public function getClick(): ?string
    {
        return $this->click;
    }

    public function isKnown(): bool
    {
        return null !== $this->source || null !== $this->campaign;
    }

    /** @param array<string, string> $marks */
    public function fill(array $marks): self
    {
        $this->source = $marks['source'] ?? null;
        $this->medium = $marks['medium'] ?? null;
        $this->campaign = $marks['campaign'] ?? null;
        $this->term = $marks['term'] ?? null;
        $this->click = $marks['click'] ?? null;

        return $this;
    }

    /** @return array<string, string> */
    public function marks(): array
    {
        return array_filter([
            'source' => (string) $this->source,
            'medium' => (string) $this->medium,
            'campaign' => (string) $this->campaign,
            'term' => (string) $this->term,
        ]);
    }
}
