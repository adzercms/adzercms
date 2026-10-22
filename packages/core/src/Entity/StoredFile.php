<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class StoredFile
{
    public const NAME_MAX = 190;

    private const PICTURES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const DOCUMENT = 'application/pdf';

    #[ORM\Column(length: self::NAME_MAX)]
    private string $name;

    #[ORM\Column(length: 64)]
    private string $storedName;

    #[ORM\Column(length: 128)]
    private string $mime;

    #[ORM\Column]
    private int $size;

    public function __construct(string $name, string $storedName, string $mime, int $size)
    {
        $this->name = $name;
        $this->storedName = $storedName;
        $this->mime = $mime;
        $this->size = $size;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStoredName(): string
    {
        return $this->storedName;
    }

    public function getMime(): string
    {
        return $this->mime;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function isPicture(): bool
    {
        return \in_array($this->mime, self::PICTURES, true);
    }

    public function isInline(): bool
    {
        return $this->isPicture() || self::DOCUMENT === $this->mime;
    }
}
