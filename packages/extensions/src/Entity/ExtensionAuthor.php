<?php

declare(strict_types=1);

namespace AdzerCMS\Extensions\Entity;

use AdzerCMS\Core\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'extension_author')]
#[ORM\UniqueConstraint(name: 'uniq_extension_author_key', columns: ['author_key'])]
class ExtensionAuthor
{
    public const KEY = 32;

    #[ORM\Id]
    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'author_key', length: self::KEY)]
    private string $key;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->key = bin2hex(random_bytes(self::KEY / 2));
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getKey(): string
    {
        return $this->key;
    }
}
