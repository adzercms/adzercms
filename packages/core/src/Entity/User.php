<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Entity;

use AdzerCMS\Core\Account\ContactKind;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Account\Status;
use AdzerCMS\Core\Account\Theme;
use AdzerCMS\Core\Repository\UserRepository;
use AdzerCMS\Core\Security\Mark;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function Symfony\Component\Clock\now;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_phone', columns: ['phone'])]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
#[ORM\Index(name: 'idx_users_role_status', columns: ['role', 'status'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    public const COMMENT_LIMIT = 64;
    public const NAME_LIMIT = 60;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: self::COMMENT_LIMIT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(length: self::NAME_LIMIT, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(length: self::NAME_LIMIT, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: self::NAME_LIMIT, nullable: true)]
    private ?string $middleName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 254, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 16, enumType: Status::class)]
    private Status $status = Status::Active;

    #[ORM\Column(length: 16, enumType: Role::class)]
    private Role $role = Role::User;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 8, nullable: true, enumType: Theme::class)]
    private ?Theme $theme = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $language = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $mutedChannels = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct()
    {
        $this->createdAt = now();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isNamed(): bool
    {
        return null !== ($this->lastName ?? $this->firstName ?? $this->middleName);
    }

    public function setName(?string $lastName, ?string $firstName, ?string $middleName): self
    {
        [$this->lastName, $this->firstName, $this->middleName] = array_map(
            static fn (?string $part): ?string => '' === trim((string) $part) ? null : mb_substr(trim((string) $part), 0, self::NAME_LIMIT),
            [$lastName, $firstName, $middleName],
        );

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getMiddleName(): ?string
    {
        return $this->middleName;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = '' === trim((string) $comment) ? null : mb_substr(trim((string) $comment), 0, self::COMMENT_LIMIT);

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = '' === trim((string) $note) ? null : trim((string) $note);

        return $this;
    }

    public function getTheme(): ?Theme
    {
        return $this->theme;
    }

    public function setTheme(?Theme $theme): self
    {
        $this->theme = $theme;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): self
    {
        $this->language = $language;

        return $this;
    }

    /** @param list<string> $channels */
    public function mute(array $channels): self
    {
        $this->mutedChannels = array_values(array_unique($channels));

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = ContactKind::Phone->normalize($phone);

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = ContactKind::Email->normalize($email);

        return $this;
    }

    public function getContact(ContactKind $kind): ?string
    {
        return match ($kind) {
            ContactKind::Phone => $this->phone,
            ContactKind::Email => $this->email,
        };
    }

    public function setContact(ContactKind $kind, string $contact): self
    {
        return match ($kind) {
            ContactKind::Phone => $this->setPhone($contact),
            ContactKind::Email => $this->setEmail($contact),
        };
    }

    public function hears(string $channel): bool
    {
        return !\in_array($channel, $this->mutedChannels, true);
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): self
    {
        $this->status = $status;
        if (Status::Archived !== $status) {
            $this->deletedAt = null;
        }

        return $this;
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    public function delete(?\DateTimeImmutable $at = null): self
    {
        $this->status = Status::Archived;
        $this->deletedAt = $at ?? now();

        return $this;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function enter(): self
    {
        $this->lastLoginAt = now();

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return null === $this->id ? throw new \LogicException('an unsaved record has no login') : (string) $this->id;
    }

    public function getRoles(): array
    {
        return [$this->role->security()];
    }

    public function mark(): Mark
    {
        return new Mark(
            hash('sha256', implode("\0", [$this->getUserIdentifier(), (string) $this->password, $this->status->value, $this->role->value])),
            $this->email,
        );
    }

    public function keeps(Mark $mark): bool
    {
        return $mark->key === $this->mark()->key && (null === $mark->email || $mark->email === $this->email);
    }

    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self && $user->keeps($this->mark());
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }
}
