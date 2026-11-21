<?php

declare(strict_types=1);

namespace AdzerCMS\Builder;

use AdzerCMS\Builder\Repository\ProjectRepository;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\AccountPolicy;
use AdzerCMS\Core\Entity\User;

final readonly class ProjectPolicy implements AccountPolicy
{
    public function __construct(
        private ProjectRepository $projects,
        private ProjectAlbums $albums,
    ) {
    }

    public function registration(): bool
    {
        return false;
    }

    public function registered(User $user): void
    {
    }

    public function deletion(): bool
    {
        return false;
    }

    public function deletionRefusal(User $user): ?Refusal
    {
        return null;
    }

    public function removalRefusal(User $user): ?Refusal
    {
        return null;
    }

    public function archived(User $user): void
    {
    }

    public function changed(User $user): void
    {
    }

    public function removing(User $user): void
    {
        foreach ($this->projects->findBy(['owner' => $user]) as $project) {
            foreach ($this->albums->all($project) as $album) {
                $album->clear();
            }
        }
    }
}
