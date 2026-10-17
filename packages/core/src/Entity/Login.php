<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Entity;

use AdzerCMS\Core\Repository\LoginRepository;
use AdzerCMS\Core\Security\Mark;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function Symfony\Component\Clock\now;

#[ORM\Entity(repositoryClass: LoginRepository::class)]
#[ORM\Table(name: 'login')]
#[ORM\Index(name: 'idx_login_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_login_seen_at', columns: ['seen_at'])]
class Login
{
    public const LABEL_LIMIT = 128;

    #[ORM\Id]
    #[ORM\Column(length: 32)]
    private string $handle;

    #[ORM\Column(length: 64)]
    private string $mark;

    #[ORM\Column(length: 254, nullable: true)]
    private ?string $email;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $enteredAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $seenAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(length: self::LABEL_LIMIT, nullable: true)]
        private ?string $device,
        #[ORM\Column(length: self::LABEL_LIMIT, nullable: true)]
        private ?string $place,
        #[ORM\Column(length: 45, nullable: true)]
        private ?string $address,
    ) {
        $this->handle = bin2hex(random_bytes(16));
        $mark = $user->mark();
        $this->mark = $mark->key;
        $this->email = $mark->email;
        $this->enteredAt = $this->seenAt = now();
    }

    public function getHandle(): string
    {
        return $this->handle;
    }

    public function getDevice(): ?string
    {
        return $this->device;
    }

    public function getPlace(): ?string
    {
        return $this->place;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getEnteredAt(): \DateTimeImmutable
    {
        return $this->enteredAt;
    }

    public function getSeenAt(): \DateTimeImmutable
    {
        return $this->seenAt;
    }

    public function mark(): Mark
    {
        return new Mark($this->mark, $this->email);
    }
}
