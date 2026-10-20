<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Entity;

use AdzerCMS\Core\Repository\ModuleSourceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModuleSourceRepository::class)]
#[ORM\Table(name: 'module_source')]
#[ORM\UniqueConstraint(name: 'uniq_module_source_url', columns: ['url'])]
class ModuleSource
{
    public const URL = 190;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 190, nullable: true)]
    private ?string $login = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $tokenCipher = null;

    public function __construct(
        #[ORM\Column(length: self::URL)]
        private string $url,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getLogin(): ?string
    {
        return $this->login;
    }

    public function getTokenCipher(): ?string
    {
        return $this->tokenCipher;
    }

    public function grant(?string $login, ?string $tokenCipher): void
    {
        $this->login = $login;
        $this->tokenCipher = $tokenCipher;
    }
}
