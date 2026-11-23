<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Account;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Photo\Photos;
use Symfony\Component\HttpFoundation\File\File;

final readonly class Avatars
{
    private const PLACE = 'avatars';
    private const SIDE = 192;

    public function __construct(private Photos $photos)
    {
    }

    public function fill(User $user, ?string $picture): void
    {
        if (null === $user->getAvatar() && null !== $picture) {
            $user->setAvatar($this->photos->fetched($picture, self::PLACE, self::SIDE));
        }
    }

    public function own(User $user, File $photo): ?Refusal
    {
        $refusal = Photos::refusal([$photo]);
        if (null === $refusal) {
            $this->drop($user);
            $user->setAvatar($this->photos->store($photo, self::PLACE, self::SIDE));
        }

        return $refusal;
    }

    public function path(string $name): ?string
    {
        return $this->photos->path(self::PLACE, $name);
    }

    public function drop(User $user): void
    {
        $this->photos->drop(self::PLACE, array_filter([$user->getAvatar()]));
        $user->setAvatar(null);
    }
}
