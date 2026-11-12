<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Security;

use AdzerCMS\Contracts\Module\ModuleManagement;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, mixed> */
final class ModuleVoter extends Voter
{
    public const MANAGE = 'ADZERCMS_MODULES';

    /** @param iterable<ModuleManagement> $managements */
    public function __construct(
        #[AutowireIterator(ModuleManagement::class)]
        private readonly iterable $managements,
    ) {
    }

    public function supportsAttribute(string $attribute): bool
    {
        return self::MANAGE === $attribute;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        foreach ($this->managements as $management) {
            if (!$management->allowed()) {
                return false;
            }
        }

        return true;
    }
}
