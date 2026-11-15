<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Photo\Photos;

final readonly class Avatars
{
    private const PLACE = 'avatars';
    private const SIDE = 96;

    public function __construct(private Photos $photos)
    {
    }

    public function fill(User $user, ?string $picture): void
    {
        if (null === $user->getAvatar() && null !== $picture) {
            $user->setAvatar($this->photos->fetched($picture, self::PLACE, self::SIDE));
        }
    }

    public function path(string $name): ?string
    {
        return $this->photos->path(self::PLACE, $name);
    }

    public function drop(User $user): void
    {
        $this->photos->drop(self::PLACE, array_filter([$user->getAvatar()]));
    }
}
